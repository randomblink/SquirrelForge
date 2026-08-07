<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Integration\IntegrationAuthentication;
use SquirrelForge\Integration\SqliteConnectorManager;
use SquirrelForge\Integration\VersionControlConnector;

final class VersionControlConnectorTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-vcs-connector-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
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
            'connector_name' => 'GitHub Connector',
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
        $vcs = new VersionControlConnector();

        $result = $vcs->coordinate(['handoff_type' => 'Clone']);

        $this->assertSame('Request Invalid', $result['status']);
    }

    public function testUnapprovedHandoffTypeIsRequestInvalid(): void
    {
        $vcs = new VersionControlConnector();

        $result = $vcs->coordinate(['connector_id' => 'x', 'handoff_type' => 'Rebase']);

        $this->assertSame('Request Invalid', $result['status']);
        $this->assertStringContainsString('approved handoff types', $result['error']);
    }

    public function testMissingHandoffTypeIsRequestInvalid(): void
    {
        $vcs = new VersionControlConnector();

        $result = $vcs->coordinate(['connector_id' => 'x']);

        $this->assertSame('Request Invalid', $result['status']);
    }

    public function testUnregisteredConnectorIsConnectorBlocked(): void
    {
        $vcs = new VersionControlConnector(new SqliteConnectorManager($this->tempPath('empty')));

        $result = $vcs->coordinate(['connector_id' => 'ghost', 'handoff_type' => 'Clone']);

        $this->assertSame('Connector Blocked', $result['status']);
        $this->assertStringContainsString('not registered', $result['error']);
    }

    public function testUnroutableConnectorIsConnectorBlocked(): void
    {
        $connectors = $this->connectorManager();
        $registered = $connectors->register([
            'connector_name' => 'GitHub Connector',
            'version' => '1.0.0',
            'owner_ref' => 'team_integrations',
            'endpoint_ref' => 'endpoint_ref_1',
            'protocol_ref' => 'REST',
            'configuration_ref' => 'config_ref_1',
            'credential_ref' => 'credential_ref_1',
            'governance_ref' => 'governance_ref_1',
        ]);
        $vcs = new VersionControlConnector($connectors);

        $result = $vcs->coordinate(['connector_id' => $registered['connector_id'], 'handoff_type' => 'Clone']);

        $this->assertSame('Connector Blocked', $result['status']);
        $this->assertStringContainsString('not active or degraded', $result['error']);
    }

    public function testCredentialRequiredWithoutAuthenticationComponentIsCredentialBlocked(): void
    {
        $connectors = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectors);
        $vcs = new VersionControlConnector($connectors);

        $result = $vcs->coordinate(['connector_id' => $connectorId, 'handoff_type' => 'Push', 'credential_ref' => 'cred_1']);

        $this->assertSame('Credential Blocked', $result['status']);
        $this->assertStringContainsString('IntegrationAuthentication', $result['error']);
    }

    public function testFailedHandshakeIsCredentialBlocked(): void
    {
        $connectors = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectors);
        $vcs = new VersionControlConnector($connectors, new IntegrationAuthentication());

        $result = $vcs->coordinate(
            ['connector_id' => $connectorId, 'handoff_type' => 'Push', 'credential_ref' => 'cred_1'],
            signHandshake: static fn(array $refs): array => ['token' => null, 'expires_at' => null, 'error' => 'invalid_client']
        );

        $this->assertSame('Credential Blocked', $result['status']);
        $this->assertSame('invalid_client', $result['error']);
    }

    public function testCoordinateWithoutAHandoffIsADryRun(): void
    {
        $connectors = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectors);
        $vcs = new VersionControlConnector($connectors);

        $result = $vcs->coordinate(['connector_id' => $connectorId, 'handoff_type' => 'Clone']);

        $this->assertSame('Ready', $result['status']);
        $this->assertNull($result['response']);
        $this->assertNull($result['error']);
    }

    public function testSuccessfulHandoffIsCompletedWithNormalizedReferences(): void
    {
        $connectors = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectors);
        $vcs = new VersionControlConnector($connectors);

        $result = $vcs->coordinate(
            ['connector_id' => $connectorId, 'handoff_type' => 'Commit'],
            handoff: static fn(array $c, array $r): array => ['response' => ['sha' => 'abc123'], 'commit_ref' => 'abc123']
        );

        $this->assertSame('Completed', $result['status']);
        $this->assertSame('abc123', $result['commit_ref']);
        $this->assertSame(['sha' => 'abc123'], $result['response']);
    }

    public function testHandoffReturningAllReferenceKindsAreNormalized(): void
    {
        $connectors = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectors);
        $vcs = new VersionControlConnector($connectors);

        $result = $vcs->coordinate(
            ['connector_id' => $connectorId, 'handoff_type' => 'Pull Request'],
            handoff: static fn(array $c, array $r): array => [
                'commit_ref' => 'abc123',
                'branch_ref' => 'feature/x',
                'tag_ref' => 'v1.0.0',
                'pull_request_ref' => 'pr_42',
            ]
        );

        $this->assertSame('Completed', $result['status']);
        $this->assertSame('feature/x', $result['branch_ref']);
        $this->assertSame('v1.0.0', $result['tag_ref']);
        $this->assertSame('pr_42', $result['pull_request_ref']);
    }

    public function testHandoffReturningAnErrorIsFailed(): void
    {
        $connectors = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectors);
        $vcs = new VersionControlConnector($connectors);

        $result = $vcs->coordinate(
            ['connector_id' => $connectorId, 'handoff_type' => 'Push'],
            handoff: static fn(array $c, array $r): array => ['error' => 'remote rejected: non-fast-forward']
        );

        $this->assertSame('Failed', $result['status']);
        $this->assertSame('remote rejected: non-fast-forward', $result['error']);
    }

    public function testHandoffThrowingIsFailedNotAnUncaughtException(): void
    {
        $connectors = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectors);
        $vcs = new VersionControlConnector($connectors);

        $result = $vcs->coordinate(
            ['connector_id' => $connectorId, 'handoff_type' => 'Clone'],
            handoff: static function (array $c, array $r): array {
                throw new RuntimeException('repository unreachable');
            }
        );

        $this->assertSame('Failed', $result['status']);
        $this->assertSame('repository unreachable', $result['error']);
    }

    public function testHandoffReceivesTheResolvedConnectorAndRequest(): void
    {
        $connectors = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectors);
        $vcs = new VersionControlConnector($connectors);
        $seenConnector = null;
        $seenRequest = null;

        $vcs->coordinate(
            ['connector_id' => $connectorId, 'handoff_type' => 'Branch', 'payload' => ['name' => 'feature/x']],
            handoff: function (array $c, array $r) use (&$seenConnector, &$seenRequest): array {
                $seenConnector = $c;
                $seenRequest = $r;

                return ['branch_ref' => 'feature/x'];
            }
        );

        $this->assertSame($connectorId, $seenConnector['connector_id']);
        $this->assertSame(['name' => 'feature/x'], $seenRequest['payload']);
    }

    public function testEventPayloadNeverExposesPayloadOrResponse(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('version_control_connector.completed', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $connectors = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectors);
        $vcs = new VersionControlConnector($connectors, null, $events);

        $vcs->coordinate(
            ['connector_id' => $connectorId, 'handoff_type' => 'Commit', 'payload' => ['secret_field' => 'super-secret-value']],
            handoff: static fn(array $c, array $r): array => ['response' => 'super-secret-response', 'commit_ref' => 'abc123']
        );

        $this->assertCount(1, $captured);
        $encoded = json_encode($captured[0]->getPayload(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('super-secret-value', $encoded);
        $this->assertStringNotContainsString('super-secret-response', $encoded);
    }

    public function testWorksWithoutAConnectorManager(): void
    {
        $vcs = new VersionControlConnector();

        $result = $vcs->coordinate(['connector_id' => 'x', 'handoff_type' => 'Clone']);

        $this->assertSame('Connector Blocked', $result['status']);
    }

    public function testWorksWithoutAnEventBus(): void
    {
        $connectors = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectors);
        $vcs = new VersionControlConnector($connectors, null, null);

        $result = $vcs->coordinate(['connector_id' => $connectorId, 'handoff_type' => 'Clone']);

        $this->assertSame('Ready', $result['status']);
    }
}
