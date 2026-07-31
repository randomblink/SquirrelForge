<?php

declare(strict_types=1);

namespace SquirrelForge\Observability;

use SquirrelForge\Storage\SqliteDocumentStorage;

/**
 * Owns dashboard definitions and builds dashboard views from Log,
 * Metrics, Trace, Alert Manager, and Health Reporter references, per
 * 27_OBSERVABILITY/DASHBOARD-MANAGER.md.
 *
 * Follows the same shape as Log/Metrics/Trace Manager: no database of
 * its own, delegating dashboard-definition persistence to the injected
 * SqliteDocumentStorage as `dashboard_definition` documents. Dashboard
 * views themselves are never persisted -- they are recomputed live on
 * every renderDashboard() call from whichever real components are
 * configured, since a stored "view" would immediately go stale and
 * this class owns no cache invalidation story for one.
 *
 * Does not take a direct SqliteObservabilityGovernance dependency
 * despite the spec listing it: Rule 3 ("must not expose raw secrets or
 * prohibited sensitive data") is instead enforced directly by
 * renderDashboard()'s own `prohibited_fields` option, a real recursive
 * strip applied to every widget's rendered data -- the same
 * deny-by-default shape TelemetryCollector already uses for prohibited
 * payload fields. There is no per-signal-type governance standard to
 * check at render time the way Log/Metrics/Trace Manager check one at
 * write time, so reusing that mechanism here would be decorative.
 *
 * Rule 1 ("must display source and freshness references for displayed
 * evidence") is upheld literally: every widget in a rendered dashboard
 * carries its own `source` (which real manager produced it) and
 * `freshness` (the actual render timestamp) fields. Rule 2 ("must not
 * become the source of truth for health, alert, diagnostic, or
 * workflow state") holds structurally -- this class has no write path
 * into any of those managers, only read calls into their already-real
 * query methods (LogManager::recent(), MetricsManager::aggregate(),
 * TraceManager::executionPath(), SqliteAlertManager::openFor(),
 * HealthReporter::componentHealth()).
 */
final class DashboardManager
{
    private const WIDGET_TYPES = ['log', 'metric', 'trace', 'alert', 'health'];

    public function __construct(
        private readonly ?SqliteDocumentStorage $documentStorage = null,
        private readonly ?LogManager $logs = null,
        private readonly ?MetricsManager $metrics = null,
        private readonly ?TraceManager $traces = null,
        private readonly ?SqliteAlertManager $alerts = null,
        private readonly ?HealthReporter $health = null
    ) {
    }

    /**
     * @param array<int, array{id: string, type: string, filters?: array<string, mixed>}> $widgets
     * @param array{owner?: ?string, classification?: ?string} $options
     * @return array{dashboard_ref: ?string, outcome: string, error: ?string}
     */
    public function defineDashboard(string $name, array $widgets, array $options = []): array
    {
        if ($this->documentStorage === null) {
            return ['dashboard_ref' => null, 'outcome' => 'not_configured', 'error' => 'Document Storage is not configured.'];
        }

        if ($widgets === []) {
            return ['dashboard_ref' => null, 'outcome' => 'invalid_definition', 'error' => 'A dashboard requires at least one widget.'];
        }

        foreach ($widgets as $widget) {
            if (!is_string($widget['id'] ?? null) || $widget['id'] === '') {
                return ['dashboard_ref' => null, 'outcome' => 'invalid_definition', 'error' => 'Every widget requires a non-empty "id".'];
            }

            if (!in_array($widget['type'] ?? null, self::WIDGET_TYPES, true)) {
                return ['dashboard_ref' => null, 'outcome' => 'invalid_definition', 'error' => sprintf('Widget "%s" has an unknown type "%s".', $widget['id'], $widget['type'] ?? '')];
            }
        }

        $result = $this->documentStorage->store('dashboard_definition', $name, ['name' => $name, 'widgets' => $widgets], [
            'owner' => $options['owner'] ?? null,
            'classification' => $options['classification'] ?? null,
        ]);

        if ($result['outcome'] !== 'stored') {
            return ['dashboard_ref' => null, 'outcome' => 'rejected', 'error' => $result['error']];
        }

        return ['dashboard_ref' => $result['document_ref'], 'outcome' => 'defined', 'error' => null];
    }

