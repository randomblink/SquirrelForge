<?php

declare(strict_types=1);

namespace SquirrelForge\Agent;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Engine\TaskRouter;

/**
 * The aggregation point for assigning work to an agent: confirms the
 * candidate is registered, specialized for the work, in an eligible
 * lifecycle state, and healthy enough to accept it, before handing the
 * actual routing decision to `14_ENGINE/TASK-ROUTER.md`, per
 * 16_AGENTS/AGENT-MANAGER.md -- the eighth and final real component in
 * 16_AGENTS's governance/coordination gap, closing the layer.
 *
 * "The Manager consults each of those authorities rather than owning
 * any of them itself" (Purpose) is genuine composition of five already-
 * real components -- `AgentRegistry::get()`, `SqliteAgentSpecialization::match()`,
 * `SqliteAgentLifecycle::currentState()`, `SqliteAgentMonitor::currentHealth()`,
 * `SqliteAgentDelegation::get()` -- plus a real handoff to `TaskRouter::route()`
 * for the actual capability-based selection this class never performs
 * itself. This closes the mutual `AGENT-MANAGER.md`/`AGENT-MONITOR.md`
 * reference pair from the other side: `SqliteAgentMonitor::currentHealth()`
 * is exactly the "leaf authority publishes, consumers read" accessor
 * that component's own docblock said this class would consume once it
 * existed.
 *
 * "No task may be assigned to an agent until it is confirmed
 * registered, specialized for the work, in an eligible lifecycle
 * state, and healthy enough to accept it" (Rule) is a real, fail-closed
 * conjunction of all four checks: an *unconfigured* Registry,
 * Specialization, Lifecycle Manager, or Monitor rejects the assignment
 * with a named "not configured" reason rather than silently skipping
 * the check it cannot perform -- the same stance `SqliteAgentGovernance`
 * already takes toward an unconfigured Policy Engine, since this
 * spec's Rule states all four confirmations as mandatory, not optional
 * secondary checks.
 *
 * "Confirm specialization match via `AGENT-SPECIALIZATION.md`; follow
 * its Collaboration or escalation outcome if there is no clean match"
 * (Process) maps `SqliteAgentSpecialization`'s own three real outcomes
 * onto this spec's own Assignment Outcome: `Matched` proceeds,
 * `Collaboration Required` and `Escalated — No Matching Role` both
 * become `Referred` (this spec's own Outputs name a `refer` outcome
 * distinct from an outright `reject` precisely for this situation),
 * and `invalid` (malformed specialization input) is a `Rejected`.
 *
 * "Confirm health status via `AGENT-MONITOR.md`; reject or flag per
 * the agent's current health classification" is read literally against
 * that spec's own Health Status table: `NORMAL` proceeds clean,
 * `DEGRADED` proceeds but is flagged (its own definition says the
 * agent "remains eligible for work"), and `CRITICAL` rejects (its own
 * definition says eligibility "must be reassessed"). `UNKNOWN` --
 * "required telemetry is missing... health cannot be classified" --
 * also rejects rather than being treated as an assumed pass, per
 * "an assignment must not proceed on a stale or assumed status"
 * (Inputs); an agent that has simply never been monitored has no
 * confirmed health at all.
 *
 * "Confirm delegation authorization when the assignment originates as
 * a delegation, via Delegation" reads an *existing*
 * `SqliteAgentDelegation` record by `delegation_id` rather than
 * re-running `delegate()` -- delegation authorization was already
 * decided elsewhere; this class only confirms it, never re-decides it,
 * matching "the Manager aggregates; it does not duplicate the
 * authority of the components it consults" (Operational Principles).
 * This check runs only when a `delegation_id` is actually supplied,
 * per the spec's own conditional wording.
 *
 * Once every check passes, the actual routing decision belongs to
 * `TaskRouter::route()` alone -- it searches every registered agent by
 * capability and load, and may select a different agent than the one
 * this class just vetted; this class never forces its own candidate
 * past that real selection, honoring "it must not... perform the
 * capability-based routing decision itself" (Permission Boundary)
 * literally.
 *
 * SQLite-backed for the explicit Agent Record table and "record the
 * assignment outcome" (Responsibilities).
 */
