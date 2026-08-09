<?php

declare(strict_types=1);

namespace SquirrelForge\Agent;

use Closure;
use DateTimeImmutable;
use PDO;
use SquirrelForge\Coordination\SqliteHandoffProtocol;
use SquirrelForge\Engine\TaskRouter;

/**
 * Defines when an agent is authorized to hand part of its own work to
 * another agent, which delegation types are valid, and what
 * accountability must be preserved when it does, per
 * 16_AGENTS/AGENT-DELEGATION.md -- the second real component in
 * 16_AGENTS's governance/coordination gap, and the first to compose
 * three already-real components at once: `TaskRouter`,
 * `SqliteHandoffProtocol`, and the just-built `SqliteAgentLifecycle`.
 *
 * "Delegation defines authorization and accountability rules. It does
 * not locate or select the receiving agent... and it does not execute
 * the handoff itself" (Purpose) is upheld by genuine composition, not
 * caller-supplied evidence standing in for either: `delegate()` calls
 * the real `TaskRouter::route()` for capability matching and owner
 * selection, and the real `SqliteHandoffProtocol::initiate()` for the
 * actual context transfer and acceptance -- this class never invents
 * an owner or fabricates an acceptance either component didn't itself
 * produce.
 *
 * "Confirm the receiving agent is in an eligible lifecycle state...
 * before delegation proceeds" composes the real
 * `SqliteAgentLifecycle::currentState()` -- only `ACTIVE` counts as
 * eligible, the one Lifecycle State that spec's own table defines as
 * "Available for work assignment" (`BUSY` is "currently executing,"
 * not available for a *new* assignment). This class checks eligibility
 * twice when a receiving-agent hint is supplied and the Task Router
 * confirms a different actual selection: the hint check is advisory,
 * the check against whichever agent `TaskRouter::route()` genuinely
 * selects is the one that gates the delegation.
 *
 * "A delegation must not be authorized without confirming the
 * delegating agent actually holds the authority to delegate this
 * class of work" (Inputs) fails closed: `authorized_delegation_types`
 * is caller-supplied evidence (no separate role/authority registry
 * exists in this codebase to compose against), and an *omitted* or
 * empty declaration is treated as "not confirmed," never as
 * "unrestricted" -- the same fail-closed stance `IntegrationManager`
 * already takes toward an unconfigured governance decision.
 *
 * "A rejected delegation requires defined alternative handling, not
 * silent abandonment" (Delegation Principles) is a caller-supplied
 * `$onRejection` closure -- this class does not own re-delegation or
 * escalation strategy itself (Boundary), so it only ever notifies the
 * real owner of that decision, invoked on every path that ends in
 * `Rejected`.
 *
 * SQLite-backed for "the delegation is fully recorded for audit and
 * traceability" (Rule) -- every attempt is recorded, including
 * rejected ones, matching this codebase's other `Sqlite*` decision
 * components.
 */
final class SqliteAgentDelegation
{
    private const DELEGATION_TYPES = ['Direct', 'Hierarchical', 'Collaborative', 'Escalation', 'Fallback'];

