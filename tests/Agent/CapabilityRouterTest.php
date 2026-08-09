<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Agent;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SquirrelForge\Agent\CapabilityRouter;

final class CapabilityRouterTest extends TestCase
{
    // --- fail-closed on an unrecognized request type ---

    public function testUnrecognizedRequestTypeIsUnrouted(): void
    {
        $router = new CapabilityRouter();

        $result = $router->route(['request_type' => 'Reticulate the splines']);

        $this->assertSame('unrouted', $result['outcome']);
        $this->assertStringContainsString('goal clarification', $result['error']);
    }

    public function testMissingRequestTypeIsUnrouted(): void
    {
        $router = new CapabilityRouter();

        $result = $router->route([]);

        $this->assertSame('unrouted', $result['outcome']);
    }

    // --- the Primary Routing Table, reproduced verbatim ---

    /**
     * @return array<int, array{0: string, 1: array<int, string>, 2: string, 3: string, 4: string}>
     */
    public static function primaryRouteProvider(): array
    {
        return [
            ['Plan a project', ['14_ENGINE', '19_REASONING', '02_WORKFLOWS', '03_CHECKLISTS'], 'Project Planning', 'Architect / Planner', 'Architecture and project checklist'],
            ['Clean documentation', ['README.md', 'ARCHITECTURE.md', 'affected layer READMEs', '23_GOVERNANCE'], 'Documentation Maintenance', 'Documentation Agent', 'Link/reference check and consistency review'],
            ['Build a plugin', ['38_WORDPRESS', '14_ENGINE', '20_EXECUTION', '29_TESTING', '24_SECURITY'], 'Plugin Development', 'Developer', 'Syntax, unit, integration, security, activation, smoke'],
            ['Build a theme', ['38_WORDPRESS', '14_ENGINE', '20_EXECUTION', '29_TESTING', '24_SECURITY'], 'Theme Development', 'Developer', 'Accessibility, responsive, template, system, smoke'],
            ['Build a block', ['38_WORDPRESS', '26_INTEGRATIONS', '29_TESTING', '24_SECURITY'], 'Block Development', 'Developer', 'Build, editor, frontend, accessibility, smoke'],
            ['Add a feature', ['Relevant domain layer', '14_ENGINE', '19_REASONING', '20_EXECUTION', '29_TESTING'], 'Feature Development', 'Developer', 'Risk-based unit and integration tests'],
            ['Fix a defect', ['Relevant domain layer', '20_EXECUTION', '29_TESTING', '35_RESILIENCE'], 'Bug Fix', 'Developer', 'Reproduction, fix evidence, regression test'],
            ['Review code', ['16_AGENTS', '19_REASONING', '24_SECURITY', '29_TESTING'], 'Code Review', 'Reviewer', 'Review checklist and validation evidence'],
            ['Audit security', ['24_SECURITY', 'relevant domain layer', '23_GOVERNANCE'], 'Security Review', 'Security Agent', 'Findings, severity, remediation, retest evidence'],
            ['Improve performance', ['32_OPTIMIZATION', 'relevant domain layer', '27_OBSERVABILITY', '29_TESTING'], 'Performance Optimization', 'Performance Agent', 'Before/after measurement and regression evidence'],
            ['Review accessibility', ['Relevant domain layer', '29_TESTING', '03_CHECKLISTS'], 'Accessibility Review', 'Reviewer', 'Accessibility evidence and retest'],
            ['Add tests', ['29_TESTING', 'relevant domain layer', '20_EXECUTION'], 'Testing', 'Developer / Reviewer', 'Test report and coverage rationale'],
            ['Write documentation', ['36_COMMUNICATION', '15_TEMPLATES', 'relevant source layers'], 'Documentation', 'Documentation Agent', 'Accuracy, links, metadata, accessibility'],
            ['Prepare release', ['23_GOVERNANCE', '02_WORKFLOWS', '29_TESTING', '35_RESILIENCE'], 'Release', 'Release Agent', 'Smoke, regression, quality gates, rollback readiness'],
            ['Recover from failure', ['35_RESILIENCE', '20_EXECUTION', '27_OBSERVABILITY', '29_TESTING'], 'Recovery', 'Recovery Agent', 'State review, rollback or repair evidence, validation'],
            ['Configure automation', ['33_AUTOMATION', '21_CONFIGURATION', '28_RUNTIME-CONFIG', '23_GOVERNANCE'], 'Automation Setup', 'Automation Agent', 'Trigger, condition, permission, and audit evidence'],
            ['Integrate external tool', ['26_INTEGRATIONS', '24_SECURITY', '21_CONFIGURATION', '29_TESTING'], 'Integration Development', 'Integration Agent', 'Auth, permission, contract, and failure-mode tests'],
            ['Optimize agent behavior', ['34_AIDRIVER', '30_LEARNING', '32_OPTIMIZATION', '23_GOVERNANCE'], 'AI Driver / Optimization', 'AI Driver Agent', 'Evaluation evidence and rollback path'],
        ];
    }

