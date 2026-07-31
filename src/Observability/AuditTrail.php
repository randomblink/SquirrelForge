<?php

declare(strict_types=1);

namespace SquirrelForge\Observability;

use SquirrelForge\Contracts\AuthorizationManagerInterface;
use SquirrelForge\Storage\SqliteDocumentStorage;

/**
 * Owns audit-event records and audit evidence references for
 * security-sensitive, governance-relevant, configuration-changing,
 * workflow-critical, integration-relevant, and release-relevant
 * actions, per 27_OBSERVABILITY/AUDIT-TRAIL.md.
 *
 * Rule 3 ("audit record persistence must use owning storage
 * infrastructure") is upheld the same way as Log/Metrics/Trace Manager:
 * no database of its own, genuine delegation to the injected
 * SqliteDocumentStorage as `audit_event` documents. Immutability
 * follows structurally, not by a special guarantee: this class exposes
 * no update method for a stored audit event at all, so nothing in its
 * own API surface can ever mutate one once recorded.
 *
 * Rule 1 ("records supplied audit facts; does not make the domain
 * decision that produced them") is upheld literally: `decision` is a
 * caller-supplied string passed straight into storage, never validated,
 * reinterpreted, or computed against any policy -- this class takes no
 * dependency on SqlitePolicyEngine or any other decision-making
 * component, even though 23_GOVERNANCE is a listed dependency, because
 * recording a governance-relevant fact does not require evaluating one.
 *
 * Rule 2 ("must not contain raw secrets or prohibited sensitive
 * payloads") gets a real two-tier enforcement mirroring
 * TelemetryCollector's own split: a field named in the caller's
 * `prohibited_fields` is grounds for outright rejection of the whole
 * event if present in evidence at all (an absolute "must not contain",
 * not a redaction option), while `sensitive_fields` are redacted only
 * when SqliteObservabilityGovernance's `audit_event` standard requires
 * it -- the same defense-in-depth shape Log/Metrics/Trace Manager
 * already use.
 *
 * The 24_SECURITY dependency is honored on the read side, not the
 * write side: retrieve() and search() check an injected
 * AuthorizationManagerInterface before exposing evidence, since audit
 * records are sensitive by nature and the spec's "Expose audit evidence
 * references to governance, security, compliance, and operational
 * owners" implies exposure is controlled. recordEvent() itself is not
 * gated -- nothing in the spec requires restricting who may submit an
 * auditable fact, and a write-permissive, read-restricted audit trail
 * is the standard shape for this kind of component.
 */
final class AuditTrail
{
    private const REQUIRED_FIELDS = ['actor_ref', 'action', 'resource_ref', 'outcome'];

    public function __construct(
        private readonly ?SqliteDocumentStorage $documentStorage = null,
        private readonly ?SqliteObservabilityGovernance $governance = null,
        private readonly ?AuthorizationManagerInterface $authorization = null
    ) {
    }

