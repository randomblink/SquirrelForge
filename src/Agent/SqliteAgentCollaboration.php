<?php

declare(strict_types=1);

namespace SquirrelForge\Agent;

use DateTimeImmutable;
use PDO;

/**
 * Defines the structure of multi-agent cooperative work -- the shared
 * objective, the collaboration model, the participating agents and
 * their roles, and the participation and ownership rules they operate
 * under -- per 16_AGENTS/AGENT-COLLABORATION.md, the fifth real
 * component in 16_AGENTS's governance/coordination gap.
 *
 * "Collaboration defines structure. It does not perform
 * synchronization, shared-context management, message passing, task
 * scheduling, or conflict resolution itself" (Purpose) is upheld by
 * never composing a specific `17_COORDINATION` execution call: none of
 * that layer's real components (Message Bus, Task Queue, Handoff
 * Protocol, Priority Manager, Progress Tracker, Conflict Resolution)
 * accept "a whole collaboration structure" as input, and each expects
 * its own task-scoped shape this class would otherwise have to
 * fabricate. "Hand off that structure to 17_COORDINATION for actual
 * scheduling, synchronization, and execution" is honored literally:
 * once a structure is validated and recorded it satisfies the Handoff
 * Rule's own content requirement ("a handoff is incomplete if
 * 17_COORDINATION cannot determine who owns what, or what should
 * trigger conflict resolution") by construction, and becomes available
 * for a caller to route into whichever real `17_COORDINATION`
 * component the work actually needs next.
 *
 * "Enforce the one-owner-at-a-time participation rule from
 * `16_AGENTS/README.md`" is a real, checked guard: two participants
 * may never declare the same ownership boundary, since "one agent owns
 * a given piece of work at a time" (Collaboration Principles) would
 * otherwise be violated at the moment the structure is defined, before
 * any execution even begins.
 *
 * This spec's own Escalation Criteria list (conflicting outputs,
 * disputed ownership, incompatible technical approaches, a concern one
 * participant raises against another's work, or an unresolved
 * dependency) is a real, closed, five-item vocabulary, even though the
 * spec states it as prose rather than a table -- "escalation criteria
 * are defined before execution, not improvised during a conflict"
 * (Collaboration Principles) is read as a requirement to validate
 * against that fixed list rather than accept arbitrary caller text, the
 * same "closed vocabulary, not free text" treatment this session
 * already applies to every other spec-enumerated list.
 *
 * A `lead_agent`, if declared, must be one of the structure's own
 * participants -- a "coordinating" agent this class never heard of
 * cannot coordinate anything. The Agent Planner's role assignment plan
 * (`16_AGENTS/AGENT-PLANNER.md`, already real as `PlannerAgent`) is
 * accepted as optional caller-supplied evidence rather than invoked
 * directly: `PlannerAgent` only runs inside a pipeline's own execution
 * context, and this spec assigns it no validation role beyond
 * informing the structure, so it is recorded, not cross-checked.
 *
 * SQLite-backed for "record the collaboration structure" and
 * "collaboration history is preserved" (Responsibilities /
 * Collaboration Principles).
 */
final class SqliteAgentCollaboration
{
    private const MODELS = ['Sequential', 'Parallel', 'Hierarchical', 'Consensus', 'Specialist Team'];

