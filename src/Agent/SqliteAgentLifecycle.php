<?php

declare(strict_types=1);

namespace SquirrelForge\Agent;

use DateTimeImmutable;
use PDO;

/**
 * Owns the operational state machine for AI agent entities themselves
 * -- their creation, activation, operation, suspension, retirement,
 * and archival -- and validates and records every transition between
 * those states, per 16_AGENTS/AGENT-LIFECYCLE.md -- the first real
 * component in 16_AGENTS beyond the pipeline plumbing
 * (`AgentOrchestrator`, `AgentRegistry`, `CallbackAgent`) already
 * built for the eight role-agent specs (Architect/Planner/Developer/
 * Reviewer/Security/Performance/Documentation/Release), which that
 * generic `CallbackAgent` + `PipelineStages::ALL` mechanism already
 * covers without needing eight separate classes.
 *
 * "A transition without a current state on record must not be
 * validated as if the agent were already known-good" (Inputs) is a
 * real, checked rule: an agent with no prior recorded transition may
 * only transition to `DRAFT` (genuine registration/creation) -- any
 * other target for an unknown agent is refused, never silently
 * treated as if some earlier state were already on file.
 *
 * The Valid Lifecycle Transitions table is applied as a literal,
 * closed lookup, not an approximation: `ARCHIVED` and `RETIRED` have
 * no listed outbound transitions at all, so "ARCHIVED records are
 * immutable" and "RETIRED agents cannot be reactivated" (Lifecycle
 * Principles) are upheld structurally by the table's own absence of
 * those rows -- this class needed no special-case code for either
 * rule, the same table lookup that rejects any other invalid pair
 * already rejects these.
 *
 * "Applicable governance or suspension conditions from Governance or
 * the Monitor" (Inputs) arrives as a caller-supplied
 * `governance_block_reason` -- neither `AGENT-GOVERNANCE.md` nor
 * `AGENT-MONITOR.md` has code yet, so this class consumes that
 * evidence rather than computing it, the same "reference, don't
 * recompute" boundary this codebase applies to every uncoded
 * authority. When present, it forces rejection even for an otherwise
 * table-valid transition -- a governance hold outranks table validity.
 *
 * "Any evidence required to validate the transition (for example, a
 * completed registration before activation)" (Inputs) is accepted and
 * recorded for audit (`evidence`), but this class does not invent a
 * specific required-evidence schema per transition -- the spec names
 * only an example, not a concrete requirement, and fabricating one
 * would assert a validation rule this spec never actually defines.
 *
 * SQLite-backed for "record every transition... and preserve archived
 * records as immutable history" -- the same reasoning this codebase's
 * other `Sqlite*` state-machine components already established. The
 * agent's current state is derived, not separately stored: it is
 * always the `new_state` of the most recent `Passed` transition, so
 * there is exactly one source of truth for it rather than a
 * parallel field that could drift from the event log.
 */
final class SqliteAgentLifecycle
{
    private const STATES = [
        'DRAFT', 'REGISTERED', 'INITIALIZED', 'ACTIVE', 'BUSY', 'SUSPENDED', 'MAINTENANCE', 'RETIRED', 'ARCHIVED',
    ];

    /** The spec's own closed Valid Lifecycle Transitions table. Any pair not listed here is invalid. */
    private const VALID_TRANSITIONS = [
        'DRAFT' => ['REGISTERED'],
        'REGISTERED' => ['INITIALIZED'],
        'INITIALIZED' => ['ACTIVE'],
        'ACTIVE' => ['BUSY', 'SUSPENDED', 'MAINTENANCE', 'RETIRED'],
        'BUSY' => ['ACTIVE'],
        'SUSPENDED' => ['ACTIVE'],
        'MAINTENANCE' => ['ACTIVE'],
        'RETIRED' => ['ARCHIVED'],
    ];

    private PDO $database;

