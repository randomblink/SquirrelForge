<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Integration\SqliteServiceDiscovery;

final class SqliteServiceDiscoveryTest extends TestCase
{
    private string $dbPath;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/squirrelforge-service-discovery-' . bin2hex(random_bytes(8)) . '.sqlite';
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
            'service_name' => 'GitHub API',
            'provider_ref' => 'github',
            'endpoint_ref' => 'endpoint_ref_1',
            'protocol_metadata' => 'REST',
            'version_ref' => 'v3',
            'credential_requirement_ref' => 'api_key',
            'governance_ref' => 'governance_ref_1',
            'capability_metadata' => ['list_repos', 'create_issue'],
        ], $overrides);
    }

    public function testDiscoverRejectsMissingServiceName(): void
    {
        $discovery = new SqliteServiceDiscovery($this->dbPath);

        $result = $discovery->discover(['service_name' => '']);

        $this->assertSame('invalid', $result['outcome']);
        $this->assertNull($result['service_id']);
    }

    public function testDiscoverRejectsDuplicateCapabilityOperations(): void
    {
        $discovery = new SqliteServiceDiscovery($this->dbPath);

        $result = $discovery->discover($this->fullDefinition(['capability_metadata' => ['a', 'a']]));

        $this->assertSame('invalid_capability_metadata', $result['outcome']);
    }

    public function testDiscoverRejectsBlankCapabilityOperationNames(): void
    {
        $discovery = new SqliteServiceDiscovery($this->dbPath);

        $result = $discovery->discover($this->fullDefinition(['capability_metadata' => ['a', '  ']]));

        $this->assertSame('invalid_capability_metadata', $result['outcome']);
    }

    public function testDiscoverStartsAtDiscoveredStatus(): void
    {
        $discovery = new SqliteServiceDiscovery($this->dbPath);

        $result = $discovery->discover($this->fullDefinition());

        $this->assertSame('discovered', $result['outcome']);
        $this->assertSame('Discovered', $result['discovery_status']);
        $this->assertNotNull($result['service_id']);
    }

    public function testCheckReferencesOnFullyReferencedServiceReturnsVerified(): void
    {
        $discovery = new SqliteServiceDiscovery($this->dbPath);
        $discovered = $discovery->discover($this->fullDefinition());

        $result = $discovery->checkReferences($discovered['service_id']);

        $this->assertSame('checked', $result['outcome']);
        $this->assertSame('Verified', $result['discovery_status']);
        $this->assertSame([], $result['missing_references']);
    }

    public function testCheckReferencesOnPartiallyReferencedServiceReturnsReferencePending(): void
    {
        $discovery = new SqliteServiceDiscovery($this->dbPath);
        $discovered = $discovery->discover($this->fullDefinition(['governance_ref' => null, 'credential_requirement_ref' => null]));

        $result = $discovery->checkReferences($discovered['service_id']);

        $this->assertSame('Reference Pending', $result['discovery_status']);
        $this->assertContains('governance_ref', $result['missing_references']);
        $this->assertContains('credential_requirement_ref', $result['missing_references']);
    }

    public function testCheckReferencesIgnoresProtocolAndVersionMetadataForCompleteness(): void
    {
        $discovery = new SqliteServiceDiscovery($this->dbPath);
        $discovered = $discovery->discover($this->fullDefinition(['protocol_metadata' => null, 'version_ref' => null]));

        $result = $discovery->checkReferences($discovered['service_id']);

        $this->assertSame('Verified', $result['discovery_status']);
    }

    public function testCheckReferencesOnUnknownServiceReturnsNotFound(): void
    {
        $discovery = new SqliteServiceDiscovery($this->dbPath);

        $result = $discovery->checkReferences('service_does_not_exist');

        $this->assertSame('not_found', $result['outcome']);
    }

    public function testCheckReferencesAfterMarkingAvailableIsNotApplicable(): void
    {
        $discovery = new SqliteServiceDiscovery($this->dbPath);
        $discovered = $discovery->discover($this->fullDefinition());
        $discovery->checkReferences($discovered['service_id']);
        $discovery->markAvailable($discovered['service_id']);

        $result = $discovery->checkReferences($discovered['service_id']);

        $this->assertSame('not_applicable', $result['outcome']);
    }

    public function testUpdateReferencesPatchesFieldsAndCanFlipVerification(): void
    {
        $discovery = new SqliteServiceDiscovery($this->dbPath);
        $discovered = $discovery->discover($this->fullDefinition(['credential_requirement_ref' => null]));
        $pending = $discovery->checkReferences($discovered['service_id']);
        $this->assertSame('Reference Pending', $pending['discovery_status']);

        $discovery->updateReferences($discovered['service_id'], ['credential_requirement_ref' => 'api_key_now_set']);
        $result = $discovery->checkReferences($discovered['service_id']);

        $this->assertSame('Verified', $result['discovery_status']);
    }

    public function testUpdateReferencesOnUnknownServiceReturnsNotFound(): void
    {
        $discovery = new SqliteServiceDiscovery($this->dbPath);

        $result = $discovery->updateReferences('service_does_not_exist', ['endpoint_ref' => 'x']);

        $this->assertSame('not_found', $result['outcome']);
    }

    public function testMarkAvailableRequiresVerifiedFirst(): void
    {
        $discovery = new SqliteServiceDiscovery($this->dbPath);
        $discovered = $discovery->discover($this->fullDefinition());

        $result = $discovery->markAvailable($discovered['service_id']);

        $this->assertSame('not_verified', $result['outcome']);
    }

    public function testMarkAvailableRequiresAGovernanceReference(): void
    {
        // Reaching Verified already requires a governance_ref, so the
        // only way to exercise markAvailable()'s own guard is a stale
        // Verified status after the reference was cleared without
        // re-checking -- a real scenario updateReferences() can produce.
        $discovery = new SqliteServiceDiscovery($this->dbPath);
        $discovered = $discovery->discover($this->fullDefinition());
        $discovery->checkReferences($discovered['service_id']);
        $discovery->updateReferences($discovered['service_id'], ['governance_ref' => null]);

        $result = $discovery->markAvailable($discovered['service_id']);

        $this->assertSame('missing_governance_reference', $result['outcome']);
    }

    public function testMarkAvailableSucceedsFromVerified(): void
    {
        $discovery = new SqliteServiceDiscovery($this->dbPath);
        $discovered = $discovery->discover($this->fullDefinition());
        $discovery->checkReferences($discovered['service_id']);

        $result = $discovery->markAvailable($discovered['service_id']);

        $this->assertSame('available', $result['outcome']);
        $this->assertSame('Available', $result['discovery_status']);
    }

    public function testMarkAvailableCanRestoreFromUnavailable(): void
    {
        $discovery = new SqliteServiceDiscovery($this->dbPath);
        $discovered = $discovery->discover($this->fullDefinition());
        $discovery->checkReferences($discovered['service_id']);
        $discovery->markAvailable($discovered['service_id']);
        $discovery->recordAvailability($discovered['service_id'], 'availability_ref_1', 'Unavailable');

        $result = $discovery->markAvailable($discovered['service_id']);

        $this->assertSame('available', $result['outcome']);
    }

    public function testRecordAvailabilityRejectsAnUnrecognizedStatus(): void
    {
        $discovery = new SqliteServiceDiscovery($this->dbPath);
        $discovered = $discovery->discover($this->fullDefinition());

        $result = $discovery->recordAvailability($discovered['service_id'], 'ref_1', 'Thriving');

        $this->assertSame('invalid_availability_status', $result['outcome']);
    }

    public function testRecordAvailabilityTransitionsAnAvailableServiceToDegraded(): void
    {
        $discovery = new SqliteServiceDiscovery($this->dbPath);
        $discovered = $discovery->discover($this->fullDefinition());
        $discovery->checkReferences($discovered['service_id']);
        $discovery->markAvailable($discovered['service_id']);

        $result = $discovery->recordAvailability($discovered['service_id'], 'availability_ref_1', 'Degraded');

        $this->assertSame('Degraded', $result['discovery_status']);
    }

    public function testRecordAvailabilityRestoringToAvailableRequiresAGovernanceReference(): void
    {
        // Same governance-evidence invariant markAvailable() enforces,
        // exercised through the other path into Available: Degraded (or
        // Unavailable) with governance_ref cleared out from under it via
        // updateReferences() must not be restorable to Available through
        // recordAvailability() either.
        $discovery = new SqliteServiceDiscovery($this->dbPath);
        $discovered = $discovery->discover($this->fullDefinition());
        $discovery->checkReferences($discovered['service_id']);
        $discovery->markAvailable($discovered['service_id']);
        $discovery->recordAvailability($discovered['service_id'], 'availability_ref_1', 'Degraded');
        $discovery->updateReferences($discovered['service_id'], ['governance_ref' => null]);

        $result = $discovery->recordAvailability($discovered['service_id'], 'availability_ref_2', 'Available');

        $this->assertSame('missing_governance_reference', $result['outcome']);
        $this->assertSame('Degraded', $result['discovery_status']);
    }

    public function testRecordAvailabilityStillRecordsTheReferenceWhenGovernanceCheckBlocksTheTransition(): void
    {
        $discovery = new SqliteServiceDiscovery($this->dbPath);
        $discovered = $discovery->discover($this->fullDefinition());
        $discovery->checkReferences($discovered['service_id']);
        $discovery->markAvailable($discovered['service_id']);
        $discovery->recordAvailability($discovered['service_id'], 'availability_ref_1', 'Unavailable');
        $discovery->updateReferences($discovered['service_id'], ['governance_ref' => null]);
        $discovery->recordAvailability($discovered['service_id'], 'availability_ref_2', 'Available');

        $record = $discovery->get($discovered['service_id']);

        $this->assertSame('Unavailable', $record['discovery_status']);
        $this->assertSame('availability_ref_2', $record['availability_ref']);
    }

    public function testRecordAvailabilityRestoringToAvailableSucceedsWithAGovernanceReference(): void
    {
        $discovery = new SqliteServiceDiscovery($this->dbPath);
        $discovered = $discovery->discover($this->fullDefinition());
        $discovery->checkReferences($discovered['service_id']);
        $discovery->markAvailable($discovered['service_id']);
        $discovery->recordAvailability($discovered['service_id'], 'availability_ref_1', 'Unavailable');

        $result = $discovery->recordAvailability($discovered['service_id'], 'availability_ref_2', 'Available');

        $this->assertSame('recorded', $result['outcome']);
        $this->assertSame('Available', $result['discovery_status']);
    }

    public function testRecordAvailabilityOnANonAvailabilityTierServiceOnlyRecordsTheReference(): void
    {
        $discovery = new SqliteServiceDiscovery($this->dbPath);
        $discovered = $discovery->discover($this->fullDefinition());

        $result = $discovery->recordAvailability($discovered['service_id'], 'availability_ref_1', 'Degraded');

        $this->assertSame('Discovered', $result['discovery_status']);

        $record = $discovery->get($discovered['service_id']);
        $this->assertSame('availability_ref_1', $record['availability_ref']);
    }

    public function testDeprecateIsReachableBeforeEverBeingMarkedAvailable(): void
    {
        $discovery = new SqliteServiceDiscovery($this->dbPath);
        $discovered = $discovery->discover($this->fullDefinition());

        $result = $discovery->deprecate($discovered['service_id'], 'replaced_by_v2_api');

        $this->assertSame('deprecated', $result['outcome']);
        $this->assertSame('Deprecated', $result['discovery_status']);
    }

    public function testDeprecateIsRejectedOnceRetired(): void
    {
        $discovery = new SqliteServiceDiscovery($this->dbPath);
        $discovered = $discovery->discover($this->fullDefinition());
        $discovery->retire($discovered['service_id'], 'shut_down');

        $result = $discovery->deprecate($discovered['service_id'], 'too_late');

        $this->assertSame('already_retired', $result['outcome']);
    }

    public function testRetireIsTerminalFromAnyStatus(): void
    {
        $discovery = new SqliteServiceDiscovery($this->dbPath);
        $discovered = $discovery->discover($this->fullDefinition());

        $result = $discovery->retire($discovered['service_id'], 'provider_shut_down');

        $this->assertSame('retired', $result['outcome']);
        $this->assertSame('Retired', $result['discovery_status']);
    }

    public function testRetireIsIdempotent(): void
    {
        $discovery = new SqliteServiceDiscovery($this->dbPath);
        $discovered = $discovery->discover($this->fullDefinition());
        $discovery->retire($discovered['service_id'], 'first');

        $result = $discovery->retire($discovered['service_id'], 'second');

        $this->assertSame('already_retired', $result['outcome']);
    }

    public function testGetReturnsHydratedCapabilityMetadata(): void
    {
        $discovery = new SqliteServiceDiscovery($this->dbPath);
        $discovered = $discovery->discover($this->fullDefinition(['capability_metadata' => ['list_repos', 'create_issue']]));

        $record = $discovery->get($discovered['service_id']);

        $this->assertSame(['list_repos', 'create_issue'], $record['capability_metadata']);
        $this->assertArrayNotHasKey('capability_metadata_json', $record);
    }

    public function testGetOnUnknownServiceReturnsNull(): void
    {
        $discovery = new SqliteServiceDiscovery($this->dbPath);

        $this->assertNull($discovery->get('service_does_not_exist'));
    }

    public function testFindByStatusFiltersToThatStatus(): void
    {
        $discovery = new SqliteServiceDiscovery($this->dbPath);
        $available = $discovery->discover($this->fullDefinition(['service_name' => 'Available One']));
        $discovery->checkReferences($available['service_id']);
        $discovery->markAvailable($available['service_id']);
        $discovery->discover($this->fullDefinition(['service_name' => 'Still Discovered']));

        $availableServices = $discovery->findByStatus('Available');
        $discoveredServices = $discovery->findByStatus('Discovered');

        $this->assertCount(1, $availableServices);
        $this->assertSame('Available One', $availableServices[0]['service_name']);
        $this->assertCount(1, $discoveredServices);
    }

    public function testFindByStatusRejectsAnUnrecognizedStatus(): void
    {
        $discovery = new SqliteServiceDiscovery($this->dbPath);
        $discovery->discover($this->fullDefinition());

        $this->assertSame([], $discovery->findByStatus('not_a_real_status'));
    }

    public function testEndpointReferenceIsNeverRawSecretMaterialJustAStoredString(): void
    {
        $discovery = new SqliteServiceDiscovery($this->dbPath);
        $discovered = $discovery->discover($this->fullDefinition(['endpoint_ref' => 'ref_only_never_raw']));

        $record = $discovery->get($discovered['service_id']);

        $this->assertSame('ref_only_never_raw', $record['endpoint_ref']);
    }

    public function testLifecycleEventsAreEmittedThroughTheEventBus(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        foreach (['service_discovery.discovered', 'service_discovery.verified', 'service_discovery.available', 'service_discovery.deprecated', 'service_discovery.retired'] as $name) {
            $events->listen($name, new CallbackEventListener(
                function (EventInterface $event) use (&$captured): void {
                    $captured[] = $event;
                }
            ));
        }

        $discovery = new SqliteServiceDiscovery($this->dbPath, $events);
        $discovered = $discovery->discover($this->fullDefinition());
        $discovery->checkReferences($discovered['service_id']);
        $discovery->markAvailable($discovered['service_id']);
        $discovery->deprecate($discovered['service_id'], 'planned_sunset');
        $discovery->retire($discovered['service_id'], 'done');

        $names = array_map(static fn(EventInterface $e): string => $e->getName(), $captured);
        $this->assertSame(
            ['service_discovery.discovered', 'service_discovery.verified', 'service_discovery.available', 'service_discovery.deprecated', 'service_discovery.retired'],
            $names
        );
    }

    public function testWorksWithoutAnEventBus(): void
    {
        $discovery = new SqliteServiceDiscovery($this->dbPath, null);

        $result = $discovery->discover($this->fullDefinition());

        $this->assertSame('discovered', $result['outcome']);
    }
}
