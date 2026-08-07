<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Integration\FileStorageConnector;
use SquirrelForge\Integration\IntegrationAuthentication;
use SquirrelForge\Integration\SqliteConnectorManager;

final class FileStorageConnectorTest extends TestCase
{
    /** @var array<int, string> */
    private array $databasePaths = [];

    protected function tearDown(): void
    {
        foreach ($this->databasePaths as $path) {
            foreach ([$path, $path . '-shm', $path . '-wal'] as $candidate) {
                if (is_file($candidate)) {
                    unlink($candidate);
                }
            }
        }
    }

    private function tempPath(string $label): string
    {
        $path = sys_get_temp_dir() . "/squirrelforge-file-storage-connector-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function connectorManager(): SqliteConnectorManager
    {
        return new SqliteConnectorManager($this->tempPath('connectors'));
    }

    private function activeConnectorId(SqliteConnectorManager $connectors): string
    {
        $registered = $connectors->register([
            'connector_name' => 'S3 Connector',
            'version' => '1.0.0',
            'owner_ref' => 'team_integrations',
            'endpoint_ref' => 'endpoint_ref_1',
            'protocol_ref' => 'REST',
            'configuration_ref' => 'config_ref_1',
            'credential_ref' => 'credential_ref_1',
            'governance_ref' => 'governance_ref_1',
        ]);
        $connectors->checkReadiness($registered['connector_id']);
        $connectors->activate($registered['connector_id']);

        return $registered['connector_id'];
    }

    public function testMissingConnectorIdIsRequestInvalid(): void
    {
        $files = new FileStorageConnector();

        $result = $files->coordinate(['handoff_type' => 'Read']);

        $this->assertSame('Request Invalid', $result['status']);
    }

    public function testUnapprovedHandoffTypeIsRequestInvalid(): void
    {
        $files = new FileStorageConnector();

        $result = $files->coordinate(['connector_id' => 'x', 'handoff_type' => 'Rename']);

        $this->assertSame('Request Invalid', $result['status']);
        $this->assertStringContainsString('approved handoff types', $result['error']);
    }

    public function testUnregisteredConnectorIsConnectorBlocked(): void
    {
        $files = new FileStorageConnector(new SqliteConnectorManager($this->tempPath('empty')));

        $result = $files->coordinate(['connector_id' => 'ghost', 'handoff_type' => 'Read']);

        $this->assertSame('Connector Blocked', $result['status']);
        $this->assertStringContainsString('not registered', $result['error']);
    }

    public function testUnroutableConnectorIsConnectorBlocked(): void
    {
        $connectors = $this->connectorManager();
        $registered = $connectors->register([
            'connector_name' => 'S3 Connector',
            'version' => '1.0.0',
            'owner_ref' => 'team_integrations',
            'endpoint_ref' => 'endpoint_ref_1',
            'protocol_ref' => 'REST',
            'configuration_ref' => 'config_ref_1',
            'credential_ref' => 'credential_ref_1',
            'governance_ref' => 'governance_ref_1',
        ]);
        $files = new FileStorageConnector($connectors);

        $result = $files->coordinate(['connector_id' => $registered['connector_id'], 'handoff_type' => 'Read']);

        $this->assertSame('Connector Blocked', $result['status']);
        $this->assertStringContainsString('not active or degraded', $result['error']);
    }

    public function testCredentialRequiredWithoutAuthenticationComponentIsCredentialBlocked(): void
    {
        $connectors = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectors);
        $files = new FileStorageConnector($connectors);

        $result = $files->coordinate(['connector_id' => $connectorId, 'handoff_type' => 'Create', 'credential_ref' => 'cred_1']);

        $this->assertSame('Credential Blocked', $result['status']);
        $this->assertStringContainsString('IntegrationAuthentication', $result['error']);
    }

    public function testFailedHandshakeIsCredentialBlocked(): void
    {
        $connectors = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectors);
        $files = new FileStorageConnector($connectors, new IntegrationAuthentication());

        $result = $files->coordinate(
            ['connector_id' => $connectorId, 'handoff_type' => 'Create', 'credential_ref' => 'cred_1'],
            signHandshake: static fn(array $refs): array => ['token' => null, 'expires_at' => null, 'error' => 'invalid_client']
        );

