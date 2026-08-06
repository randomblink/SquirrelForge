<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Integration\SqliteConnectorManager;

final class SqliteConnectorManagerTest extends TestCase
{
    private string $dbPath;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/squirrelforge-connector-manager-' . bin2hex(random_bytes(8)) . '.sqlite';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    private function fullDefinition(array $overrides = []): array
    {
        return array_merge([
            'connector_name' => 'GitHub Connector',
            'version' => '1.0.0',
            'owner_ref' => 'team_integrations',
            'provider_ref' => 'github',
            'endpoint_ref' => 'endpoint_ref_1',
            'protocol_ref' => 'REST',
            'configuration_ref' => 'config_ref_1',
            'credential_ref' => 'credential_ref_1',
            'governance_ref' => 'governance_ref_1',
            'capability_metadata' => ['create_issue', 'list_repos'],
        ], $overrides);
    }

    public function testRegisterRejectsMissingRequiredFields(): void
    {
        $manager = new SqliteConnectorManager($this->dbPath);

        $result = $manager->register(['connector_name' => '', 'version' => '1.0.0', 'owner_ref' => 'team']);

        $this->assertSame('invalid', $result['outcome']);
        $this->assertNull($result['connector_id']);
    }

    public function testRegisterRejectsDuplicateCapabilityOperations(): void
    {
        $manager = new SqliteConnectorManager($this->dbPath);

        $result = $manager->register($this->fullDefinition(['capability_metadata' => ['a', 'a']]));

        $this->assertSame('invalid_capability_metadata', $result['outcome']);
    }

    public function testRegisterRejectsBlankCapabilityOperationNames(): void
    {
        $manager = new SqliteConnectorManager($this->dbPath);

        $result = $manager->register($this->fullDefinition(['capability_metadata' => ['a', '  ']]));

        $this->assertSame('invalid_capability_metadata', $result['outcome']);
    }

    public function testRegisterStartsAtRegisteredStatus(): void
    {
        $manager = new SqliteConnectorManager($this->dbPath);

        $result = $manager->register($this->fullDefinition());

        $this->assertSame('registered', $result['outcome']);
        $this->assertSame('registered', $result['lifecycle_status']);
        $this->assertNotNull($result['connector_id']);
    }

    public function testCheckReadinessOnFullyReferencedConnectorReturnsReady(): void
    {
        $manager = new SqliteConnectorManager($this->dbPath);
        $registered = $manager->register($this->fullDefinition());

        $result = $manager->checkReadiness($registered['connector_id']);

        $this->assertSame('checked', $result['outcome']);
        $this->assertSame('ready', $result['lifecycle_status']);
        $this->assertSame([], $result['missing_references']);
    }

    public function testCheckReadinessOnPartiallyReferencedConnectorReturnsReadinessPending(): void
    {
        $manager = new SqliteConnectorManager($this->dbPath);
        $registered = $manager->register($this->fullDefinition(['governance_ref' => null, 'credential_ref' => null]));

        $result = $manager->checkReadiness($registered['connector_id']);

        $this->assertSame('readiness_pending', $result['lifecycle_status']);
        $this->assertContains('governance_ref', $result['missing_references']);
        $this->assertContains('credential_ref', $result['missing_references']);
    }

    public function testCheckReadinessPersistsTheResolvedStatus(): void
    {
        $manager = new SqliteConnectorManager($this->dbPath);
        $registered = $manager->register($this->fullDefinition());
        $manager->checkReadiness($registered['connector_id']);

        $record = $manager->get($registered['connector_id']);

        $this->assertSame('ready', $record['lifecycle_status']);
    }

    public function testCheckReadinessOnUnknownConnectorReturnsNotFound(): void
    {
        $manager = new SqliteConnectorManager($this->dbPath);

        $result = $manager->checkReadiness('connector_does_not_exist');

        $this->assertSame('not_found', $result['outcome']);
    }

    public function testCheckReadinessAfterActivationIsNotApplicable(): void
    {
        $manager = new SqliteConnectorManager($this->dbPath);
        $registered = $manager->register($this->fullDefinition());
        $manager->checkReadiness($registered['connector_id']);
        $manager->activate($registered['connector_id']);

        $result = $manager->checkReadiness($registered['connector_id']);

        $this->assertSame('not_applicable', $result['outcome']);
    }

