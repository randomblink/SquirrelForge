<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Configuration;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Configuration\Defaults;
use SquirrelForge\Configuration\ProjectSettings;
use SquirrelForge\Engine\WorkflowSelector;

final class ProjectSettingsTest extends TestCase
{
    /**
     * @return array{project_name: string, root: string}
     */
    private function minimalSettings(array $overrides = []): array
    {
        return array_replace(['project_name' => 'SquirrelForge', 'root' => '/repo'], $overrides);
    }

    // --- required fields ---

    public function testMissingProjectNameIsInvalid(): void
    {
        $settings = new ProjectSettings();

        $result = $settings->define(['root' => '/repo']);

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testMissingRootIsInvalid(): void
    {
        $settings = new ProjectSettings();

        $result = $settings->define(['project_name' => 'SquirrelForge']);

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testMinimalSettingsAreDefined(): void
    {
        $settings = new ProjectSettings();

        $result = $settings->define($this->minimalSettings());

        $this->assertSame('defined', $result['outcome']);
        $this->assertSame('SquirrelForge', $result['project_name']);
        $this->assertSame('/repo', $result['root']);
    }

    // --- required_workflows cross-check against the real WorkflowSelector ---

    public function testKnownWorkflowIsAccepted(): void
    {
        $settings = new ProjectSettings(null, new WorkflowSelector());

        $result = $settings->define($this->minimalSettings(['required_workflows' => ['BUG-FIX-WORKFLOW']]));

        $this->assertSame('defined', $result['outcome']);
        $this->assertSame(['BUG-FIX-WORKFLOW'], $result['required_workflows']);
    }

    public function testUnknownWorkflowIsRejected(): void
    {
        $settings = new ProjectSettings(null, new WorkflowSelector());

        $result = $settings->define($this->minimalSettings(['required_workflows' => ['MADE-UP-WORKFLOW']]));

        $this->assertSame('invalid', $result['outcome']);
        $this->assertStringContainsString('MADE-UP-WORKFLOW', $result['error']);
    }

    public function testMixOfKnownAndUnknownWorkflowsIsRejected(): void
    {
        $settings = new ProjectSettings(null, new WorkflowSelector());

        $result = $settings->define($this->minimalSettings(['required_workflows' => ['BUG-FIX-WORKFLOW', 'NOT-REAL']]));

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testWithoutAWorkflowSelectorNoCrossCheckHappens(): void
    {
        $settings = new ProjectSettings();

        $result = $settings->define($this->minimalSettings(['required_workflows' => ['ANYTHING-AT-ALL']]));

        $this->assertSame('defined', $result['outcome']);
    }

    // --- overrides: composes Defaults::validateOverride() ---

    public function testAcceptedOverrideIsAppliedByKey(): void
    {
        $settings = new ProjectSettings(new Defaults());

        $result = $settings->define($this->minimalSettings([
            'overrides' => [['key' => 'max_retries', 'value' => 5, 'source' => 'project_settings.json']],
        ]));

        $this->assertSame(['max_retries' => 5], $result['accepted_overrides']);
        $this->assertSame([], $result['rejected_overrides']);
    }

    public function testOverrideWeakeningAMandatoryDefaultIsRejectedNotApplied(): void
    {
        $settings = new ProjectSettings(new Defaults());

        $result = $settings->define($this->minimalSettings([
            'overrides' => [['key' => 'least_privilege', 'value' => false, 'source' => 'project_settings.json']],
        ]));

        $this->assertSame('defined', $result['outcome']);
        $this->assertSame([], $result['accepted_overrides']);
        $this->assertCount(1, $result['rejected_overrides']);
        $this->assertSame('rejected', $result['rejected_overrides'][0]['outcome']);
    }

    public function testOverrideWithoutASourceIsRejected(): void
    {
        $settings = new ProjectSettings(new Defaults());

        $result = $settings->define($this->minimalSettings([
            'overrides' => [['key' => 'max_retries', 'value' => 5, 'source' => null]],
        ]));

        $this->assertCount(1, $result['rejected_overrides']);
        $this->assertSame([], $result['accepted_overrides']);
    }

    public function testOverrideOfAnUnknownKeyIsRejected(): void
    {
        $settings = new ProjectSettings(new Defaults());

        $result = $settings->define($this->minimalSettings([
            'overrides' => [['key' => 'made_up_key', 'value' => 'x', 'source' => 'project_settings.json']],
        ]));

        $this->assertCount(1, $result['rejected_overrides']);
    }

    public function testWithoutADefaultsComponentEveryOverrideIsRejected(): void
    {
        $settings = new ProjectSettings();

        $result = $settings->define($this->minimalSettings([
            'overrides' => [['key' => 'max_retries', 'value' => 5, 'source' => 'project_settings.json']],
        ]));

        $this->assertSame('defined', $result['outcome']);
        $this->assertSame([], $result['accepted_overrides']);
        $this->assertCount(1, $result['rejected_overrides']);
    }

    public function testMultipleOverridesAreEachEvaluatedIndependently(): void
    {
        $settings = new ProjectSettings(new Defaults());

        $result = $settings->define($this->minimalSettings([
            'overrides' => [
                ['key' => 'max_retries', 'value' => 5, 'source' => 'a'],
                ['key' => 'least_privilege', 'value' => false, 'source' => 'a'],
                ['key' => 'output_location', 'value' => '/tmp', 'source' => 'a'],
            ],
        ]));

        $this->assertSame(['max_retries' => 5, 'output_location' => '/tmp'], $result['accepted_overrides']);
        $this->assertCount(1, $result['rejected_overrides']);
    }

    // --- pass-through fields ---

    public function testStandardsAndReleasePolicyAreCarriedForwardOpaquely(): void
    {
        $settings = new ProjectSettings();

        $result = $settings->define($this->minimalSettings([
            'standards' => ['SF-SPEC-013'],
            'release_policy_ref' => 'governance_policy_1',
            'test_commands' => ['composer test'],
            'technology_profile' => ['php', 'sqlite'],
        ]));

        $this->assertSame(['SF-SPEC-013'], $result['standards']);
        $this->assertSame('governance_policy_1', $result['release_policy_ref']);
        $this->assertSame(['composer test'], $result['test_commands']);
        $this->assertSame(['php', 'sqlite'], $result['technology_profile']);
    }
}