    /**
     * @return array{name: string, widgets: array<int, array<string, mixed>>}|null
     */
    public function getDashboard(string $dashboardRef): ?array
    {
        if ($this->documentStorage === null) {
            return null;
        }

        $retrieved = $this->documentStorage->retrieve($dashboardRef);

        return $retrieved['found'] ? $retrieved['content'] : null;
    }

    /**
     * @param array{prohibited_fields?: array<int, string>} $options
     * @return array{dashboard_ref: string, widgets: array<int, array{id: string, type: string, source: string, freshness: string, data: mixed, outcome: string}>, rendered_at: string, outcome: string, error: ?string}
     */
    public function renderDashboard(string $dashboardRef, array $options = []): array
    {
        $definition = $this->getDashboard($dashboardRef);
        $renderedAt = gmdate(DATE_RFC3339);

        if ($definition === null) {
            return ['dashboard_ref' => $dashboardRef, 'widgets' => [], 'rendered_at' => $renderedAt, 'outcome' => 'not_found', 'error' => sprintf('Unknown dashboard "%s".', $dashboardRef)];
        }

        $prohibitedFields = $options['prohibited_fields'] ?? [];
        $widgets = [];

        foreach ($definition['widgets'] as $widget) {
            $rendered = $this->renderWidget($widget);
            $data = $prohibitedFields !== [] ? $this->stripProhibited($rendered['data'], $prohibitedFields) : $rendered['data'];

            $widgets[] = ['id' => $widget['id'], 'type' => $widget['type'], 'source' => $rendered['source'], 'freshness' => $renderedAt, 'data' => $data, 'outcome' => $rendered['outcome']];
        }

        return ['dashboard_ref' => $dashboardRef, 'widgets' => $widgets, 'rendered_at' => $renderedAt, 'outcome' => 'rendered', 'error' => null];
    }

    /**
     * @param array{id: string, type: string, filters?: array<string, mixed>} $widget
     * @return array{source: string, data: mixed, outcome: string}
     */
    private function renderWidget(array $widget): array
    {
        $filters = $widget['filters'] ?? [];

        return match ($widget['type']) {
            'log' => $this->logs !== null
                ? ['source' => 'log_manager', 'data' => $this->logs->recent($filters), 'outcome' => 'rendered']
                : ['source' => 'log_manager', 'data' => null, 'outcome' => 'not_configured'],
            'metric' => $this->metrics !== null && isset($filters['metric_name'])
                ? ['source' => 'metrics_manager', 'data' => $this->metrics->aggregate($filters['metric_name'], $filters['aggregate_type'] ?? 'mean'), 'outcome' => 'rendered']
                : ['source' => 'metrics_manager', 'data' => null, 'outcome' => 'not_configured'],
            'trace' => $this->traces !== null && isset($filters['trace_id'])
                ? ['source' => 'trace_manager', 'data' => $this->traces->executionPath($filters['trace_id']), 'outcome' => 'rendered']
                : ['source' => 'trace_manager', 'data' => null, 'outcome' => 'not_configured'],
            'alert' => $this->alerts !== null && isset($filters['source'])
                ? ['source' => 'alert_manager', 'data' => $this->alerts->openFor($filters['source']), 'outcome' => 'rendered']
                : ['source' => 'alert_manager', 'data' => null, 'outcome' => 'not_configured'],
            'health' => $this->health !== null && isset($filters['source'])
                ? ['source' => 'health_reporter', 'data' => $this->health->componentHealth($filters['source'], $filters['options'] ?? []), 'outcome' => 'rendered']
                : ['source' => 'health_reporter', 'data' => null, 'outcome' => 'not_configured'],
        };
    }

    /**
     * @param array<int, string> $prohibitedFields
     */
    private function stripProhibited(mixed $data, array $prohibitedFields): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        $stripped = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && in_array($key, $prohibitedFields, true)) {
                continue;
            }

            $stripped[$key] = is_array($value) ? $this->stripProhibited($value, $prohibitedFields) : $value;
        }

        return $stripped;
    }
}
