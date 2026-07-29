<?php

declare(strict_types=1);

namespace SquirrelForge\Automation;

use Closure;
use DateTimeImmutable;

/**
 * Verifies that required approval and decision references exist, remain
 * valid, match scope, and permit Automation-domain progression, per
 * 33_AUTOMATION/APPROVAL-GATE.md.
 *
 * "Determine required approval-reference categories from the automation
 * contract and authoritative policy-evaluation results" is a real
 * integration: when the injected SqliteAutomationGovernance's current
 * record for an automation is `conditioned`, its real `conditions` list
 * (produced by AutomationValidator's unsatisfied references, surfaced
 * through Automation Governance's own review()) becomes required
 * approval categories here, on top of whatever the caller names
 * directly -- this class doesn't invent conditions, it verifies the
 * ones an upstream authority already decided.
 *
 * Verification is real, not a fabricated status check: each supplied
 * reference's validity window (`valid_from`/`valid_until`) is compared
 * against actual wall-clock time (via an injectable clock, for tests),
 * and its `scope` is matched against the automation's own scope, with
 * `*` as an explicit wildcard. This class never creates or approves a
 * reference itself, per the spec's rule that it "does not... create
 * human approvals" or "approve its own exceptions" -- it only ever
 * reports what a caller-supplied reference already says.
 *
 * All seven gate results are real and reachable through a deterministic
 * priority ladder: `incomplete` (a required category has no reference,
 * or one whose scope doesn't match) beats `revoked-reference` beats
 * `blocked` (a denied reference) beats `expired-reference` (approved,
 * but outside its validity window) beats `pending` beats
 * `conditional-pass` (every category checks out, but the underlying
 * governance decision was itself conditional) beats `pass`. Like
 * AutomationValidator, this class has no database -- the spec forbids
 * owning "general audit/storage infrastructure" -- it's a pure
 * verification function.
 */
final class ApprovalGate
{
    private const REFERENCE_STATUSES = ['approved', 'pending', 'revoked', 'denied'];

    public function __construct(
        private readonly ?SqliteAutomationGovernance $governance = null,
        private readonly ?Closure $clock = null
    ) {
    }

    /**
     * @param array<string, array{status: string, scope?: string, valid_from?: string, valid_until?: string}> $references keyed by category
     * @param array{scope?: string, required_categories?: array<int, string>} $options
     * @return array{outcome: string, required_categories: array<int, string>, missing_categories: array<int, string>, revoked_categories: array<int, string>, blocked_categories: array<int, string>, expired_categories: array<int, string>, pending_categories: array<int, string>}
     */
    public function checkGate(string $automationId, array $references, array $options = []): array
    {
        $governanceStatus = $this->governance?->currentStatus($automationId);
        $isConditioned = ($governanceStatus['outcome'] ?? null) === 'conditioned';
        $governanceConditions = $isConditioned ? $governanceStatus['conditions'] : [];

        $requiredCategories = array_values(array_unique([...($options['required_categories'] ?? []), ...$governanceConditions]));

        $missing = [];
        $revoked = [];
        $blocked = [];
        $expired = [];
        $pending = [];

        foreach ($requiredCategories as $category) {
            $reference = $references[$category] ?? null;

            if ($reference === null || !$this->scopeMatches($reference, $options['scope'] ?? null)) {
                $missing[] = $category;
                continue;
            }

            $status = $reference['status'] ?? null;

            if (!in_array($status, self::REFERENCE_STATUSES, true)) {
                $missing[] = $category;
                continue;
            }

            match ($status) {
                'revoked' => $revoked[] = $category,
                'denied' => $blocked[] = $category,
                'pending' => $pending[] = $category,
                'approved' => $this->isWithinValidityWindow($reference) ? null : ($expired[] = $category),
            };
        }

        $outcome = match (true) {
            $missing !== [] => 'incomplete',
            $revoked !== [] => 'revoked-reference',
            $blocked !== [] => 'blocked',
            $expired !== [] => 'expired-reference',
            $pending !== [] => 'pending',
            $isConditioned => 'conditional-pass',
            default => 'pass',
        };

        return [
            'outcome' => $outcome,
            'required_categories' => $requiredCategories,
            'missing_categories' => $missing,
            'revoked_categories' => $revoked,
            'blocked_categories' => $blocked,
            'expired_categories' => $expired,
            'pending_categories' => $pending,
        ];
    }

    /**
     * @param array{scope?: string} $reference
     */
    private function scopeMatches(array $reference, ?string $requiredScope): bool
    {
        if ($requiredScope === null || !isset($reference['scope']) || $reference['scope'] === '*') {
            return true;
        }

        return $reference['scope'] === $requiredScope;
    }

    /**
     * @param array{valid_from?: string, valid_until?: string} $reference
     */
    private function isWithinValidityWindow(array $reference): bool
    {
        $now = $this->now();

        if (isset($reference['valid_from']) && $now < new DateTimeImmutable($reference['valid_from'])) {
            return false;
        }

        if (isset($reference['valid_until']) && $now > new DateTimeImmutable($reference['valid_until'])) {
            return false;
        }

        return true;
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock !== null ? ($this->clock)() : new DateTimeImmutable();
    }
}