    #[DataProvider('primaryRouteProvider')]
    public function testEachPrimaryRouteMatchesTheSpecTableExactly(string $requestType, array $sourceLayers, string $workflow, string $leadAgent, string $requiredVerification): void
    {
        $router = new CapabilityRouter();

        $result = $router->route(['request_type' => $requestType]);

        $this->assertSame('routed', $result['outcome']);
        $this->assertSame($sourceLayers, $result['source_layers']);
        $this->assertSame($workflow, $result['workflow']);
        $this->assertSame($leadAgent, $result['primary_owner']);
        $this->assertSame($requiredVerification, $result['required_verification']);
        $this->assertSame([], $result['supporting_agents']);
        $this->assertFalse($result['escalation_required']);
    }

    // --- applicable_domains ---

    public function testApplicableDomainsMustBeAnArray(): void
    {
        $router = new CapabilityRouter();

        $result = $router->route(['request_type' => 'Fix a defect', 'applicable_domains' => 'Security']);

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testUnrecognizedDomainIsInvalid(): void
    {
        $router = new CapabilityRouter();

        $result = $router->route(['request_type' => 'Fix a defect', 'applicable_domains' => ['Astrology']]);

        $this->assertSame('invalid', $result['outcome']);
        $this->assertStringContainsString('Astrology', $result['error']);
    }

    public function testApplicableDomainAddsItsSourceLayer(): void
    {
        $router = new CapabilityRouter();

        $result = $router->route(['request_type' => 'Fix a defect', 'applicable_domains' => ['Learning']]);

        $this->assertContains('30_LEARNING', $result['source_layers']);
    }

    public function testApplicableDomainDeduplicatesAnAlreadyPresentSourceLayer(): void
    {
        // "Fix a defect" already includes 29_TESTING; declaring the Testing domain must not duplicate it.
        $router = new CapabilityRouter();

        $result = $router->route(['request_type' => 'Fix a defect', 'applicable_domains' => ['Testing']]);

        $this->assertSame(1, count(array_filter($result['source_layers'], static fn(string $layer): bool => $layer === '29_TESTING')));
    }

    // --- Domain Precedence Rule: WordPress ---

    public function testWordPressDomainMakesTheWordPressManagerThePrimaryOwner(): void
    {
        $router = new CapabilityRouter();

        $result = $router->route(['request_type' => 'Audit security', 'applicable_domains' => ['WordPress']]);

        $this->assertSame('WordPress Manager (38_WORDPRESS/WORDPRESS-MANAGER.md)', $result['primary_owner']);
    }

    public function testWordPressDomainMovesTheOriginalLeadAgentToSupporting(): void
    {
        $router = new CapabilityRouter();

        $result = $router->route(['request_type' => 'Audit security', 'applicable_domains' => ['WordPress']]);

        $this->assertCount(1, $result['supporting_agents']);
        $this->assertStringContainsString('Security Agent', $result['supporting_agents'][0]);
        $this->assertStringContainsString('only if explicitly called', $result['supporting_agents'][0]);
    }

    public function testNonWordPressDomainDoesNotTriggerPrecedence(): void
    {
        $router = new CapabilityRouter();

        $result = $router->route(['request_type' => 'Audit security', 'applicable_domains' => ['Security']]);

        $this->assertSame('Security Agent', $result['primary_owner']);
        $this->assertSame([], $result['supporting_agents']);
    }

    // --- risk_signals ---

    public function testRiskSignalsMustBeAnArray(): void
    {
        $router = new CapabilityRouter();

        $result = $router->route(['request_type' => 'Fix a defect', 'risk_signals' => 'deployment']);

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testUnrecognizedRiskSignalIsInvalid(): void
    {
        $router = new CapabilityRouter();

        $result = $router->route(['request_type' => 'Fix a defect', 'risk_signals' => ['mercury_in_retrograde']]);

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testNoRiskSignalsMeansNoEscalation(): void
    {
        $router = new CapabilityRouter();

        $result = $router->route(['request_type' => 'Fix a defect']);

        $this->assertFalse($result['escalation_required']);
        $this->assertSame([], $result['risk_signals']);
    }

    public function testARealRiskSignalRequiresEscalationAndIsEchoedBack(): void
    {
        $router = new CapabilityRouter();

        $result = $router->route(['request_type' => 'Fix a defect', 'risk_signals' => ['secrets_or_credentials', 'deployment']]);

        $this->assertTrue($result['escalation_required']);
        $this->assertSame(['secrets_or_credentials', 'deployment'], $result['risk_signals']);
    }
}