    private const ELIGIBLE_RECEIVING_STATES = ['ACTIVE'];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?TaskRouter $taskRouter = null,
        private readonly ?SqliteHandoffProtocol $handoffProtocol = null,
        private readonly ?SqliteAgentLifecycle $agentLifecycle = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS agent_delegations (
                delegation_id TEXT PRIMARY KEY,
                task_ref TEXT NOT NULL,
                delegating_agent TEXT NOT NULL,
                delegation_type TEXT,
                receiving_agent TEXT,
                authorization TEXT NOT NULL,
                authorization_reason TEXT,
                handoff_ref TEXT,
                status TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * Delegation Process steps 1-7.
     *
     * @param array{
     *     task_ref?: ?string,
     *     delegating_agent?: ?string,
     *     delegation_type?: ?string,
     *     authorized_delegation_types?: array<int, string>,
     *     required_capability?: ?string,
     *     receiving_agent_hint?: ?string,
     *     step?: array{task_id?: string, status?: string, domain_context?: ?string, permissions?: ?string},
     *     context?: array<string, mixed>,
     *     task_status?: ?string,
     *     validation_status?: ?string,
     *     artifacts?: array<int, string>,
     *     notes?: ?string
     * } $request
     * @param ?Closure $requestAcceptance forwarded to SqliteHandoffProtocol::initiate().
     * @param ?Closure $onRejection (array $context): mixed the real "require alternative handling" hook. Invoked whenever this delegation ends Rejected.
     * @param array{max_retries?: int, critical_dependency_unresolved?: bool, security_or_integrity_at_risk?: bool, human_approval_required?: bool} $recoveryOptions forwarded to SqliteHandoffProtocol::initiate().
     * @return array{outcome: string, delegation_id: ?string, task_ref: ?string, delegation_type: ?string, receiving_agent: ?string, authorization: string, status: string, handoff_ref: ?string, error: ?string}
     */
    public function delegate(array $request, ?Closure $requestAcceptance = null, ?Closure $onRejection = null, array $recoveryOptions = []): array
    {
        $taskRef = $request['task_ref'] ?? null;
        $delegatingAgent = $request['delegating_agent'] ?? null;
        $delegationType = $request['delegation_type'] ?? null;

        if (!$this->present($taskRef) || !$this->present($delegatingAgent)) {
            return $this->reject($taskRef, $delegatingAgent, $delegationType, null, 'A delegation requires a non-empty task_ref and delegating_agent.', $onRejection);
        }

        if (!is_string($delegationType) || !in_array($delegationType, self::DELEGATION_TYPES, true)) {
            return $this->reject($taskRef, $delegatingAgent, $delegationType, null, sprintf('"%s" is not one of this spec\'s named Delegation Types.', (string) ($delegationType ?? '')), $onRejection);
        }

        $authorizedTypes = $request['authorized_delegation_types'] ?? [];

        if (!in_array($delegationType, $authorizedTypes, true)) {
            return $this->reject($taskRef, $delegatingAgent, $delegationType, null, sprintf('"%s" is not confirmed authorized to delegate "%s" work.', $delegatingAgent, $delegationType), $onRejection);
        }

        $hint = $request['receiving_agent_hint'] ?? null;

        if ($this->present($hint)) {
            $hintReason = $this->ineligibilityReason($hint);

            if ($hintReason !== null) {
                return $this->reject($taskRef, $delegatingAgent, $delegationType, null, $hintReason, $onRejection);
            }
        }

        if ($this->taskRouter === null) {
            return $this->reject($taskRef, $delegatingAgent, $delegationType, null, 'No Task Router is configured to select a receiving agent.', $onRejection);
        }

        $routed = $this->taskRouter->route(
            $request['step'] ?? ['task_id' => $taskRef],
            ['required_capability' => $request['required_capability'] ?? ''],
            $request['context'] ?? []
        );

        if ($routed['status'] !== 'ROUTED' || $routed['owner'] === null) {
            return $this->reject($taskRef, $delegatingAgent, $delegationType, null, $routed['rationale'] ?? 'Task Router did not select a receiving agent.', $onRejection);
        }

        $receivingAgent = $routed['owner'];
        $ineligibilityReason = $this->ineligibilityReason($receivingAgent);

        if ($ineligibilityReason !== null) {
            return $this->reject($taskRef, $delegatingAgent, $delegationType, $receivingAgent, $ineligibilityReason, $onRejection);
        }

        if ($this->handoffProtocol === null) {
            $delegationId = $this->record($taskRef, $delegatingAgent, $delegationType, $receivingAgent, 'Approved', null, null, 'Authorized');

            return $this->outcome('recorded', $delegationId, $taskRef, $delegationType, $receivingAgent, 'Approved', 'Authorized', null, null);
        }

        $handoff = $this->handoffProtocol->initiate(
            [
                'task_id' => $taskRef,
                'current_agent' => $delegatingAgent,
                'next_agent' => $receivingAgent,
                'task_status' => $request['task_status'] ?? null,
                'validation_status' => $request['validation_status'] ?? null,
                'artifacts' => $request['artifacts'] ?? [],
                'notes' => $request['notes'] ?? null,
            ],
            $requestAcceptance,
            $recoveryOptions
        );

        $status = match ($handoff['outcome']) {
            'Accepted' => 'Accepted',
            'Sent' => 'Handed Off',
            default => 'Rejected',
        };

        $delegationId = $this->record($taskRef, $delegatingAgent, $delegationType, $receivingAgent, 'Approved', null, $handoff['handoff_id'], $status);

        if ($status === 'Rejected' && $onRejection !== null) {
            $onRejection(['task_ref' => $taskRef, 'delegation_id' => $delegationId, 'receiving_agent' => $receivingAgent, 'reason' => $handoff['error'] ?? $handoff['rejection_reason'] ?? 'Handoff did not reach Accepted.']);
        }

        return $this->outcome('recorded', $delegationId, $taskRef, $delegationType, $receivingAgent, 'Approved', $status, $handoff['handoff_id'], null);
    }

    /**
     * @return ?string null when eligible; the rejection reason otherwise.
     */
    private function ineligibilityReason(string $agentId): ?string
    {
        if ($this->agentLifecycle === null) {
            return null;
        }

        $state = $this->agentLifecycle->currentState($agentId);

        if (!in_array($state, self::ELIGIBLE_RECEIVING_STATES, true)) {
            return sprintf('Agent "%s" is in lifecycle state "%s", which is not eligible to receive new work.', $agentId, $state ?? 'UNKNOWN');
        }

        return null;
    }

    /**
     * @return array{outcome: string, delegation_id: ?string, task_ref: ?string, delegation_type: ?string, receiving_agent: ?string, authorization: string, status: string, handoff_ref: ?string, error: ?string}
     */
    private function reject(?string $taskRef, ?string $delegatingAgent, ?string $delegationType, ?string $receivingAgent, string $reason, ?Closure $onRejection): array
    {
        $delegationId = null;

        if ($this->present($taskRef) && $this->present($delegatingAgent)) {
            $delegationId = $this->record($taskRef, $delegatingAgent, $delegationType, $receivingAgent, 'Denied', $reason, null, 'Rejected');
        }

        if ($onRejection !== null) {
            $onRejection(['task_ref' => $taskRef, 'delegation_id' => $delegationId, 'receiving_agent' => $receivingAgent, 'reason' => $reason]);
        }

        return $this->outcome('rejected', $delegationId, $taskRef, $delegationType, $receivingAgent, 'Denied', 'Rejected', null, $reason);
    }

    private function record(string $taskRef, string $delegatingAgent, ?string $delegationType, ?string $receivingAgent, string $authorization, ?string $authorizationReason, ?string $handoffRef, string $status): string
    {
        $delegationId = 'delegation_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO agent_delegations (delegation_id, task_ref, delegating_agent, delegation_type, receiving_agent, authorization, authorization_reason, handoff_ref, status, created_at)
             VALUES (:delegation_id, :task_ref, :delegating_agent, :delegation_type, :receiving_agent, :authorization, :authorization_reason, :handoff_ref, :status, :created_at)'
        );
        $statement->execute([
            'delegation_id' => $delegationId,
            'task_ref' => $taskRef,
            'delegating_agent' => $delegatingAgent,
            'delegation_type' => $delegationType,
            'receiving_agent' => $receivingAgent,
            'authorization' => $authorization,
            'authorization_reason' => $authorizationReason,
            'handoff_ref' => $handoffRef,
            'status' => $status,
            'created_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);

        return $delegationId;
    }

    /**
     * @return ?array<string, mixed>
     */
    public function get(string $delegationId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM agent_delegations WHERE delegation_id = :delegation_id');
        $statement->execute(['delegation_id' => $delegationId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history(string $taskRef): array
    {
        $statement = $this->database->prepare('SELECT * FROM agent_delegations WHERE task_ref = :task_ref ORDER BY rowid ASC');
        $statement->execute(['task_ref' => $taskRef]);

        return $statement->fetchAll();
    }

    private function present(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }

    /**
     * @return array{outcome: string, delegation_id: ?string, task_ref: ?string, delegation_type: ?string, receiving_agent: ?string, authorization: string, status: string, handoff_ref: ?string, error: ?string}
     */
    private function outcome(string $outcome, ?string $delegationId, ?string $taskRef, ?string $delegationType, ?string $receivingAgent, string $authorization, string $status, ?string $handoffRef, ?string $error): array
    {
        return [
            'outcome' => $outcome,
            'delegation_id' => $delegationId,
            'task_ref' => $taskRef,
            'delegation_type' => $delegationType,
            'receiving_agent' => $receivingAgent,
            'authorization' => $authorization,
            'status' => $status,
            'handoff_ref' => $handoffRef,
            'error' => $error,
        ];
    }
}