        $this->assertSame('Credential Blocked', $result['status']);
        $this->assertSame('invalid_client', $result['error']);
    }

    public function testCoordinateWithoutAHandoffIsADryRun(): void
    {
        $connectors = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectors);
        $files = new FileStorageConnector($connectors);

        $result = $files->coordinate(['connector_id' => $connectorId, 'handoff_type' => 'Read']);

        $this->assertSame('Ready', $result['status']);
        $this->assertNull($result['response']);
        $this->assertNull($result['error']);
    }

    public function testSuccessfulCreateNormalizesChecksumAndVersion(): void
    {
        $connectors = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectors);
        $files = new FileStorageConnector($connectors);

        $result = $files->coordinate(
            ['connector_id' => $connectorId, 'handoff_type' => 'Create'],
            handoff: static fn(array $c, array $r): array => ['response' => 'ok', 'checksum_ref' => 'sha256:abc', 'version_ref' => 'v3']
        );

        $this->assertSame('Completed', $result['status']);
        $this->assertSame('sha256:abc', $result['checksum_ref']);
        $this->assertSame('v3', $result['version_ref']);
    }

    public function testSynchronizeNormalizesAvailabilityStatus(): void
    {
        $connectors = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectors);
        $files = new FileStorageConnector($connectors);

        $result = $files->coordinate(
            ['connector_id' => $connectorId, 'handoff_type' => 'Synchronize'],
            handoff: static fn(array $c, array $r): array => ['availability_status' => 'available']
        );

        $this->assertSame('Completed', $result['status']);
        $this->assertSame('available', $result['availability_status']);
    }

    public function testHandoffReturningAnErrorIsFailed(): void
    {
        $connectors = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectors);
        $files = new FileStorageConnector($connectors);

        $result = $files->coordinate(
            ['connector_id' => $connectorId, 'handoff_type' => 'Delete'],
            handoff: static fn(array $c, array $r): array => ['error' => 'object not found']
        );

        $this->assertSame('Failed', $result['status']);
        $this->assertSame('object not found', $result['error']);
    }

    public function testHandoffThrowingIsFailedNotAnUncaughtException(): void
    {
        $connectors = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectors);
        $files = new FileStorageConnector($connectors);

        $result = $files->coordinate(
            ['connector_id' => $connectorId, 'handoff_type' => 'Read'],
            handoff: static function (array $c, array $r): array {
                throw new RuntimeException('service unreachable');
            }
        );

        $this->assertSame('Failed', $result['status']);
        $this->assertSame('service unreachable', $result['error']);
    }

    public function testHandoffReceivesTheResolvedConnectorAndRequest(): void
    {
        $connectors = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectors);
        $files = new FileStorageConnector($connectors);
        $seenConnector = null;
        $seenRequest = null;

        $files->coordinate(
            ['connector_id' => $connectorId, 'handoff_type' => 'Copy', 'payload' => ['from' => 'a.txt', 'to' => 'b.txt']],
            handoff: function (array $c, array $r) use (&$seenConnector, &$seenRequest): array {
                $seenConnector = $c;
                $seenRequest = $r;

                return ['response' => 'ok'];
            }
        );

        $this->assertSame($connectorId, $seenConnector['connector_id']);
        $this->assertSame(['from' => 'a.txt', 'to' => 'b.txt'], $seenRequest['payload']);
    }

    public function testEventPayloadNeverExposesPayloadOrResponse(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('file_storage_connector.completed', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $connectors = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectors);
        $files = new FileStorageConnector($connectors, null, $events);

        $files->coordinate(
            ['connector_id' => $connectorId, 'handoff_type' => 'Read', 'payload' => ['path' => '/secret/file.txt']],
            handoff: static fn(array $c, array $r): array => ['response' => 'super-secret-file-contents']
        );

        $this->assertCount(1, $captured);
        $encoded = json_encode($captured[0]->getPayload(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('super-secret-file-contents', $encoded);
        $this->assertStringNotContainsString('/secret/file.txt', $encoded);
    }

    public function testWorksWithoutAConnectorManager(): void
    {
        $files = new FileStorageConnector();

        $result = $files->coordinate(['connector_id' => 'x', 'handoff_type' => 'Read']);

        $this->assertSame('Connector Blocked', $result['status']);
    }

    public function testWorksWithoutAnEventBus(): void
    {
        $connectors = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectors);
        $files = new FileStorageConnector($connectors, null, null);

        $result = $files->coordinate(['connector_id' => $connectorId, 'handoff_type' => 'Read']);

        $this->assertSame('Ready', $result['status']);
    }
}