final class SqliteAgentManager
{
    private const ELIGIBLE_LIFECYCLE_STATES = ['ACTIVE'];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?AgentRegistry $agentRegistry = null,
        private readonly ?SqliteAgentSpecialization $specialization = null,
        private readonly ?SqliteAgentLifecycle $lifecycle = null,
        private readonly ?SqliteAgentMonitor $monitor = null,
        private readonly ?SqliteAgentDelegation $delegation = null,
        private readonly ?TaskRouter $taskRouter = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS agent_manager_assignments (
                assignment_id TEXT PRIMARY KEY,
                agent_id TEXT,
                requested_work TEXT,
                specialization_match TEXT,
                lifecycle_state TEXT,
                health_status TEXT,
                delegation_reference TEXT,
                outcome TEXT NOT NULL,
                failing_check TEXT,
                owner TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * Agent Management Process steps 1-8.
     *
     * @param array{
     *     agent_id?: ?string,
     *     work_reference?: ?string,
     *     specialization?: array{required_domain?: ?string, candidate_roles?: array<int, string>, boundary_verified?: bool, escalation_criteria?: array<int, string>},
     *     delegation_id?: ?string,
     *     step?: array{task_id?: string, status?: string, domain_context?: ?string, permissions?: ?string},
     *     requirements?: array{required_capability?: string},
     *     context?: array<string, mixed>
     * } $request
     * @return array{
     *     outcome: string,
     *     assignment_id: ?string,
     *     agent_id: ?string,
     *     owner: ?string,
     *     health_flagged: bool,
     *     failing_check: ?string,
     *     error: ?string
     * }
     */
    public function assign(array $request): array
    {
        $agentId = $request['agent_id'] ?? null;
        $workReference = $request['work_reference'] ?? null;

        if (!$this->present($agentId) || !$this->present($workReference)) {
            return $this->envelope('invalid', null, $agentId, null, false, null, 'An assignment requires a non-empty agent_id and work_reference.');
        }

        if ($this->agentRegistry === null) {
            return $this->recordAndEnvelope('reject', $agentId, $workReference, null, null, null, null, 'registry', null, false, 'Agent Registry is not configured.');
        }

        if ($this->agentRegistry->get($agentId) === null) {
            return $this->recordAndEnvelope('reject', $agentId, $workReference, null, null, null, null, 'registry', null, false, sprintf('Agent "%s" is not listed in the Agent Registry.', $agentId));
        }

        if ($this->specialization === null) {
            return $this->recordAndEnvelope('reject', $agentId, $workReference, null, null, null, null, 'specialization', null, false, 'Specialization is not configured.');
        }

        $specializationResult = $this->specialization->match(array_replace(
            ['work_reference' => $workReference],
            $request['specialization'] ?? []
        ));

        if ($specializationResult['outcome'] === 'invalid') {
            return $this->recordAndEnvelope('reject', $agentId, $workReference, null, null, null, null, 'specialization', null, false, $specializationResult['error']);
        }

        if (in_array($specializationResult['outcome'], ['Collaboration Required', 'Escalated — No Matching Role'], true)) {
            return $this->recordAndEnvelope('refer', $agentId, $workReference, $specializationResult['outcome'], null, null, null, 'specialization', null, false, $specializationResult['rationale']);
        }

        if ($this->lifecycle === null) {
            return $this->recordAndEnvelope('reject', $agentId, $workReference, $specializationResult['outcome'], null, null, null, 'lifecycle', null, false, 'Lifecycle Manager is not configured.');
        }

        $lifecycleState = $this->lifecycle->currentState($agentId);

        if (!in_array($lifecycleState, self::ELIGIBLE_LIFECYCLE_STATES, true)) {
            return $this->recordAndEnvelope('reject', $agentId, $workReference, $specializationResult['outcome'], $lifecycleState, null, null, 'lifecycle', null, false, sprintf('Agent "%s" is in lifecycle state "%s", which is not eligible for new work.', $agentId, $lifecycleState ?? 'UNKNOWN'));
        }

        if ($this->monitor === null) {
            return $this->recordAndEnvelope('reject', $agentId, $workReference, $specializationResult['outcome'], $lifecycleState, null, null, 'monitor', null, false, 'Monitor is not configured.');
        }

        $health = $this->monitor->currentHealth($agentId);
        $healthStatus = $health['status'] ?? 'UNKNOWN';

        if (in_array($healthStatus, ['CRITICAL', 'UNKNOWN'], true)) {
            return $this->recordAndEnvelope('reject', $agentId, $workReference, $specializationResult['outcome'], $lifecycleState, $healthStatus, null, 'monitor', null, false, sprintf('Agent "%s" health status is "%s"; eligibility for new work must be reassessed.', $agentId, $healthStatus));
        }

        $healthFlagged = $healthStatus === 'DEGRADED';
        $delegationId = $request['delegation_id'] ?? null;

        if ($this->present($delegationId)) {
            if ($this->delegation === null) {
                return $this->recordAndEnvelope('reject', $agentId, $workReference, $specializationResult['outcome'], $lifecycleState, $healthStatus, $delegationId, 'delegation', null, $healthFlagged, 'Delegation is not configured to confirm authorization.');
            }

            $delegationRecord = $this->delegation->get($delegationId);

            if ($delegationRecord === null || $delegationRecord['authorization'] !== 'Approved') {
                return $this->recordAndEnvelope('reject', $agentId, $workReference, $specializationResult['outcome'], $lifecycleState, $healthStatus, $delegationId, 'delegation', null, $healthFlagged, sprintf('Delegation "%s" is not confirmed authorized.', $delegationId));
            }
        }

        if ($this->taskRouter === null) {
            return $this->recordAndEnvelope('reject', $agentId, $workReference, $specializationResult['outcome'], $lifecycleState, $healthStatus, $delegationId, 'task_router', null, $healthFlagged, 'Task Router is not configured.');
        }

        $routed = $this->taskRouter->route(
            $request['step'] ?? ['task_id' => $workReference],
            $request['requirements'] ?? ['required_capability' => ''],
            $request['context'] ?? []
        );

        if ($routed['status'] !== 'ROUTED') {
            return $this->recordAndEnvelope('reject', $agentId, $workReference, $specializationResult['outcome'], $lifecycleState, $healthStatus, $delegationId, 'task_router', null, $healthFlagged, $routed['rationale'] ?? 'Task Router did not route this assignment.');
        }

        return $this->recordAndEnvelope('proceed', $agentId, $workReference, $specializationResult['outcome'], $lifecycleState, $healthStatus, $delegationId, null, $routed['owner'], $healthFlagged, null);
    }

    /**
     * @return ?array<string, mixed>
     */
    public function get(string $assignmentId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM agent_manager_assignments WHERE assignment_id = :assignment_id');
        $statement->execute(['assignment_id' => $assignmentId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * "Report aggregate agent status across the checks above"
     * (Responsibilities) -- every recorded assignment decision for an
     * agent, in the order it was decided.
     *
     * @return array<int, array<string, mixed>>
     */
    public function history(string $agentId): array
    {
        $statement = $this->database->prepare('SELECT * FROM agent_manager_assignments WHERE agent_id = :agent_id ORDER BY rowid ASC');
        $statement->execute(['agent_id' => $agentId]);

        return $statement->fetchAll();
    }

    /**
     * @return array{outcome: string, assignment_id: ?string, agent_id: ?string, owner: ?string, health_flagged: bool, failing_check: ?string, error: ?string}
     */
    private function recordAndEnvelope(
        string $outcome,
        string $agentId,
        string $workReference,
        ?string $specializationMatch,
        ?string $lifecycleState,
        ?string $healthStatus,
        ?string $delegationReference,
        ?string $failingCheck,
        ?string $owner,
        bool $healthFlagged,
        ?string $error
    ): array {
        $assignmentId = 'assignment_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO agent_manager_assignments (
                assignment_id, agent_id, requested_work, specialization_match, lifecycle_state,
                health_status, delegation_reference, outcome, failing_check, owner, created_at
            ) VALUES (
                :assignment_id, :agent_id, :requested_work, :specialization_match, :lifecycle_state,
                :health_status, :delegation_reference, :outcome, :failing_check, :owner, :created_at
            )'
        );
        $statement->execute([
            'assignment_id' => $assignmentId,
            'agent_id' => $agentId,
            'requested_work' => $workReference,
            'specialization_match' => $specializationMatch,
            'lifecycle_state' => $lifecycleState,
            'health_status' => $healthStatus,
            'delegation_reference' => $delegationReference,
            'outcome' => $outcome,
            'failing_check' => $failingCheck,
            'owner' => $owner,
            'created_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);

        return $this->envelope($outcome, $assignmentId, $agentId, $owner, $healthFlagged, $failingCheck, $error);
    }

    /**
     * @return array{outcome: string, assignment_id: ?string, agent_id: ?string, owner: ?string, health_flagged: bool, failing_check: ?string, error: ?string}
     */
    private function envelope(string $outcome, ?string $assignmentId, ?string $agentId, ?string $owner, bool $healthFlagged, ?string $failingCheck, ?string $error): array
    {
        return [
            'outcome' => $outcome,
            'assignment_id' => $assignmentId,
            'agent_id' => $agentId,
            'owner' => $owner,
            'health_flagged' => $healthFlagged,
            'failing_check' => $failingCheck,
            'error' => $error,
        ];
    }

    private function present(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }
}
