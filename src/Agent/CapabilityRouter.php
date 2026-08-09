<?php

declare(strict_types=1);

namespace SquirrelForge\Agent;

/**
 * Maps an incoming request to the correct source layers, workflow,
 * lead agent, and required verification, per
 * 12_AGENT/CAPABILITY-ROUTER.md -- closing the first of three real
 * gaps a fresh audit-then-fork pass found.
 *
 * "It prevents the agent from choosing capabilities by habit or
 * loading unrelated domain rules" (Purpose) is upheld by treating the
 * Primary Routing Table and Domain Routing table as real, closed,
 * literal data -- every one of the spec's own 18 request types and 8
 * domains is reproduced verbatim as a PHP constant, never
 * approximated or summarized. "If no route fits, return to goal
 * clarification... do not improvise an undocumented workflow"
 * (Selection Rule 8) is a real, fail-closed guard: an unrecognized
 * `request_type` produces `unrouted`, never a best-guess fallback.
 *
 * Like `TaskRouter`, this class has no database: a routing decision is
 * a pure return value for the caller to act on, not persisted state
 * this class itself owns.
 *
 * The Domain Precedence Rule's WordPress override is applied literally
 * -- when the caller declares `WordPress` among `applicable_domains`,
 * `38_WORDPRESS/WORDPRESS-MANAGER.md` becomes the `primary_owner` and
 * the Primary Routing Table's own Lead Agent moves to
 * `supporting_agents`, exactly the shape the spec's own Precedence
 * Examples show ("Supporting: `16_AGENTS/AGENT-SECURITY.md` only if
 * explicitly called by the WordPress route"). Detecting *whether* a
 * request is WordPress-specific from free text is a real judgment call
 * this class cannot perform, so `applicable_domains` is required
 * caller-supplied evidence, validated against the spec's own eight
 * named domains -- never inferred.
 *
 * Risk Escalation is read literally too: the spec names thirteen
 * trigger conditions but never a deterministic signal-to-addition
 * mapping ("escalation *may* add Security, Governance, Resilience,
 * Observability, Testing, or manual approval" -- an open set, not a
 * function of which specific signal fired). Rather than fabricate that
 * missing mapping, `risk_signals` are validated against the real
 * closed vocabulary and echoed back verbatim alongside a real
 * `escalation_required` flag; which specific addition applies is left
 * to the caller, the same way `VERSIONING.md`'s own ambiguous Change
 * Classification rows are left to human judgment rather than coded.
 */