    /** This spec's own Escalation Criteria prose, turned into a real, closed, checkable vocabulary. */
    private const ESCALATION_CRITERIA = [
        'conflicting_outputs',
        'disputed_ownership',
        'incompatible_technical_approaches',
        'cross_participant_concern',
        'unresolved_dependency',
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
            'CREATE TABLE IF NOT EXISTS agent_collaborations (
                collaboration_id TEXT PRIMARY KEY,
                objective TEXT NOT NULL,
                collaboration_model TEXT NOT NULL,
                lead_agent TEXT,
                participants_json TEXT NOT NULL,
                escalation_criteria_json TEXT NOT NULL,
                planner_plan_json TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * Collaboration Process steps 1-6.
     *
     * @param array{
     *     objective?: ?string,
     *     collaboration_model?: ?string,
     *     participants?: array<int, array{agent?: ?string, role?: ?string, ownership_boundary?: ?string}>,
     *     lead_agent?: ?string,
     *     escalation_criteria?: array<int, string>,
     *     planner_plan?: ?array<int, array<string, mixed>>
     * } $request
     * @return array{
     *     outcome: string,
     *     collaboration_id: ?string,
     *     collaboration_model: ?string,
     *     participants: ?array<int, array<string, mixed>>,
     *     escalation_criteria: ?array<int, string>,
     *     error: ?string
     * }
     */
    public function define(array $request): array
    {
        $objective = $request['objective'] ?? null;

        if (!$this->present($objective)) {
            return $this->envelope('invalid', null, null, null, null, 'A collaboration structure requires a non-empty shared objective.');
        }

        $participants = $request['participants'] ?? [];

        if (!is_array($participants) || $participants === []) {
            return $this->envelope('invalid', null, null, null, null, 'A collaboration structure requires at least one participating agent with a defined role.');
        }

        foreach ($participants as $participant) {
            if (!$this->present($participant['agent'] ?? null) || !$this->present($participant['role'] ?? null)) {
                return $this->envelope('invalid', null, null, null, null, 'Every participant requires a non-empty agent and role.');
            }
        }

        $collaborationModel = $request['collaboration_model'] ?? null;

        if (!is_string($collaborationModel) || !in_array($collaborationModel, self::MODELS, true)) {
            return $this->envelope('invalid', null, null, null, null, sprintf('"%s" is not one of this spec\'s named Collaboration Models.', (string) ($collaborationModel ?? '')));
        }

        $leadAgent = $request['lead_agent'] ?? null;
        $participantAgents = array_column($participants, 'agent');

        if ($collaborationModel === 'Hierarchical' && !$this->present($leadAgent)) {
            return $this->envelope('rejected', null, $collaborationModel, null, null, 'The Hierarchical model requires an identified lead agent.');
        }

        if ($leadAgent !== null && !in_array($leadAgent, $participantAgents, true)) {
            return $this->envelope('rejected', null, $collaborationModel, null, null, sprintf('Lead agent "%s" is not one of this structure\'s own participants.', $leadAgent));
        }

        $ownershipBoundaries = array_filter(array_column($participants, 'ownership_boundary'), $this->present(...));
        $duplicateBoundary = $this->firstDuplicate($ownershipBoundaries);

        if ($duplicateBoundary !== null) {
            return $this->envelope('rejected', null, $collaborationModel, null, null, sprintf('Ownership boundary "%s" is claimed by more than one participant, violating the one-owner-at-a-time rule.', $duplicateBoundary));
        }

        $escalationCriteria = $request['escalation_criteria'] ?? [];

        if (!is_array($escalationCriteria) || $escalationCriteria === []) {
            return $this->envelope('rejected', null, $collaborationModel, null, null, 'Escalation criteria must be defined before execution, not improvised during a conflict.');
        }

        $unrecognizedCriteria = array_diff($escalationCriteria, self::ESCALATION_CRITERIA);

        if ($unrecognizedCriteria !== []) {
            return $this->envelope('rejected', null, $collaborationModel, null, null, sprintf('Unrecognized escalation criteria: %s.', implode(', ', $unrecognizedCriteria)));
        }

        $collaborationId = 'collaboration_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO agent_collaborations (
                collaboration_id, objective, collaboration_model, lead_agent,
                participants_json, escalation_criteria_json, planner_plan_json, created_at
            ) VALUES (
                :collaboration_id, :objective, :collaboration_model, :lead_agent,
                :participants_json, :escalation_criteria_json, :planner_plan_json, :created_at
            )'
        );
        $statement->execute([
            'collaboration_id' => $collaborationId,
            'objective' => $objective,
            'collaboration_model' => $collaborationModel,
            'lead_agent' => $leadAgent,
            'participants_json' => json_encode($participants, JSON_THROW_ON_ERROR),
            'escalation_criteria_json' => json_encode($escalationCriteria, JSON_THROW_ON_ERROR),
            'planner_plan_json' => isset($request['planner_plan']) ? json_encode($request['planner_plan'], JSON_THROW_ON_ERROR) : null,
            'created_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);

        return $this->envelope('defined', $collaborationId, $collaborationModel, $participants, $escalationCriteria, null);
    }

    /**
     * @return ?array<string, mixed>
     */
    public function get(string $collaborationId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM agent_collaborations WHERE collaboration_id = :collaboration_id');
        $statement->execute(['collaboration_id' => $collaborationId]);
        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * "Collaboration history is preserved" -- every recorded structure,
     * in the order it was defined.
     *
     * @return array<int, array<string, mixed>>
     */
    public function history(): array
    {
        $statement = $this->database->query('SELECT * FROM agent_collaborations ORDER BY rowid ASC');

        return array_map(fn(array $row): array => $this->hydrate($row), $statement->fetchAll());
    }

    /**
     * @param array<int, string> $values
     */
    private function firstDuplicate(array $values): ?string
    {
        $seen = [];

        foreach ($values as $value) {
            if (isset($seen[$value])) {
                return $value;
            }

            $seen[$value] = true;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $row['participants'] = json_decode((string) $row['participants_json'], true, flags: JSON_THROW_ON_ERROR);
        $row['escalation_criteria'] = json_decode((string) $row['escalation_criteria_json'], true, flags: JSON_THROW_ON_ERROR);
        $row['planner_plan'] = $row['planner_plan_json'] !== null ? json_decode((string) $row['planner_plan_json'], true, flags: JSON_THROW_ON_ERROR) : null;
        unset($row['participants_json'], $row['escalation_criteria_json'], $row['planner_plan_json']);

        return $row;
    }

    private function present(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }

    /**
     * @param ?array<int, array<string, mixed>> $participants
     * @param ?array<int, string> $escalationCriteria
     * @return array{
     *     outcome: string,
     *     collaboration_id: ?string,
     *     collaboration_model: ?string,
     *     participants: ?array<int, array<string, mixed>>,
     *     escalation_criteria: ?array<int, string>,
     *     error: ?string
     * }
     */
    private function envelope(string $outcome, ?string $collaborationId, ?string $collaborationModel, ?array $participants, ?array $escalationCriteria, ?string $error): array
    {
        return [
            'outcome' => $outcome,
            'collaboration_id' => $collaborationId,
            'collaboration_model' => $collaborationModel,
            'participants' => $participants,
            'escalation_criteria' => $escalationCriteria,
            'error' => $error,
        ];
    }
}
