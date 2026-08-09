<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Communication;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SquirrelForge\Automation\RuleEngine;
use SquirrelForge\Communication\SqliteRequestRouter;
use SquirrelForge\Governance\SqlitePolicyEngine;
use SquirrelForge\Integration\ApiGateway;
use SquirrelForge\Integration\IntegrationAuthentication;
use SquirrelForge\Integration\IntegrationMonitor;
use SquirrelForge\Integration\SqliteConnectorManager;
use SquirrelForge\Integration\SqliteIntegrationGovernance;
use SquirrelForge\Resilience\RetryManager;

final class SqliteRequestRouterTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-request-router-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function connectorManager(): SqliteConnectorManager
    {
        return new SqliteConnectorManager($this->tempPath('connectors'));
    }

    /**
     * Drives a connector through the real Connector Manager lifecycle
     * to `active`, the one status this class treats as routable.
     */
    private function activeConnectorId(SqliteConnectorManager $connectorManager): string
    {
        $registered = $connectorManager->register([
            'connector_name' => 'stripe_connector', 'version' => '1.0.0', 'owner_ref' => 'integrations_team',
            'endpoint_ref' => 'endpoint_1', 'protocol_ref' => 'https', 'configuration_ref' => 'config_1',
            'credential_ref' => 'credential_1', 'governance_ref' => 'governance_1',
        ]);
        $connectorManager->checkReadiness($registered['connector_id']);
        $connectorManager->activate($registered['connector_id']);

        return $registered['connector_id'];
    }

    private function governanceThatApproves(): SqliteIntegrationGovernance
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('p1', ['category' => 'integration', 'priority' => 1, 'condition' => ['type' => 'boolean', 'field' => 'ready', 'equals' => true], 'effect' => 'allow']);

        return new SqliteIntegrationGovernance($this->tempPath('governance'), $policyEngine);
    }

    private function governanceThatDenies(): SqliteIntegrationGovernance
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('p1', ['category' => 'integration', 'priority' => 1, 'condition' => ['type' => 'boolean', 'field' => 'ready', 'equals' => true], 'effect' => 'deny']);

        return new SqliteIntegrationGovernance($this->tempPath('governance'), $policyEngine);
    }

    /**
     * @return array<string, mixed>
     */
    private function governanceRequest(): array
    {
        return [
            'requesting_component' => 'orchestrator',
            'external_service_ref' => 'stripe',
            'policy_context' => ['ready' => true],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function requestFor(array $overrides = []): array
    {
        return array_replace([
            'request_id' => 'req_1',
            'target_service' => 'stripe',
            'routing_strategy' => 'Direct routing',
        ], $overrides);
    }

    // --- shape validation ---

    public function testMissingRequestIdIsInvalid(): void
    {
        $router = new SqliteRequestRouter($this->tempPath('db'));

        $result = $router->route($this->requestFor(['request_id' => '']));

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testUnrecognizedRoutingStrategyIsInvalid(): void
    {
        $router = new SqliteRequestRouter($this->tempPath('db'));

        $result = $router->route($this->requestFor(['routing_strategy' => 'Vibes-based routing']));

        $this->assertSame('invalid', $result['outcome']);
    }

    // --- governance ---

    public function testGovernanceRequestWithoutGovernanceComposedFails(): void
    {
        $router = new SqliteRequestRouter($this->tempPath('db'));

        $result = $router->route($this->requestFor(['governance_request' => $this->governanceRequest()]));

        $this->assertSame('resolved', $result['outcome']);
        $this->assertSame('Failed', $result['state']);
    }

    public function testGovernanceDenialFailsRouting(): void
    {
        $router = new SqliteRequestRouter($this->tempPath('db'), null, $this->governanceThatDenies());

        $result = $router->route($this->requestFor(['governance_request' => $this->governanceRequest()]));

        $this->assertSame('Failed', $result['state']);
        $this->assertStringContainsString('Governance did not approve', $result['error']);
    }

    public function testGovernanceApprovalProceeds(): void
    {
        $router = new SqliteRequestRouter($this->tempPath('db'), null, $this->governanceThatApproves());

        $result = $router->route($this->requestFor(['governance_request' => $this->governanceRequest()]));

        // No connector/gateway declared -- proceeds all the way to a dry-run Validated.
        $this->assertSame('Validated', $result['state']);
    }

    // --- authentication ---

    public function testAuthProviderWithoutAuthenticationComposedFails(): void
    {
        $router = new SqliteRequestRouter($this->tempPath('db'));

        $result = $router->route($this->requestFor(['auth_provider_ref' => 'stripe_provider']));

        $this->assertSame('Failed', $result['state']);
    }

    public function testAuthMissingCredentialReferenceIsBlockedAndFails(): void
    {
        $router = new SqliteRequestRouter($this->tempPath('db'), null, null, new IntegrationAuthentication());

        $result = $router->route($this->requestFor(['auth_provider_ref' => 'stripe_provider', 'auth_references' => []]));

        $this->assertSame('Failed', $result['state']);
        $this->assertStringContainsString('not ready', $result['error']);
    }

    public function testAuthReadyProceeds(): void
    {
        $router = new SqliteRequestRouter($this->tempPath('db'), null, null, new IntegrationAuthentication());

        $result = $router->route($this->requestFor(['auth_provider_ref' => 'stripe_provider', 'auth_references' => ['credential_ref' => 'cred_1']]));

        $this->assertSame('Validated', $result['state']);
    }

    // --- connector routability ---

    public function testConnectorIdWithoutConnectorManagerComposedFails(): void
    {
        $router = new SqliteRequestRouter($this->tempPath('db'));

        $result = $router->route($this->requestFor(['connector_id' => 'connector_1']));

        $this->assertSame('Failed', $result['state']);
    }

    public function testUnknownConnectorFails(): void
    {
        $router = new SqliteRequestRouter($this->tempPath('db'), $this->connectorManager());

        $result = $router->route($this->requestFor(['connector_id' => 'ghost_connector']));

        $this->assertSame('Failed', $result['state']);
    }

    public function testNotYetRoutableConnectorIsDeferred(): void
    {
        $connectorManager = $this->connectorManager();
        $registered = $connectorManager->register([
            'connector_name' => 'stripe_connector', 'version' => '1.0.0', 'owner_ref' => 'integrations_team',
        ]);
        $router = new SqliteRequestRouter($this->tempPath('db'), $connectorManager);

        $result = $router->route($this->requestFor(['connector_id' => $registered['connector_id']]));

        $this->assertSame('Deferred', $result['state']);
    }

    public function testActiveConnectorProceeds(): void
    {
        $connectorManager = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectorManager);
        $router = new SqliteRequestRouter($this->tempPath('db'), $connectorManager);

        $result = $router->route($this->requestFor(['connector_id' => $connectorId]));

        $this->assertSame('Validated', $result['state']);
    }

    // --- health via IntegrationMonitor ---

    public function testUnavailableHealthFindingFailsRouting(): void
    {
        $connectorManager = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectorManager);
        $router = new SqliteRequestRouter($this->tempPath('db'), $connectorManager, null, null, new IntegrationMonitor());

        $result = $router->route($this->requestFor(['connector_id' => $connectorId, 'health_signals' => ['availability' => 'unavailable']]));

        $this->assertSame('Failed', $result['state']);
    }

    public function testDegradedHealthFindingDefersRouting(): void
    {
        $connectorManager = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectorManager);
        $router = new SqliteRequestRouter($this->tempPath('db'), $connectorManager, null, null, new IntegrationMonitor());

        $result = $router->route($this->requestFor(['connector_id' => $connectorId, 'health_signals' => ['latency_ms' => 5000, 'latency_threshold_ms' => 1000]]));

        $this->assertSame('Deferred', $result['state']);
    }

    public function testHealthyFindingProceeds(): void
    {
        $connectorManager = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectorManager);
        $router = new SqliteRequestRouter($this->tempPath('db'), $connectorManager, null, null, new IntegrationMonitor());

        $result = $router->route($this->requestFor(['connector_id' => $connectorId, 'health_signals' => ['availability' => 'available']]));

        $this->assertSame('Validated', $result['state']);
    }

    // --- forwarding: connector handoff ---

    public function testSuccessfulConnectorHandoffRoutes(): void
    {
        $connectorManager = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectorManager);
        $router = new SqliteRequestRouter($this->tempPath('db'), $connectorManager);

        $result = $router->route($this->requestFor(['connector_id' => $connectorId]), fn(): array => ['ok' => true]);

        $this->assertSame('Routed', $result['state']);
    }

    public function testFailingConnectorHandoffFails(): void
    {
        $connectorManager = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectorManager);
        $router = new SqliteRequestRouter($this->tempPath('db'), $connectorManager);

        $result = $router->route(
            $this->requestFor(['connector_id' => $connectorId]),
            function (): void { throw new RuntimeException('external service rejected the call'); }
        );

        $this->assertSame('Failed', $result['state']);
        $this->assertStringContainsString('external service rejected the call', $result['error']);
    }

    // --- forwarding: real ApiGateway composition, failure not silently treated as success ---

    public function testApiGatewayDryRunWithNoTransportIsStillRouted(): void
    {
        $router = new SqliteRequestRouter($this->tempPath('db'), null, null, null, null, new ApiGateway());

        $result = $router->route($this->requestFor(['gateway_request' => ['endpoint_ref' => 'https://api.stripe.com', 'method' => 'GET']]));

        $this->assertSame('Routed', $result['state']);
    }

    public function testApiGatewayShapeErrorIsNotSilentlyRouted(): void
    {
        $router = new SqliteRequestRouter($this->tempPath('db'), null, null, null, null, new ApiGateway());

        // Missing endpoint_ref -- a real ApiGateway "Request Invalid" result, never thrown, must not be treated as success.
        $result = $router->route($this->requestFor(['gateway_request' => ['method' => 'GET']]));

        $this->assertSame('Failed', $result['state']);
    }

    // --- forwarding: real RetryManager composition ---

    public function testRetryManagerEventuallySucceeds(): void
    {
        $connectorManager = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectorManager);
        $router = new SqliteRequestRouter($this->tempPath('db'), $connectorManager, null, null, null, null, new RetryManager(fn() => null));

        $attempts = 0;
        $handoff = function () use (&$attempts): array {
            $attempts++;

            if ($attempts < 2) {
                throw new RuntimeException('transient failure');
            }

            return ['ok' => true];
        };

        $result = $router->route($this->requestFor(['connector_id' => $connectorId, 'retry_policy' => ['max_attempts' => 3]]), $handoff);

        $this->assertSame('Routed', $result['state']);
        $this->assertSame(2, $attempts);
    }

    public function testRetryManagerExhaustionFails(): void
    {
        $connectorManager = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectorManager);
        $router = new SqliteRequestRouter($this->tempPath('db'), $connectorManager, null, null, null, null, new RetryManager(fn() => null));

        $result = $router->route(
            $this->requestFor(['connector_id' => $connectorId, 'retry_policy' => ['max_attempts' => 2]]),
            function (): void { throw new RuntimeException('persistent failure'); }
        );

        $this->assertSame('Failed', $result['state']);
    }

    // --- no forward mechanism: honest Validated dry run ---

    public function testNoForwardMechanismResolvesValidated(): void
    {
        $router = new SqliteRequestRouter($this->tempPath('db'));

        $result = $router->route($this->requestFor());

        $this->assertSame('Validated', $result['state']);
    }

    // --- complete() ---

    public function testCompleteOnUnknownRoutingIsInvalid(): void
    {
        $router = new SqliteRequestRouter($this->tempPath('db'));

        $result = $router->complete('ghost');

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testCompleteOnANonRoutedDecisionIsRejected(): void
    {
        $router = new SqliteRequestRouter($this->tempPath('db'));
        $resolved = $router->route($this->requestFor());

        $result = $router->complete($resolved['routing_id']);

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testCompleteOnARoutedDecisionSucceeds(): void
    {
        $connectorManager = $this->connectorManager();
        $connectorId = $this->activeConnectorId($connectorManager);
        $router = new SqliteRequestRouter($this->tempPath('db'), $connectorManager);
        $resolved = $router->route($this->requestFor(['connector_id' => $connectorId]), fn(): array => ['ok' => true]);

        $result = $router->complete($resolved['routing_id']);

        $this->assertSame('completed', $result['outcome']);
        $this->assertSame('Completed', $result['state']);
    }

    // --- get() / history() ---

    public function testGetUnknownRoutingReturnsNull(): void
    {
        $router = new SqliteRequestRouter($this->tempPath('db'));

        $this->assertNull($router->get('ghost'));
    }

    public function testHistoryPreservesEveryRoutingDecisionForARequest(): void
    {
        $router = new SqliteRequestRouter($this->tempPath('db'));
        $router->route($this->requestFor(['request_id' => 'req_1']));
        $router->route($this->requestFor(['request_id' => 'req_1', 'target_service' => 'twilio']));
        $router->route($this->requestFor(['request_id' => 'req_other']));

        $history = $router->history('req_1');

        $this->assertCount(2, $history);
    }
}
