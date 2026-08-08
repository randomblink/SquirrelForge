<?php

declare(strict_types=1);

namespace SquirrelForge\Execution;

use DateTimeImmutable;

/**
 * Assembles a concise Execution Report from existing authoritative
 * records and references, per 20_EXECUTION/EXECUTION-REPORTER.md --
 * the ninth real component in 20_EXECUTION, and the layer's purest
 * assembler: "it does not decide, validate, assess, or recommend on
 * its own authority" (Purpose) is the whole point of this class, not
 * an incidental constraint.
 *
 * Four of the Execution Report Model's fields (Completed Activity,
 * Blocked/Unresolved Conditions, Rollback References, Failure/Recovery
 * References) are assembled entirely from the already-built
 * `SqliteExecutionLogger` -- the same shared log every other component
 * in this layer already writes through (`ActionDispatcher`,
 * `WorkflowExecutor`, `FailureHandler`, `RollbackManager`,
 * `SqliteResultCollector`). This class never invents a second
 * classification of what happened; it reads the real `action_type`/
 * `outcome` fields those components already recorded and buckets them,
 * the same "assemble and summarize existing records" the Purpose
 * requires literally. "Changed Artifact References" is genuine
 * composition of the just-extended `SqliteResultCollector::forExecution()`.
 *
 * The remaining fields (`status_reference` from the uncoded
 * `14_ENGINE/STATE-MANAGER.md`, the validation record's own fields from
 * `EngineValidation`, `unresolved_risk_references` from
 * `RiskAssessor`, `recommended_next_actions` from whichever planning/
 * governance authority supplies them) have no queryable store this
 * class could read from -- `EngineValidation` is a pure function with
 * no persistence, and `STATE-MANAGER.md`/`RISK-ASSESSOR.md`'s
 * production of a durable, retrievable reference is out of scope here.
 * These arrive as caller-supplied `$externalReferences`, the same
 * "reference, don't recompute" boundary `SqliteResultCollector::attachValidation()`
 * already established -- an empty/omitted field is never fabricated
 * content, it is the honest absence of a supplied record.
 *
 * "Validation Decision... copied without reinterpretation" gets one
 * real, checked guard: if a `validation_decision` is supplied, it must
 * be one of the seven values `EngineValidation::decide()` can actually
 * produce (`ACCEPTED`, `ACCEPTED_WITH_LIMITATIONS`, `REPAIR_REQUIRED`,
 * `BLOCKED`, `RECOVERY_REQUIRED`, `CLARIFICATION_REQUIRED`, `REJECTED`)
 * -- since that component is the only real source of this field, a
 * value outside its actual vocabulary cannot be a genuine copy of one,
 * and this class refuses to assemble a report around a value it cannot
 * verify actually came from that authority, rather than "reinterpreting"
 * an inconsistency into something plausible-looking.
 */
final class ExecutionReporter
{
    private const VALIDATION_DECISIONS = [
        'ACCEPTED', 'ACCEPTED_WITH_LIMITATIONS', 'REPAIR_REQUIRED', 'BLOCKED', 'RECOVERY_REQUIRED', 'CLARIFICATION_REQUIRED', 'REJECTED',
    ];

    private const COMPLETED_OUTCOMES = ['Passed', 'Complete', 'Successful', 'Completed'];

    private const UNRESOLVED_OUTCOMES = ['Failed', 'Blocked', 'Halted', 'Partial'];

    public function __construct(
        private readonly ?SqliteResultCollector $resultCollector = null,
        private readonly ?SqliteExecutionLogger $logger = null
    ) {
    }

    /**
     * @param array{
     *     status_reference?: ?string,
     *     validation_record_reference?: ?string,
     *     validation_decision?: ?string,
     *     validation_evidence_references?: array<int, string>,
     *     validation_limitations?: array<int, string>,
     *     unresolved_risk_references?: array<int, string>,
     *     recommended_next_actions?: array<int, array<string, mixed>>
     * } $externalReferences evidence from components this class has no queryable store for.
     * @return array{
     *     outcome: string,
     *     report: ?array<string, mixed>,
     *     error: ?string
     * }
     */
    public function assemble(string $executionRef, array $externalReferences = []): array
    {
        $validationDecision = $externalReferences['validation_decision'] ?? null;

        if ($validationDecision !== null && !in_array($validationDecision, self::VALIDATION_DECISIONS, true)) {
            return ['outcome' => 'invalid', 'report' => null, 'error' => sprintf('"%s" is not one of EngineValidation\'s real decision values; it cannot be copied without reinterpretation.', $validationDecision)];
        }

        [$completedActivity, $blockedUnresolved, $rollbackReferences, $failureRecoveryReferences] = $this->classifyLogEntries($executionRef);

        $report = [
            'report_id' => 'execution_report_' . bin2hex(random_bytes(12)),
            'execution_ref' => $executionRef,
            'status_reference' => $externalReferences['status_reference'] ?? null,
            'completed_activity' => $completedActivity,
            'blocked_unresolved_conditions' => $blockedUnresolved,
            'changed_artifact_references' => $this->resultCollector?->forExecution($executionRef) ?? [],
            'validation_record_reference' => $externalReferences['validation_record_reference'] ?? null,
            'validation_decision' => $validationDecision,
            'validation_evidence_references' => $externalReferences['validation_evidence_references'] ?? [],
            'validation_limitations' => $externalReferences['validation_limitations'] ?? [],
            'rollback_references' => $rollbackReferences,
            'failure_recovery_references' => $failureRecoveryReferences,
            'unresolved_risk_references' => $externalReferences['unresolved_risk_references'] ?? [],
            'recommended_next_actions' => $externalReferences['recommended_next_actions'] ?? [],
            'timestamp' => (new DateTimeImmutable())->format(DATE_ATOM),
        ];

        return ['outcome' => 'assembled', 'report' => $report, 'error' => null];
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>, 2: array<int, array<string, mixed>>, 3: array<int, array<string, mixed>>}
     */
    private function classifyLogEntries(string $executionRef): array
    {
        $completed = [];
        $unresolved = [];
        $rollbacks = [];
        $failures = [];

        foreach ($this->logger?->history($executionRef) ?? [] as $entry) {
            if (in_array($entry['outcome'], self::COMPLETED_OUTCOMES, true)) {
                $completed[] = $entry;
            } elseif (in_array($entry['outcome'], self::UNRESOLVED_OUTCOMES, true)) {
                $unresolved[] = $entry;
            }

            if ($entry['action_type'] === 'rollback') {
                $rollbacks[] = $entry;
            } elseif (in_array($entry['action_type'], ['failure_intake', 'failure_routing'], true)) {
                $failures[] = $entry;
            }
        }

        return [$completed, $unresolved, $rollbacks, $failures];
    }
}