    public function __construct(string $databasePath)
    {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS agent_lifecycle_events (
                event_id TEXT PRIMARY KEY,
                agent_id TEXT NOT NULL,
                previous_state TEXT,
                new_state TEXT NOT NULL,
                requested_by TEXT NOT NULL,
                validation TEXT NOT NULL,
                rejection_reason TEXT,
                evidence_json TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * Lifecycle Process steps 1-6 (step 7, publishing, is satisfied by
     * the return value itself and by `currentState()`/`get()` for
     * `AGENT-MANAGER.md`/`AGENT-MONITOR.md` to consume).
     *
     * @param array<string, mixed> $evidence recorded for audit; not independently interpreted (see class docblock).
     * @return array{outcome: string, event_id: ?string, agent_id: ?string, previous_state: ?string, new_state: ?string, error: ?string}
     */
    public function transition(string $agentId, string $toState, string $requestedBy, array $evidence = [], ?string $governanceBlockReason = null): array
    {
        if ($agentId === '' || $requestedBy === '') {
            return $this->outcome('invalid', null, $agentId, null, $toState, 'A lifecycle transition requires a non-empty agent_id and requested_by.');
        }

        if (!in_array($toState, self::STATES, true)) {
            return $this->reject($agentId, null, $toState, $requestedBy, $evidence, sprintf('"%s" is not one of this spec\'s named Lifecycle States.', $toState));
        }

        $currentState = $this->currentState($agentId);

        if ($currentState === null) {
            if ($toState !== 'DRAFT') {
                return $this->reject($agentId, null, $toState, $requestedBy, $evidence, 'No current lifecycle state is on record for this agent; only DRAFT (initial registration) is valid for an unknown agent.');
            }
        } elseif (!in_array($toState, self::VALID_TRANSITIONS[$currentState] ?? [], true)) {
            return $this->reject($agentId, $currentState, $toState, $requestedBy, $evidence, sprintf('Transition from "%s" to "%s" is not in the valid transition table.', $currentState, $toState));
        }

        if ($governanceBlockReason !== null && $governanceBlockReason !== '') {
            return $this->reject($agentId, $currentState, $toState, $requestedBy, $evidence, sprintf('Blocked by governance/suspension condition: %s', $governanceBlockReason));
        }

        $eventId = $this->record($agentId, $currentState, $toState, $requestedBy, 'Passed', null, $evidence);

        return $this->outcome('transitioned', $eventId, $agentId, $currentState, $toState, null);
    }

    /**
     * The agent's current lifecycle state: the `new_state` of its most
     * recent `Passed` transition, or null when the agent has never
     * successfully transitioned (unknown to this component).
     */
    public function currentState(string $agentId): ?string
    {
        $statement = $this->database->prepare(
            "SELECT new_state FROM agent_lifecycle_events WHERE agent_id = :agent_id AND validation = 'Passed' ORDER BY rowid DESC LIMIT 1"
        );
        $statement->execute(['agent_id' => $agentId]);
        $row = $statement->fetch();

        return $row === false ? null : $row['new_state'];
    }

    /**
     * @return ?array<string, mixed>
     */
    public function get(string $eventId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM agent_lifecycle_events WHERE event_id = :event_id');
        $statement->execute(['event_id' => $eventId]);
        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * "Preserve archived records as immutable history" -- every
     * transition attempt (Passed and Rejected) for one agent, in the
     * order it happened.
     *
     * @return array<int, array<string, mixed>>
     */
    public function history(string $agentId): array
    {
        $statement = $this->database->prepare('SELECT * FROM agent_lifecycle_events WHERE agent_id = :agent_id ORDER BY rowid ASC');
        $statement->execute(['agent_id' => $agentId]);

        return array_map(fn(array $row): array => $this->hydrate($row), $statement->fetchAll());
    }

    /**
     * @param array<string, mixed> $evidence
     * @return array{outcome: string, event_id: ?string, agent_id: ?string, previous_state: ?string, new_state: ?string, error: ?string}
     */
    private function reject(string $agentId, ?string $currentState, string $toState, string $requestedBy, array $evidence, string $reason): array
    {
        $eventId = $this->record($agentId, $currentState, $toState, $requestedBy, 'Rejected', $reason, $evidence);

        return $this->outcome('rejected', $eventId, $agentId, $currentState, $toState, $reason);
    }

    /**
     * @param array<string, mixed> $evidence
     */
    private function record(string $agentId, ?string $previousState, string $newState, string $requestedBy, string $validation, ?string $rejectionReason, array $evidence): string
    {
        $eventId = 'agent_lifecycle_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO agent_lifecycle_events (event_id, agent_id, previous_state, new_state, requested_by, validation, rejection_reason, evidence_json, created_at)
             VALUES (:event_id, :agent_id, :previous_state, :new_state, :requested_by, :validation, :rejection_reason, :evidence_json, :created_at)'
        );
        $statement->execute([
            'event_id' => $eventId,
            'agent_id' => $agentId,
            'previous_state' => $previousState,
            'new_state' => $newState,
            'requested_by' => $requestedBy,
            'validation' => $validation,
            'rejection_reason' => $rejectionReason,
            'evidence_json' => json_encode($evidence, JSON_THROW_ON_ERROR),
            'created_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);

        return $eventId;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $row['evidence'] = json_decode($row['evidence_json'], true, flags: JSON_THROW_ON_ERROR);
        unset($row['evidence_json']);

        return $row;
    }

    /**
     * @return array{outcome: string, event_id: ?string, agent_id: ?string, previous_state: ?string, new_state: ?string, error: ?string}
     */
    private function outcome(string $outcome, ?string $eventId, string $agentId, ?string $previousState, string $newState, ?string $error): array
    {
        return [
            'outcome' => $outcome,
            'event_id' => $eventId,
            'agent_id' => $agentId,
            'previous_state' => $previousState,
            'new_state' => $newState,
            'error' => $error,
        ];
    }
}