    /**
     * @param array{actor_ref: string, action: string, resource_ref: string, outcome: string, decision?: ?string, reason?: ?string, evidence?: array<string, mixed>, timestamp?: string} $event
     * @param array{sensitive_fields?: array<int, string>, prohibited_fields?: array<int, string>} $options
     * @return array{audit_ref: ?string, outcome: string, error: ?string}
     */
    public function recordEvent(array $event, array $options = []): array
    {
        if ($this->documentStorage === null) {
            return ['audit_ref' => null, 'outcome' => 'not_configured', 'error' => 'Document Storage is not configured.'];
        }

        $missingField = $this->firstMissingField($event);

        if ($missingField !== null) {
            return ['audit_ref' => null, 'outcome' => 'invalid_event', 'error' => sprintf('Missing required field "%s".', $missingField)];
        }

        $evidence = $event['evidence'] ?? [];
        $prohibited = array_intersect($options['prohibited_fields'] ?? [], array_keys($evidence));

        if ($prohibited !== []) {
            return ['audit_ref' => null, 'outcome' => 'prohibited_evidence', 'error' => sprintf('Evidence contains prohibited field(s): %s.', implode(', ', $prohibited))];
        }

        $retentionDays = null;

        if ($this->governance !== null) {
            $standard = $this->governance->getStandard('audit_event');
            $retentionDays = $standard['retention_days'] ?? null;

            if (($standard['redaction_required'] ?? false) === true) {
                foreach ($options['sensitive_fields'] ?? [] as $field) {
                    if (array_key_exists($field, $evidence)) {
                        $evidence[$field] = '[REDACTED]';
                    }
                }
            }
        }

        $content = [
            'actor_ref' => $event['actor_ref'],
            'action' => $event['action'],
            'resource_ref' => $event['resource_ref'],
            'decision' => $event['decision'] ?? null,
            'reason' => $event['reason'] ?? null,
            'outcome' => $event['outcome'],
            'evidence' => $evidence,
            'occurred_at' => $event['timestamp'] ?? gmdate(DATE_RFC3339),
        ];

        $result = $this->documentStorage->store('audit_event', sprintf('audit:%s:%s', $event['action'], $event['resource_ref']), $content, [
            'owner' => $event['actor_ref'],
            'metadata' => ['retention_days' => $retentionDays],
        ]);

        if ($result['outcome'] !== 'stored') {
            return ['audit_ref' => null, 'outcome' => 'rejected', 'error' => $result['error']];
        }

        return ['audit_ref' => $result['document_ref'], 'outcome' => 'recorded', 'error' => null];
    }

    /**
     * @param array{identity_ref?: string, permission_ref?: string, correlation_id?: string} $context
     * @return array{found: bool, audit: ?array<string, mixed>, error: ?string}
     */
    public function retrieve(string $auditRef, array $context = []): array
    {
        if ($this->documentStorage === null) {
            return ['found' => false, 'audit' => null, 'error' => 'Document Storage is not configured.'];
        }

        $denied = $this->checkAuthorization('retrieve', $auditRef, $context);

        if ($denied !== null) {
            return ['found' => false, 'audit' => null, 'error' => $denied];
        }

        $retrieved = $this->documentStorage->retrieve($auditRef);

        if (!$retrieved['found']) {
            return ['found' => false, 'audit' => null, 'error' => $retrieved['error']];
        }

        return ['found' => true, 'audit' => $retrieved['content'], 'error' => null];
    }

    /**
     * @param array{actor_ref?: string, action?: string} $filters
     * @param array{identity_ref?: string, permission_ref?: string, correlation_id?: string} $context
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters = [], array $context = []): array
    {
        if ($this->documentStorage === null || $this->checkAuthorization('search', 'audit_event', $context) !== null) {
            return [];
        }

        $searchFilters = ['type' => 'audit_event'];

        if (isset($filters['actor_ref'])) {
            $searchFilters['owner'] = $filters['actor_ref'];
        }

        $records = [];

        foreach ($this->documentStorage->search($searchFilters) as $document) {
            $retrieved = $this->documentStorage->retrieve($document['document_ref']);

            if (!$retrieved['found']) {
                continue;
            }

            $content = $retrieved['content'];

            if (isset($filters['action']) && ($content['action'] ?? null) !== $filters['action']) {
                continue;
            }

            $records[] = $content;
        }

        return $records;
    }

    /**
     * @param array<string, mixed> $event
     */
    private function firstMissingField(array $event): ?string
    {
        foreach (self::REQUIRED_FIELDS as $field) {
            if (!isset($event[$field]) || (is_string($event[$field]) && $event[$field] === '')) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function checkAuthorization(string $operation, string $resourceRef, array $context): ?string
    {
        if ($this->authorization === null) {
            return null;
        }

        $result = $this->authorization->authorize(
            $context['identity_ref'] ?? '',
            $context['permission_ref'] ?? 'audit:' . $operation,
            $operation,
            $resourceRef,
            $context['correlation_id'] ?? ('correlation_' . bin2hex(random_bytes(8)))
        );

        return $result['authorized'] ? null : 'Authorization to access audit evidence was denied.';
    }
}