final class CapabilityRouter
{
    /** The Primary Routing Table, reproduced verbatim. */
    private const PRIMARY_ROUTES = [
        'Plan a project' => [
            'source_layers' => ['14_ENGINE', '19_REASONING', '02_WORKFLOWS', '03_CHECKLISTS'],
            'workflow' => 'Project Planning',
            'lead_agent' => 'Architect / Planner',
            'required_verification' => 'Architecture and project checklist',
        ],
        'Clean documentation' => [
            'source_layers' => ['README.md', 'ARCHITECTURE.md', 'affected layer READMEs', '23_GOVERNANCE'],
            'workflow' => 'Documentation Maintenance',
            'lead_agent' => 'Documentation Agent',
            'required_verification' => 'Link/reference check and consistency review',
        ],
        'Build a plugin' => [
            'source_layers' => ['38_WORDPRESS', '14_ENGINE', '20_EXECUTION', '29_TESTING', '24_SECURITY'],
            'workflow' => 'Plugin Development',
            'lead_agent' => 'Developer',
            'required_verification' => 'Syntax, unit, integration, security, activation, smoke',
        ],
        'Build a theme' => [
            'source_layers' => ['38_WORDPRESS', '14_ENGINE', '20_EXECUTION', '29_TESTING', '24_SECURITY'],
            'workflow' => 'Theme Development',
            'lead_agent' => 'Developer',
            'required_verification' => 'Accessibility, responsive, template, system, smoke',
        ],
        'Build a block' => [
            'source_layers' => ['38_WORDPRESS', '26_INTEGRATIONS', '29_TESTING', '24_SECURITY'],
            'workflow' => 'Block Development',
            'lead_agent' => 'Developer',
            'required_verification' => 'Build, editor, frontend, accessibility, smoke',
        ],
        'Add a feature' => [
            'source_layers' => ['Relevant domain layer', '14_ENGINE', '19_REASONING', '20_EXECUTION', '29_TESTING'],
            'workflow' => 'Feature Development',
            'lead_agent' => 'Developer',
            'required_verification' => 'Risk-based unit and integration tests',
        ],
        'Fix a defect' => [
            'source_layers' => ['Relevant domain layer', '20_EXECUTION', '29_TESTING', '35_RESILIENCE'],
            'workflow' => 'Bug Fix',
            'lead_agent' => 'Developer',
            'required_verification' => 'Reproduction, fix evidence, regression test',
        ],
        'Review code' => [
            'source_layers' => ['16_AGENTS', '19_REASONING', '24_SECURITY', '29_TESTING'],
            'workflow' => 'Code Review',
            'lead_agent' => 'Reviewer',
            'required_verification' => 'Review checklist and validation evidence',
        ],
        'Audit security' => [
            'source_layers' => ['24_SECURITY', 'relevant domain layer', '23_GOVERNANCE'],
            'workflow' => 'Security Review',
            'lead_agent' => 'Security Agent',
            'required_verification' => 'Findings, severity, remediation, retest evidence',
        ],
        'Improve performance' => [
            'source_layers' => ['32_OPTIMIZATION', 'relevant domain layer', '27_OBSERVABILITY', '29_TESTING'],
            'workflow' => 'Performance Optimization',
            'lead_agent' => 'Performance Agent',
            'required_verification' => 'Before/after measurement and regression evidence',
        ],
        'Review accessibility' => [
            'source_layers' => ['Relevant domain layer', '29_TESTING', '03_CHECKLISTS'],
            'workflow' => 'Accessibility Review',
            'lead_agent' => 'Reviewer',
            'required_verification' => 'Accessibility evidence and retest',
        ],
        'Add tests' => [
            'source_layers' => ['29_TESTING', 'relevant domain layer', '20_EXECUTION'],
            'workflow' => 'Testing',
            'lead_agent' => 'Developer / Reviewer',
            'required_verification' => 'Test report and coverage rationale',
        ],
        'Write documentation' => [
            'source_layers' => ['36_COMMUNICATION', '15_TEMPLATES', 'relevant source layers'],
            'workflow' => 'Documentation',
            'lead_agent' => 'Documentation Agent',
            'required_verification' => 'Accuracy, links, metadata, accessibility',
        ],
        'Prepare release' => [
            'source_layers' => ['23_GOVERNANCE', '02_WORKFLOWS', '29_TESTING', '35_RESILIENCE'],
            'workflow' => 'Release',
            'lead_agent' => 'Release Agent',
            'required_verification' => 'Smoke, regression, quality gates, rollback readiness',
        ],
        'Recover from failure' => [
            'source_layers' => ['35_RESILIENCE', '20_EXECUTION', '27_OBSERVABILITY', '29_TESTING'],
            'workflow' => 'Recovery',
            'lead_agent' => 'Recovery Agent',
            'required_verification' => 'State review, rollback or repair evidence, validation',
        ],
        'Configure automation' => [
            'source_layers' => ['33_AUTOMATION', '21_CONFIGURATION', '28_RUNTIME-CONFIG', '23_GOVERNANCE'],
            'workflow' => 'Automation Setup',
            'lead_agent' => 'Automation Agent',
            'required_verification' => 'Trigger, condition, permission, and audit evidence',
        ],
        'Integrate external tool' => [
            'source_layers' => ['26_INTEGRATIONS', '24_SECURITY', '21_CONFIGURATION', '29_TESTING'],
            'workflow' => 'Integration Development',
            'lead_agent' => 'Integration Agent',
            'required_verification' => 'Auth, permission, contract, and failure-mode tests',
        ],
        'Optimize agent behavior' => [
            'source_layers' => ['34_AIDRIVER', '30_LEARNING', '32_OPTIMIZATION', '23_GOVERNANCE'],
            'workflow' => 'AI Driver / Optimization',
            'lead_agent' => 'AI Driver Agent',
            'required_verification' => 'Evaluation evidence and rollback path',
        ],
    ];

    /** The Domain Routing table, reproduced verbatim. */
    private const DOMAIN_SOURCE_LAYERS = [
        'WordPress' => '38_WORDPRESS',
        'Security' => '24_SECURITY',
        'Testing' => '29_TESTING',
        'Governance' => '23_GOVERNANCE',
        'Observability' => '27_OBSERVABILITY',
        'Learning' => '30_LEARNING',
        'Automation' => '33_AUTOMATION',
        'Runtime Config' => '28_RUNTIME-CONFIG',
    ];