    public function testUpdateReferencesPatchesFieldsAndCanFlipReadiness(): void
    {
        $manager = new SqliteConnectorManager($this->dbPath);
        $registered = $manager->register($this->fullDefinition(['credential_ref' => null]));
        $pending = $manager->checkReadiness($registered['connector_id']);
        $this->assertSame('readiness_pending', $pending['lifecycle_status']);

        $manager->updateReferences($registered['connector_id'], ['credential_ref' => 'credential_ref_now_set']);
        $result = $manager->checkReadiness($registered['connector_id']);

        $this->assertSame('ready', $result['lifecycle_status']);
    }

    public function testUpdateReferencesOnUnknownConnectorReturnsNotFound(): void
    {
        $manager = new SqliteConnectorManager($this->dbPath);

        $result = $manager->updateReferences('connector_does_not_exist', ['endpoint_ref' => 'x']);

        $this->assertSame('not_found', $result['outcome']);
    }

    public function testActivateRequiresReadyStatusFirst(): void
    {
        $manager = new SqliteConnectorManager($this->dbPath);
        $registered = $manager->register($this->fullDefinition());

        $result = $manager->activate($registered['connector_id']);

        $this->assertSame('not_ready', $result['outcome']);
    }

    public function testActivateRequiresAGovernanceReference(): void
    {
        // Reaching `ready` already requires a governance_ref (it's one of
        // the required readiness references), so the only way to exercise
        // activate()'s own guard is a stale `ready` status after the
        // reference was cleared without re-checking readiness -- a real
        // scenario updateReferences() can produce, not a contrived one.
        $manager = new SqliteConnectorManager($this->dbPath);
        $registered = $manager->register($this->fullDefinition());
        $manager->checkReadiness($registered['connector_id']);
        $manager->updateReferences($registered['connector_id'], ['governance_ref' => null]);

        $result = $manager->activate($registered['connector_id']);

        $this->assertSame('missing_governance_reference', $result['outcome']);
    }

    public function testActivateSucceedsFromReady(): void
    {
        $manager = new SqliteConnectorManager($this->dbPath);
        $registered = $manager->register($this->fullDefinition());
        $manager->checkReadiness($registered['connector_id']);

        $result = $manager->activate($registered['connector_id']);

        $this->assertSame('activated', $result['outcome']);
        $this->assertSame('active', $result['lifecycle_status']);
    }

    public function testDeactivateRequiresActiveOrDegraded(): void
    {
        $manager = new SqliteConnectorManager($this->dbPath);
        $registered = $manager->register($this->fullDefinition());

        $result = $manager->deactivate($registered['connector_id'], 'maintenance');

        $this->assertSame('not_active', $result['outcome']);
    }

    public function testDeactivateFromActiveMovesToSuspended(): void
    {
        $manager = new SqliteConnectorManager($this->dbPath);
        $registered = $manager->register($this->fullDefinition());
        $manager->checkReadiness($registered['connector_id']);
        $manager->activate($registered['connector_id']);

        $result = $manager->deactivate($registered['connector_id'], 'maintenance');

        $this->assertSame('deactivated', $result['outcome']);
        $this->assertSame('suspended', $result['lifecycle_status']);
    }

    public function testActivateCanReactivateFromSuspended(): void
    {
        $manager = new SqliteConnectorManager($this->dbPath);
        $registered = $manager->register($this->fullDefinition());
        $manager->checkReadiness($registered['connector_id']);
        $manager->activate($registered['connector_id']);
        $manager->deactivate($registered['connector_id'], 'maintenance');

        $result = $manager->activate($registered['connector_id']);

        $this->assertSame('activated', $result['outcome']);
        $this->assertSame('active', $result['lifecycle_status']);
    }

    public function testRecordAvailabilityDegradesAnActiveConnector(): void
    {
        $manager = new SqliteConnectorManager($this->dbPath);
        $registered = $manager->register($this->fullDefinition());
        $manager->checkReadiness($registered['connector_id']);
        $manager->activate($registered['connector_id']);

        $result = $manager->recordAvailability($registered['connector_id'], 'availability_ref_1', true);

        $this->assertSame('degraded', $result['lifecycle_status']);
    }

    public function testRecordAvailabilityRecoversADegradedConnector(): void
    {
        $manager = new SqliteConnectorManager($this->dbPath);
        $registered = $manager->register($this->fullDefinition());
        $manager->checkReadiness($registered['connector_id']);
        $manager->activate($registered['connector_id']);
        $manager->recordAvailability($registered['connector_id'], 'availability_ref_1', true);

        $result = $manager->recordAvailability($registered['connector_id'], 'availability_ref_2', false);

        $this->assertSame('active', $result['lifecycle_status']);
    }

