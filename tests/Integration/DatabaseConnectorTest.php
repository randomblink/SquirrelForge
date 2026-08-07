<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Integration\DatabaseConnector;
use SquirrelForge\Integration\IntegrationAuthentication;
use SquirrelForge\Integration\SqliteConnectorManager;

final class DatabaseConnectorTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-db-connector-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
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
            'connector_name' => 'Postgres Connector',
            'version' => '1.0.0',
            'owner_ref' => 'team_integrations',
            'endpoint_ref' => 'endpoint_ref_1',
            'protocol_ref' => 'SQL',
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
        $db = new DatabaseConnector();

        $result = $db->coordinate(['handoff_type' => 'Query']);

        $this->assertSame('Request Invalid', $result['status']);
    }

    public function testUnapprovedHandoffTypeIsRequestInvalid(): void
    {
        $db = new DatabaseConnector();

        $result = $db->coordinate(['connector_id' => 'x', 'handoff_type' => 'Drop']);

        $this->assertSame('Request Invalid', $result['status']);
        $this->assertStringContainsString('approved handoff types', $result['error']);
    }

    public function testUnregisteredConnectorIsConnectorBlocked(): void
    {
        $db = new DatabaseConnector(new SqliteConnectorManager($this->tempPath('empty')));

        $result = $db->coordinate(['connector_id' => 'ghost', 'handoff_type' => 'Query']);

        $this->assertSame('Connector Blocked', $result['status']);
        $this->assertStringContainsString('not registered', $result['error']);
    }

    public function testUnroutableConnectorIsConnectorBlocked(): void
    {
        $connectors = $this->connectorManager();
        $registered = $connectors->register([
            'connector_name' => 'Postgres Connector',
            'version' => '1.0.0',
            'owner_ref' => 'team_integrations',
            'endpoint_ref' => 'endpoint_ref_1',
            'protocol_ref' => 'SQL',
            'configuration_ref' => 'config_ref_1',
            'credential_ref' => 'credential_ref_1',
            'governance_ref' => 'governance_ref_1',
        ]);
        $db = new DatabaseConnector($connectors);

        $result = $db->coordinate(['connector_id' => $registered['connector_id'], 'handoff_type' => 'Query']);

        $this->assertSame('Connector Blocked', $result['status']);
        $this->assertStringContainsString('not active or degraded', $result['error']);
    }

    public function testCredentialRequiredWithoutAuthenticationComponentIsCredentialBlocked(): void
    {
        $connectors = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectors);
        $db = new DatabaseConnector($connectors);

        $result = $db->coordinate(['connector_id' => $connectorId, 'handoff_type' => 'Write', 'credential_ref' => 'cred_1']);

        $this->assertSame('Credential Blocked', $result['status']);
        $this->assertStringContainsString('IntegrationAuthentication', $result['error']);
    }

    public function testFailedHandshakeIsCredentialBlocked(): void
    {
        $connectors = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectors);
        $db = new DatabaseConnector($connectors, new IntegrationAuthentication());

        $result = $db->coordinate(
            ['connector_id' => $connectorId, 'handoff_type' => 'Write', 'credential_ref' => 'cred_1'],
            signHandshake: static fn(array $refs): array => ['token' => null, 'expires_at' => null, 'error' => 'invalid_client']
        );

        $this->assertSame('Credential Blocked', $result['status']);
        $this->assertSame('invalid_client', $result['error']);
    }

    public function testCoordinateWithoutAHandoffIsADryRun(): void
    {
        $connectors = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectors);
        $db = new DatabaseConnector($connectors);

        $result = $db->coordinate(['connector_id' => $connectorId, 'handoff_type' => 'Connect']);

        $this->assertSame('Ready', $result['status']);
        $this->assertNull($result['response']);
        $this->assertNull($result['error']);
    }

    public function testSuccessfulQueryIsCompletedWithResponse(): void
    {
        $connectors = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectors);
        $db = new DatabaseConnector($connectors);

        $result = $db->coordinate(
            ['connector_id' => $connectorId, 'handoff_type' => 'Query'],
            handoff: static fn(array $c, array $r): array => ['response' => [['id' => 1]], 'usage' => ['rows_returned' => 1]]
        );

        $this->assertSame('Completed', $result['status']);
        $this->assertSame([['id' => 1]], $result['response']);
        $this->assertSame(['rows_returned' => 1], $result['usage']);
    }

    public function testTransactionHandoffNormalizesTransactionStatus(): void
    {
        $connectors = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectors);
        $db = new DatabaseConnector($connectors);

        $result = $db->coordinate(
            ['connector_id' => $connectorId, 'handoff_type' => 'Transaction'],
            handoff: static fn(array $c, array $r): array => ['transaction_status' => 'committed']
        );

        $this->assertSame('Completed', $result['status']);
        $this->assertSame('committed', $result['transaction_status']);
    }

    public function testHandoffReturningAnErrorIsFailed(): void
    {
        $connectors = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectors);
        $db = new DatabaseConnector($connectors);

        $result = $db->coordinate(
            ['connector_id' => $connectorId, 'handoff_type' => 'Write'],
            handoff: static fn(array $c, array $r): array => ['error' => 'unique constraint violation']
        );

        $this->assertSame('Failed', $result['status']);
        $this->assertSame('unique constraint violation', $result['error']);
    }

    public function testHandoffThrowingIsFailedNotAnUncaughtException(): void
    {
        $connectors = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectors);
        $db = new DatabaseConnector($connectors);

        $result = $db->coordinate(
            ['connector_id' => $connectorId, 'handoff_type' => 'Query'],
            handoff: static function (array $c, array $r): array {
                throw new RuntimeException('connection reset');
            }
        );

        $this->assertSame('Failed', $result['status']);
        $this->assertSame('connection reset', $result['error']);
    }

    public function testHandoffReceivesTheResolvedConnectorAndRequest(): void
    {
        $connectors = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectors);
        $db = new DatabaseConnector($connectors);
        $seenConnector = null;
        $seenRequest = null;

        $db->coordinate(
            ['connector_id' => $connectorId, 'handoff_type' => 'Query', 'payload' => ['sql' => 'SELECT 1']],
            handoff: function (array $c, array $r) use (&$seenConnector, &$seenRequest): array {
                $seenConnector = $c;
                $seenRequest = $r;

                return ['response' => []];
            }
        );

        $this->assertSame($connectorId, $seenConnector['connector_id']);
        $this->assertSame(['sql' => 'SELECT 1'], $seenRequest['payload']);
    }

    public function testEventPayloadNeverExposesPayloadOrResponse(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('database_connector.completed', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $connectors = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectors);
        $db = new DatabaseConnector($connectors, null, $events);

        $db->coordinate(
            ['connector_id' => $connectorId, 'handoff_type' => 'Query', 'payload' => ['sql' => 'SELECT secret FROM tokens']],
            handoff: static fn(array $c, array $r): array => ['response' => 'super-secret-row']
        );

        $this->assertCount(1, $captured);
        $encoded = json_encode($captured[0]->getPayload(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('super-secret-row', $encoded);
        $this->assertStringNotContainsString('SELECT secret', $encoded);
    }

    public function testWorksWithoutAConnectorManager(): void
    {
        $db = new DatabaseConnector();

        $result = $db->coordinate(['connector_id' => 'x', 'handoff_type' => 'Query']);

        $this->assertSame('Connector Blocked', $result['status']);
    }

    public function testWorksWithoutAnEventBus(): void
    {
        $connectors = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectors);
        $db = new DatabaseConnector($connectors, null, null);

        $result = $db->coordinate(['connector_id' => $connectorId, 'handoff_type' => 'Connect']);

        $this->assertSame('Ready', $result['status']);
    }
}