    /** The Risk Escalation bullet list, reproduced as a real, closed vocabulary. */
    private const RISK_SIGNALS = [
        'production_systems', 'database_writes', 'destructive_file_operations', 'authentication_or_authorization',
        'secrets_or_credentials', 'deployment', 'dependency_upgrades', 'schema_changes', 'external_apis',
        'payment_or_order_data', 'personal_or_sensitive_data', 'irreversible_actions', 'incomplete_previous_work',
    ];

    /**
     * @param array{
     *     request_type?: ?string,
     *     applicable_domains?: array<int, string>,
     *     risk_signals?: array<int, string>
     * } $request
     * @return array{
     *     outcome: string,
     *     primary_owner: ?string,
     *     supporting_agents: array<int, string>,
     *     source_layers: array<int, string>,
     *     workflow: ?string,
     *     required_verification: ?string,
     *     escalation_required: bool,
     *     risk_signals: array<int, string>,
     *     error: ?string
     * }
     */
    public function route(array $request): array
    {
        $requestType = $request['request_type'] ?? null;

        if (!is_string($requestType) || !isset(self::PRIMARY_ROUTES[$requestType])) {
            return $this->envelope('unrouted', null, [], [], null, null, false, [], 'No route fits this request type; return to goal clarification rather than improvising an undocumented workflow.');
        }

        $applicableDomains = $request['applicable_domains'] ?? [];

        if (!is_array($applicableDomains)) {
            return $this->envelope('invalid', null, [], [], null, null, false, [], 'applicable_domains must be an array of this spec\'s own named Domain Routing values.');
        }

        $unrecognizedDomains = array_diff($applicableDomains, array_keys(self::DOMAIN_SOURCE_LAYERS));

        if ($unrecognizedDomains !== []) {
            return $this->envelope('invalid', null, [], [], null, null, false, [], sprintf('Unrecognized domain(s): %s.', implode(', ', $unrecognizedDomains)));
        }

        $riskSignals = $request['risk_signals'] ?? [];

        if (!is_array($riskSignals)) {
            return $this->envelope('invalid', null, [], [], null, null, false, [], 'risk_signals must be an array of this spec\'s own named Risk Escalation values.');
        }

        $unrecognizedSignals = array_diff($riskSignals, self::RISK_SIGNALS);

        if ($unrecognizedSignals !== []) {
            return $this->envelope('invalid', null, [], [], null, null, false, [], sprintf('Unrecognized risk signal(s): %s.', implode(', ', $unrecognizedSignals)));
        }

        $route = self::PRIMARY_ROUTES[$requestType];
        $sourceLayers = $route['source_layers'];

        foreach ($applicableDomains as $domain) {
            $sourceLayers[] = self::DOMAIN_SOURCE_LAYERS[$domain];
        }

        $sourceLayers = array_values(array_unique($sourceLayers));

        // Domain Precedence Rule: WordPress work is owned by the WordPress Manager,
        // and the Primary Routing Table's own Lead Agent becomes a conditional supporting specialist.
        if (in_array('WordPress', $applicableDomains, true)) {
            $primaryOwner = 'WordPress Manager (38_WORDPRESS/WORDPRESS-MANAGER.md)';
            $supportingAgents = [sprintf('%s, only if explicitly called by the WordPress route', $route['lead_agent'])];
        } else {
            $primaryOwner = $route['lead_agent'];
            $supportingAgents = [];
        }

        return $this->envelope(
            'routed',
            $primaryOwner,
            $supportingAgents,
            $sourceLayers,
            $route['workflow'],
            $route['required_verification'],
            $riskSignals !== [],
            $riskSignals,
            null
        );
    }

    /**
     * @param array<int, string> $supportingAgents
     * @param array<int, string> $sourceLayers
     * @param array<int, string> $riskSignals
     * @return array{
     *     outcome: string,
     *     primary_owner: ?string,
     *     supporting_agents: array<int, string>,
     *     source_layers: array<int, string>,
     *     workflow: ?string,
     *     required_verification: ?string,
     *     escalation_required: bool,
     *     risk_signals: array<int, string>,
     *     error: ?string
     * }
     */
    private function envelope(
        string $outcome,
        ?string $primaryOwner,
        array $supportingAgents,
        array $sourceLayers,
        ?string $workflow,
        ?string $requiredVerification,
        bool $escalationRequired,
        array $riskSignals,
        ?string $error
    ): array {
        return [
            'outcome' => $outcome,
            'primary_owner' => $primaryOwner,
            'supporting_agents' => $supportingAgents,
            'source_layers' => $sourceLayers,
            'workflow' => $workflow,
            'required_verification' => $requiredVerification,
            'escalation_required' => $escalationRequired,
            'risk_signals' => $riskSignals,
            'error' => $error,
        ];
    }
}
