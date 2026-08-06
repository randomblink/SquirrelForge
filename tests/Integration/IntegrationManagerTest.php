<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SquirrelForge\Automation\RuleEngine;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Governance\SqlitePolicyEngine;
use SquirrelForge\Integration\IntegrationManager;
use SquirrelForge\Integration\SqliteConnectorManager;
use SquirrelForge\Integration\SqliteIntegrationGovernance;

final class IntegrationManagerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-integration-manager-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
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

    private function approvingGovernance(): SqliteIntegrationGovernance
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('p1', [
            'category' => 'integration', 'priority' => 1,
            'condition' => ['type' => 'boolean', 'field' => 'ready', 'equals' => true],
            'effect' => 'allow',
        ]);

        return new SqliteIntegrationGovernance($this->tempPath('gov'), $policyEngine);
    }

    private function denyingGovernance(): SqliteIntegrationGovernance
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('p1', [
            'category' => 'integration', 'priority' => 1,
            'condition' => ['type' => 'boolean', 'field' => 'ready', 'equals' => true],
            'effect' => 'deny',
        ]);

        return new SqliteIntegrationGovernance($this->tempPath('gov'), $policyEngine);
    }

    /**
     * @return array{requesting_component: string, connector_id: string, capability: string, governance_request: array<string, mixed>}
     */
    private function request(string $connectorId, array $overrides = []): array
    {
        return array_replace([
            'requesting_component' => 'workflow_owner_1',
            'connector_id' => $connectorId,
            'capability' => 'list_repos',
            'governance_request' => [
                'requesting_component' => 'workflow_owner_1',
                'external_service_ref' => 'github_api',
                'policy_context' => ['ready' => true],
            ],
        ], $overrides);
    }

    public function testMissingRequiredFieldsIsReadinessBlocked(): void
    {
        $manager = new IntegrationManager();

        $result = $manager->coordinate(['requesting_component' => 'x']);

        $this->assertSame('Readiness Blocked', $result['integration_status']);
        $this->assertNotNull($result['error']);
    }

    public function testUnknownConnectorIsReadinessBlocked(): void
    {
        $connectors = new SqliteConnectorManager($this->tempPath('conn'));
        $manager = new IntegrationManager($connectors);

        $result = $manager->coordinate($this->request('connector_does_not_exist'));

        $this->assertSame('Readiness Blocked', $result['integration_status']);
    }

    public function testConnectorThatIsNotYetActiveIsReadinessBlocked(): void
    {
        $connectors = new SqliteConnectorManager($this->tempPath('conn'));
        $registered = $connectors->register([
            'connector_name' => 'Unready Connector',
            'version' => '1.0.0',
            'owner_ref' => 'team_integrations',
        ]);
        $manager = new IntegrationManager($connectors);

        $result = $manager->coordinate($this->request($registered['connector_id']));

        $this->assertSame('Readiness Blocked', $result['integration_status']);
    }

    public function testNoConnectorManagerConfiguredIsReadinessBlocked(): void
    {
        $manager = new IntegrationManager();

        $result = $manager->coordinate($this->request('connector_1'));

        $this->assertSame('Readiness Blocked', $result['integration_status']);
    }

    public function testNoGovernanceDecisionAvailableFailsClosedToGovernanceBlocked(): void
    {
        $connectors = new SqliteConnectorManager($this->tempPath('conn'));
        $connectorId = $this->activeConnectorId($connectors);
        $manager = new IntegrationManager($connectors, null);

        $result = $manager->coordinate($this->request($connectorId));

        $this->assertSame('Governance Blocked', $result['integration_status']);
    }

    public function testDenyingGovernanceDecisionIsGovernanceBlocked(): void
    {
        $connectors = new SqliteConnectorManager($this->tempPath('conn'));
        $connectorId = $this->activeConnectorId($connectors);
        $manager = new IntegrationManager($connectors, $this->denyingGovernance());

        $result = $manager->coordinate($this->request($connectorId));

        $this->assertSame('Governance Blocked', $result['integration_status']);
        $this->assertSame('Rejected', $result['governance_decision']);
    }

    public function testApprovingGovernanceDecisionProceedsPastTheGovernanceGate(): void
    {
        $connectors = new SqliteConnectorManager($this->tempPath('conn'));
        $connectorId = $this->activeConnectorId($connectors);
        $manager = new IntegrationManager($connectors, $this->approvingGovernance());

        $result = $manager->coordinate($this->request($connectorId));

        $this->assertSame('Routed', $result['integration_status']);
        $this->assertSame('Approved', $result['governance_decision']);
    }

    public function testExplicitlyUnauthorizedCredentialIsCredentialBlocked(): void
    {
        $connectors = new SqliteConnectorManager($this->tempPath('conn'));
        $connectorId = $this->activeConnectorId($connectors);
        $manager = new IntegrationManager($connectors, $this->approvingGovernance());

        $result = $manager->coordinate($this->request($connectorId, ['credential_authorized' => false, 'credential_status' => 'expired_token']));

        $this->assertSame('Credential Blocked', $result['integration_status']);
        $this->assertSame('expired_token', $result['error']);
    }

    public function testCredentialAuthorizedDefaultsToTrueWhenOmitted(): void
    {
        $connectors = new SqliteConnectorManager($this->tempPath('conn'));
        $connectorId = $this->activeConnectorId($connectors);
        $manager = new IntegrationManager($connectors, $this->approvingGovernance());

        $result = $manager->coordinate($this->request($connectorId));

        $this->assertSame('Routed', $result['integration_status']);
    }

    public function testNoHandoffClosureTerminatesAtRoutedAsADryRun(): void
    {
        $connectors = new SqliteConnectorManager($this->tempPath('conn'));
        $connectorId = $this->activeConnectorId($connectors);
        $manager = new IntegrationManager($connectors, $this->approvingGovernance());

        $result = $manager->coordinate($this->request($connectorId), null);

        $this->assertSame('Routed', $result['integration_status']);
        $this->assertNull($result['response']);
        $this->assertNull($result['error']);
    }

    public function testSuccessfulHandoffReachesCompletedHandoffWithResponseReceivedStage(): void
    {
        $connectors = new SqliteConnectorManager($this->tempPath('conn'));
        $connectorId = $this->activeConnectorId($connectors);
        $manager = new IntegrationManager($connectors, $this->approvingGovernance());

        $result = $manager->coordinate(
            $this->request($connectorId),
            static fn(array $connector, array $request): array => ['repos' => ['repo_a', 'repo_b']]
        );

        $this->assertSame('Completed Handoff', $result['integration_status']);
        $this->assertSame(['repos' => ['repo_a', 'repo_b']], $result['response']);
        $this->assertNull($result['error']);
        $this->assertContains('Response Received', $result['stages']);
        $this->assertContains('Completed Handoff', $result['stages']);
    }

    public function testHandoffReceivesTheConnectorRecordAndRequest(): void
    {
        $connectors = new SqliteConnectorManager($this->tempPath('conn'));
        $connectorId = $this->activeConnectorId($connectors);
        $manager = new IntegrationManager($connectors, $this->approvingGovernance());
        $seenConnector = null;
        $seenRequest = null;

        $manager->coordinate(
            $this->request($connectorId),
            function (array $connector, array $request) use (&$seenConnector, &$seenRequest): array {
                $seenConnector = $connector;
                $seenRequest = $request;

                return ['ok' => true];
            }
        );

        $this->assertSame($connectorId, $seenConnector['connector_id']);
        $this->assertSame('list_repos', $seenRequest['capability']);
    }

    public function testHandoffReturningAnErrorReachesExternalFailureReportedThenCompletedHandoff(): void
    {
        $connectors = new SqliteConnectorManager($this->tempPath('conn'));
        $connectorId = $this->activeConnectorId($connectors);
        $manager = new IntegrationManager($connectors, $this->approvingGovernance());

        $result = $manager->coordinate(
            $this->request($connectorId),
            static fn(array $connector, array $request): array => ['error' => 'rate_limited']
        );

        $this->assertSame('Completed Handoff', $result['integration_status']);
        $this->assertSame('rate_limited', $result['error']);
        $this->assertNull($result['response']);
        $this->assertContains('External Failure Reported', $result['stages']);
        $this->assertNotContains('Recovery Requested', $result['stages']);
    }

    public function testHandoffThrowingIsTreatedAsAnExternalFailureNotAnUncaughtException(): void
    {
        $connectors = new SqliteConnectorManager($this->tempPath('conn'));
        $connectorId = $this->activeConnectorId($connectors);
        $manager = new IntegrationManager($connectors, $this->approvingGovernance());

        $result = $manager->coordinate(
            $this->request($connectorId),
            static function (array $connector, array $request): array {
                throw new RuntimeException('connector unreachable');
            }
        );

        $this->assertSame('Completed Handoff', $result['integration_status']);
        $this->assertSame('connector unreachable', $result['error']);
    }

    public function testExternalFailureRequestsRecoveryWhenARecoveryClosureIsSupplied(): void
    {
        $connectors = new SqliteConnectorManager($this->tempPath('conn'));
        $connectorId = $this->activeConnectorId($connectors);
        $manager = new IntegrationManager($connectors, $this->approvingGovernance());
        $recoveryCalledWith = null;

        $result = $manager->coordinate(
            $this->request($connectorId),
            static fn(array $connector, array $request): array => ['error' => 'timeout'],
            function (array $context) use (&$recoveryCalledWith): void {
                $recoveryCalledWith = $context;
            }
        );

        $this->assertContains('Recovery Requested', $result['stages']);
        $this->assertSame('timeout', $recoveryCalledWith['error']);
        $this->assertSame($connectorId, $recoveryCalledWith['connector_id']);
    }

    public function testRecoveryIsNeverRequestedOnSuccess(): void
    {
        $connectors = new SqliteConnectorManager($this->tempPath('conn'));
        $connectorId = $this->activeConnectorId($connectors);
        $manager = new IntegrationManager($connectors, $this->approvingGovernance());
        $recoveryCalled = false;

        $manager->coordinate(
            $this->request($connectorId),
            static fn(array $connector, array $request): array => ['ok' => true],
            function () use (&$recoveryCalled): void {
                $recoveryCalled = true;
            }
        );

        $this->assertFalse($recoveryCalled);
    }

    public function testDegradedConnectorsAreStillRoutable(): void
    {
        $connectors = new SqliteConnectorManager($this->tempPath('conn'));
        $connectorId = $this->activeConnectorId($connectors);
        $connectors->recordAvailability($connectorId, 'availability_ref_1', true);
        $manager = new IntegrationManager($connectors, $this->approvingGovernance());

        $result = $manager->coordinate($this->request($connectorId));

        $this->assertSame('Routed', $result['integration_status']);
    }

    public function testSuspendedConnectorsAreNotRoutable(): void
    {
        $connectors = new SqliteConnectorManager($this->tempPath('conn'));
        $connectorId = $this->activeConnectorId($connectors);
        $connectors->deactivate($connectorId, 'maintenance');
        $manager = new IntegrationManager($connectors, $this->approvingGovernance());

        $result = $manager->coordinate($this->request($connectorId));

        $this->assertSame('Readiness Blocked', $result['integration_status']);
    }

    public function testStagesRecordFullProgressionOnSuccess(): void
    {
        $connectors = new SqliteConnectorManager($this->tempPath('conn'));
        $connectorId = $this->activeConnectorId($connectors);
        $manager = new IntegrationManager($connectors, $this->approvingGovernance());

        $result = $manager->coordinate(
            $this->request($connectorId),
            static fn(array $connector, array $request): array => ['ok' => true]
        );

        $this->assertSame(['Received', 'Routed', 'Response Received', 'Completed Handoff'], $result['stages']);
    }

    public function testEmitsAnEventForEachStageTransition(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        foreach (['integration_manager.routed', 'integration_manager.response_received', 'integration_manager.completed_handoff'] as $name) {
            $events->listen($name, new CallbackEventListener(
                function (EventInterface $event) use (&$captured): void {
                    $captured[] = $event;
                }
            ));
        }

        $connectors = new SqliteConnectorManager($this->tempPath('conn'));
        $connectorId = $this->activeConnectorId($connectors);
        $manager = new IntegrationManager($connectors, $this->approvingGovernance(), $events);

        $manager->coordinate(
            $this->request($connectorId),
            static fn(array $connector, array $request): array => ['ok' => true]
        );

        $this->assertCount(3, $captured);
    }

    public function testWorksWithoutAnEventBus(): void
    {
        $connectors = new SqliteConnectorManager($this->tempPath('conn'));
        $connectorId = $this->activeConnectorId($connectors);
        $manager = new IntegrationManager($connectors, $this->approvingGovernance(), null);

        $result = $manager->coordinate($this->request($connectorId));

        $this->assertSame('Routed', $result['integration_status']);
    }
}