    public function testRecordAvailabilityOnANonActiveConnectorOnlyRecordsTheReference(): void
    {
        $manager = new SqliteConnectorManager($this->dbPath);
        $registered = $manager->register($this->fullDefinition());

        $result = $manager->recordAvailability($registered['connector_id'], 'availability_ref_1', true);

        $this->assertSame('registered', $result['lifecycle_status']);

        $record = $manager->get($registered['connector_id']);
        $this->assertSame('availability_ref_1', $record['availability_ref']);
    }

    public function testRetireIsTerminalFromAnyStatus(): void
    {
        $manager = new SqliteConnectorManager($this->dbPath);
        $registered = $manager->register($this->fullDefinition());

        $result = $manager->retire($registered['connector_id'], 'provider_deprecated');

        $this->assertSame('retired', $result['outcome']);
        $this->assertSame('retired', $result['lifecycle_status']);
    }

    public function testRetireIsIdempotent(): void
    {
        $manager = new SqliteConnectorManager($this->dbPath);
        $registered = $manager->register($this->fullDefinition());
        $manager->retire($registered['connector_id'], 'first');

        $result = $manager->retire($registered['connector_id'], 'second');

        $this->assertSame('already_retired', $result['outcome']);
    }

    public function testGetReturnsHydratedCapabilityMetadata(): void
    {
        $manager = new SqliteConnectorManager($this->dbPath);
        $registered = $manager->register($this->fullDefinition(['capability_metadata' => ['create_issue', 'list_repos']]));

        $record = $manager->get($registered['connector_id']);

        $this->assertSame(['create_issue', 'list_repos'], $record['capability_metadata']);
        $this->assertArrayNotHasKey('capability_metadata_json', $record);
    }

    public function testGetOnUnknownConnectorReturnsNull(): void
    {
        $manager = new SqliteConnectorManager($this->dbPath);

        $this->assertNull($manager->get('connector_does_not_exist'));
    }

    public function testFindByStatusFiltersToThatStatus(): void
    {
        $manager = new SqliteConnectorManager($this->dbPath);
        $active = $manager->register($this->fullDefinition(['connector_name' => 'Active One']));
        $manager->checkReadiness($active['connector_id']);
        $manager->activate($active['connector_id']);
        $manager->register($this->fullDefinition(['connector_name' => 'Still Registered']));

        $activeConnectors = $manager->findByStatus('active');
        $registeredConnectors = $manager->findByStatus('registered');

        $this->assertCount(1, $activeConnectors);
        $this->assertSame('Active One', $activeConnectors[0]['connector_name']);
        $this->assertCount(1, $registeredConnectors);
    }

    public function testFindByStatusRejectsAnUnrecognizedStatus(): void
    {
        $manager = new SqliteConnectorManager($this->dbPath);
        $manager->register($this->fullDefinition());

        $this->assertSame([], $manager->findByStatus('not_a_real_status'));
    }

    public function testCredentialReferenceIsNeverRawSecretMaterialJustAStoredString(): void
    {
        $manager = new SqliteConnectorManager($this->dbPath);
        $registered = $manager->register($this->fullDefinition(['credential_ref' => 'ref_only_never_raw']));

        $record = $manager->get($registered['connector_id']);

        $this->assertSame('ref_only_never_raw', $record['credential_ref']);
    }

    public function testLifecycleEventsAreEmittedThroughTheEventBus(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        foreach (['connector.registered', 'connector.ready', 'connector.active', 'connector.suspended', 'connector.retired'] as $name) {
            $events->listen($name, new CallbackEventListener(
                function (EventInterface $event) use (&$captured): void {
                    $captured[] = $event;
                }
            ));
        }

        $manager = new SqliteConnectorManager($this->dbPath, $events);
        $registered = $manager->register($this->fullDefinition());
        $manager->checkReadiness($registered['connector_id']);
        $manager->activate($registered['connector_id']);
        $manager->deactivate($registered['connector_id'], 'maintenance');
        $manager->retire($registered['connector_id'], 'done');

        $names = array_map(static fn(EventInterface $e): string => $e->getName(), $captured);
        $this->assertSame(
            ['connector.registered', 'connector.ready', 'connector.active', 'connector.suspended', 'connector.retired'],
            $names
        );
    }

    public function testWorksWithoutAnEventBus(): void
    {
        $manager = new SqliteConnectorManager($this->dbPath, null);

        $result = $manager->register($this->fullDefinition());

        $this->assertSame('registered', $result['outcome']);
    }
}
