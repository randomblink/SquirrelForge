<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Integration\AutomationConnector;
use SquirrelForge\Integration\IntegrationAuthentication;
use SquirrelForge\Integration\SqliteConnectorManager;

final class AutomationConnectorTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-automation-connector-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
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
            'connector_name' => 'CI Connector',
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

    public function testMissingConnectorIdOrTriggerRefIsFailed(): void
    {
        $automation = new AutomationConnector();

        $result = $automation->submit(['connector_id' => 'x']);

        $this->assertSame('Failed', $result['status']);
        $this->assertStringContainsString('trigger_ref', $result['error']);
    }

    public function testUnregisteredConnectorIsFailed(): void
    {
        $automation = new AutomationConnector(new SqliteConnectorManager($this->tempPath('empty')));

        $result = $automation->submit(['connector_id' => 'ghost', 'trigger_ref' => 'nightly_build']);

        $this->assertSame('Failed', $result['status']);
        $this->assertStringContainsString('not registered', $result['error']);
    }

    public function testUnroutableConnectorStatusIsFailed(): void
    {
        $connectors = $this->connectorManager();
        $registered = $connectors->register([
            'connector_name' => 'CI Connector',
            'version' => '1.0.0',
            'owner_ref' => 'team_integrations',
            'endpoint_ref' => 'endpoint_ref_1',
            'protocol_ref' => 'REST',
            'configuration_ref' => 'config_ref_1',
            'credential_ref' => 'credential_ref_1',
            'governance_ref' => 'governance_ref_1',
        ]);
        $automation = new AutomationConnector($connectors);

        $result = $automation->submit(['connector_id' => $registered['connector_id'], 'trigger_ref' => 'nightly_build']);

        $this->assertSame('Failed', $result['status']);
        $this->assertStringContainsString('not active or degraded', $result['error']);
    }

    public function testCredentialRequiredWithoutAuthenticationComponentIsFailed(): void
    {
        $connectors = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectors);
        $automation = new AutomationConnector($connectors);

        $result = $automation->submit(['connector_id' => $connectorId, 'trigger_ref' => 'nightly_build', 'credential_ref' => 'cred_1']);

        $this->assertSame('Failed', $result['status']);
        $this->assertStringContainsString('IntegrationAuthentication', $result['error']);
    }

    public function testFailedHandshakeIsFailed(): void
    {
        $connectors = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectors);
        $automation = new AutomationConnector($connectors, new IntegrationAuthentication());

        $result = $automation->submit(
            ['connector_id' => $connectorId, 'trigger_ref' => 'nightly_build', 'credential_ref' => 'cred_1'],
            signHandshake: static fn(array $refs): array => ['token' => null, 'expires_at' => null, 'error' => 'invalid_client']
        );

        $this->assertSame('Failed', $result['status']);
        $this->assertSame('invalid_client', $result['error']);
    }

    public function testSubmitWithoutATriggerIsADryRun(): void
    {
        $connectors = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectors);
        $automation = new AutomationConnector($connectors);

        $result = $automation->submit(['connector_id' => $connectorId, 'trigger_ref' => 'nightly_build']);

        $this->assertSame('Submitted', $result['status']);
        $this->assertNull($result['error']);
        $this->assertNull($result['response']);
    }

    public function testTriggerReportedStatusIsRespected(): void
    {
        $connectors = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectors);
        $automation = new AutomationConnector($connectors);

        $result = $automation->submit(
            ['connector_id' => $connectorId, 'trigger_ref' => 'nightly_build'],
            trigger: static fn(array $c, array $r): array => ['status' => 'Running', 'external_ref' => 'run_123']
        );

        $this->assertSame('Running', $result['status']);
        $this->assertSame('run_123', $result['external_ref']);
    }

    public function testUnrecognizedStatusWithErrorFallsBackToFailed(): void
    {
        $connectors = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectors);
        $automation = new AutomationConnector($connectors);

        $result = $automation->submit(
            ['connector_id' => $connectorId, 'trigger_ref' => 'nightly_build'],
            trigger: static fn(array $c, array $r): array => ['status' => 'made_up', 'error' => 'boom']
        );

        $this->assertSame('Failed', $result['status']);
    }

    public function testMissingStatusWithNoErrorFallsBackToAcceptedNotCompleted(): void
    {
        $connectors = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectors);
        $automation = new AutomationConnector($connectors);

        $result = $automation->submit(
            ['connector_id' => $connectorId, 'trigger_ref' => 'nightly_build'],
            trigger: static fn(array $c, array $r): array => ['external_ref' => 'run_123']
        );

        $this->assertSame('Accepted', $result['status']);
    }

    public function testTriggerThrowingIsFailedNotAnUncaughtException(): void
    {
        $connectors = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectors);
        $automation = new AutomationConnector($connectors);

        $result = $automation->submit(
            ['connector_id' => $connectorId, 'trigger_ref' => 'nightly_build'],
            trigger: static function (array $c, array $r): array {
                throw new RuntimeException('platform unreachable');
            }
        );

        $this->assertSame('Failed', $result['status']);
        $this->assertSame('platform unreachable', $result['error']);
    }

    public function testTriggerReceivesTheResolvedConnectorAndRequest(): void
    {
        $connectors = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectors);
        $automation = new AutomationConnector($connectors);
        $seenConnector = null;
        $seenRequest = null;

        $automation->submit(
            ['connector_id' => $connectorId, 'trigger_ref' => 'nightly_build', 'payload' => ['branch' => 'main']],
            trigger: function (array $c, array $r) use (&$seenConnector, &$seenRequest): array {
                $seenConnector = $c;
                $seenRequest = $r;

                return ['status' => 'Accepted'];
            }
        );

        $this->assertSame($connectorId, $seenConnector['connector_id']);
        $this->assertSame(['branch' => 'main'], $seenRequest['payload']);
    }

    public function testEventPayloadNeverExposesPayloadOrResponse(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        foreach (['automation_connector.submitted', 'automation_connector.completed'] as $eventName) {
            $events->listen($eventName, new CallbackEventListener(function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }));
        }

        $connectors = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectors);
        $automation = new AutomationConnector($connectors, null, $events);

        $automation->submit(
            ['connector_id' => $connectorId, 'trigger_ref' => 'nightly_build', 'payload' => ['secret_field' => 'super-secret-value']],
            trigger: static fn(array $c, array $r): array => ['status' => 'Completed', 'response' => 'super-secret-response']
        );

        $this->assertCount(2, $captured);
        foreach ($captured as $event) {
            $encoded = json_encode($event->getPayload(), JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString('super-secret-value', $encoded);
            $this->assertStringNotContainsString('super-secret-response', $encoded);
        }
    }

    public function testWorksWithoutAConnectorManager(): void
    {
        $automation = new AutomationConnector();

        $result = $automation->submit(['connector_id' => 'x', 'trigger_ref' => 'nightly_build']);

        $this->assertSame('Failed', $result['status']);
        $this->assertStringContainsString('not registered', $result['error']);
    }

    public function testWorksWithoutAnEventBus(): void
    {
        $connectors = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectors);
        $automation = new AutomationConnector($connectors, null, null);

        $result = $automation->submit(['connector_id' => $connectorId, 'trigger_ref' => 'nightly_build']);

        $this->assertSame('Submitted', $result['status']);
    }
}
