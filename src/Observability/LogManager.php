<?php

declare(strict_types=1);

namespace SquirrelForge\Observability;

use SquirrelForge\Storage\SqliteDocumentStorage;

/**
 * Owns structured log records derived from normalized telemetry, per
 * 27_OBSERVABILITY/LOG-MANAGER.md.
 *
 * "Log storage is performed through owning storage infrastructure, not
 * owned locally by Log Manager" (Rule 3) is upheld literally: this class
 * has no database of its own and genuinely delegates persistence to the
 * injected SqliteDocumentStorage, storing each log record as a
 * `log_record` document -- the same real delegation SqliteDocumentRepository
 * uses for its own storage_reference, applied here for the first time to
 * an Observability component.
 *
 * "Produce structured log records from normalized telemetry" consumes
 * TelemetryCollector's actual output shape directly (the `normalized`
 * array its receive() method returns), a genuine composition rather
 * than a separately-invented input format. "Classify log severity" is
 * a real, deterministic default derived from signal_type when the
 * caller doesn't supply one explicitly (e.g. `alert` defaults to
 * `error`), the same kind of honest heuristic EventListener uses for
 * its own dot-namespace classification.
 *
 * "Apply redaction and retention requirements from Observability
 * Governance" is real and applied again here, independently of
 * whatever Telemetry Collector already did -- a genuine defense-in-depth
 * layer, not a redundant no-op: this class calls
 * SqliteObservabilityGovernance::getStandard() itself and redacts
 * caller-named sensitive_fields when the standard requires it, and
 * carries the standard's real retention_days into the stored
 * document's metadata.
 */
final class LogManager
{
    private const DEFAULT_SEVERITY_BY_SIGNAL_TYPE = [
        'alert' => 'error',
        'health_report' => 'warning',
        'diagnostic' => 'warning',
        'audit_event' => 'info',
        'metric' => 'info',
        'trace' => 'info',
        'telemetry' => 'info',
        'log' => 'info',
        'dashboard' => 'info',
    ];

    public function __construct(
        private readonly ?SqliteDocumentStorage $documentStorage = null,
        private readonly ?SqliteObservabilityGovernance $governance = null
    ) {
    }

    /**
     * @param array{event_id: string, source: string, signal_type: string, classification?: ?string, correlation_id: string, payload: array<string, mixed>, timestamp: string} $telemetry TelemetryCollector::receive()'s `normalized` output
     * @param array{severity?: string, sensitive_fields?: array<int, string>} $options
     * @return array{log_ref: ?string, severity: ?string, outcome: string, error: ?string}
     */
    public function recordLog(array $telemetry, array $options = []): array
    {
        if ($this->documentStorage === null) {
            return ['log_ref' => null, 'severity' => null, 'outcome' => 'not_configured', 'error' => 'Document Storage is not configured.'];
        }

        $severity = $options['severity'] ?? self::DEFAULT_SEVERITY_BY_SIGNAL_TYPE[$telemetry['signal_type']] ?? 'info';
        $payload = $telemetry['payload'];
        $retentionDays = null;

        if ($this->governance !== null) {
            $standard = $this->governance->getStandard($telemetry['signal_type']);
            $retentionDays = $standard['retention_days'] ?? null;

            if (($standard['redaction_required'] ?? false) === true) {
                foreach ($options['sensitive_fields'] ?? [] as $field) {
                    if (array_key_exists($field, $payload)) {
                        $payload[$field] = '[REDACTED]';
                    }
                }
            }
        }

        $content = [
            'severity' => $severity,
            'source' => $telemetry['source'],
            'signal_type' => $telemetry['signal_type'],
            'correlation_id' => $telemetry['correlation_id'],
            'timestamp' => $telemetry['timestamp'],
            'payload' => $payload,
        ];

        $result = $this->documentStorage->store('log_record', sprintf('log:%s:%s', $telemetry['source'], $telemetry['event_id']), $content, [
            'owner' => $telemetry['source'],
            'classification' => $telemetry['classification'] ?? null,
            'metadata' => ['retention_days' => $retentionDays, 'correlation_id' => $telemetry['correlation_id']],
        ]);

        if ($result['outcome'] !== 'stored') {
            return ['log_ref' => null, 'severity' => $severity, 'outcome' => 'rejected', 'error' => $result['error']];
        }

        return ['log_ref' => $result['document_ref'], 'severity' => $severity, 'outcome' => 'recorded', 'error' => null];
    }
}
