<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Agent\Roles\ArchitectAgent;
use SquirrelForge\Agent\Roles\DocumentationAgent;
use SquirrelForge\Agent\Roles\PlannerAgent;
use SquirrelForge\Agent\Roles\ReviewerAgent;
use SquirrelForge\Tests\Support\FakeLlmClient;

/**
 * Covers the "reason only when the caller didn't already say" contract
 * added to the role agents: explicit context fields must always win over
 * an LLM's guess, and the LLM must only be consulted for fields that are
 * genuinely missing.
 */
final class LlmReasoningTest extends TestCase
{
    public function testArchitectDoesNotCallLlmWhenAllFieldsAreExplicit(): void
    {
        $fake = new FakeLlmClient(function (): string {
            $this->fail('LLM should not have been called when every blueprint field is explicit.');
        });

        $agent = new ArchitectAgent($fake);
        $agent->boot();

        $result = $agent->execute([
            'stage' => 'architect',
            'goal' => 'Build a caching plugin.',
            'project_type' => 'Plugin',
            'components' => ['Cache Manager'],
            'dependencies' => [],
            'risks' => [],
            'primary_workflow' => 'PLUGIN-DEVELOPMENT-WORKFLOW',
            'supporting_workflows' => [],
            'expected_output' => 'Installable plugin.',
        ]);

        $this->assertSame([], $fake->calls);
        $this->assertSame('Plugin', $result['blueprint']['project_type']);
    }

    public function testArchitectUsesLlmForMissingFieldsOnly(): void
    {
        $fake = new FakeLlmClient(json_encode([
            'project_type' => 'Theme',
            'components' => ['Should be ignored: project_type was explicit'],
            'dependencies' => ['Transients API'],
            'risks' => [],
            'primary_workflow' => 'PLUGIN-DEVELOPMENT-WORKFLOW',
            'supporting_workflows' => [],
            'output' => 'Installable plugin.',
        ], JSON_THROW_ON_ERROR));

        $agent = new ArchitectAgent($fake);
        $agent->boot();

        $result = $agent->execute([
            'stage' => 'architect',
            'goal' => 'Build a caching plugin.',
            'project_type' => 'Plugin', // explicit: must not be overwritten by the LLM's "Theme".
        ]);

        $this->assertCount(1, $fake->calls);
        $this->assertSame('Plugin', $result['blueprint']['project_type']);
        $this->assertSame(['Transients API'], $result['blueprint']['dependencies']);
    }

    public function testThrowsWhenLlmReturnsInvalidJson(): void
    {
        $fake = new FakeLlmClient('this is not json');
        $agent = new ArchitectAgent($fake);
        $agent->boot();

        $this->expectException(\RuntimeException::class);

        $agent->execute(['stage' => 'architect', 'goal' => 'Build something.']);
    }

    public function testThrowsWhenLlmOmitsARequestedField(): void
    {
        $fake = new FakeLlmClient(json_encode(['project_type' => 'Plugin'], JSON_THROW_ON_ERROR));
        $agent = new ArchitectAgent($fake);
        $agent->boot();

        $this->expectException(\RuntimeException::class);

        $agent->execute(['stage' => 'architect', 'goal' => 'Build something.']);
    }

    public function testStripsMarkdownCodeFenceFromLlmResponse(): void
    {
        $fake = new FakeLlmClient("```json\n" . json_encode(['issues' => []], JSON_THROW_ON_ERROR) . "\n```");

        $agent = new ReviewerAgent($fake);
        $agent->boot();

        $result = $agent->execute([
            'stage' => 'reviewer',
            'history' => ['developer' => ['implementation' => ['tasks' => []]]],
        ]);

        $this->assertSame('APPROVED', $result['status']);
    }

    public function testReviewerAsksLlmOnlyWhenIssuesAreNotExplicit(): void
    {
        $fake = new FakeLlmClient(json_encode(['issues' => ['Missing nonce check.']], JSON_THROW_ON_ERROR));

        $agent = new ReviewerAgent($fake);
        $agent->boot();

        $result = $agent->execute([
            'stage' => 'reviewer',
            'history' => ['developer' => ['implementation' => ['tasks' => []]]],
        ]);

        $this->assertCount(1, $fake->calls);
        $this->assertSame(['Missing nonce check.'], $result['review']['issues']);
        $this->assertSame('REVISION_REQUIRED', $result['status']);
    }

    public function testReviewerRespectsExplicitEmptyIssuesWithoutCallingLlm(): void
    {
        $fake = new FakeLlmClient(function (): string {
            $this->fail('LLM should not be called when issues were explicitly supplied.');
        });

        $agent = new ReviewerAgent($fake);
        $agent->boot();

        $result = $agent->execute([
            'stage' => 'reviewer',
            'history' => ['developer' => ['implementation' => ['tasks' => []]]],
            'issues' => [],
        ]);

        $this->assertSame([], $fake->calls);
        $this->assertSame('APPROVED', $result['status']);
    }

    public function testPlannerAsksLlmForPhasesWhenNotSupplied(): void
    {
        $fake = new FakeLlmClient(json_encode([
            'phases' => [
                [
                    'phase' => 'Core',
                    'task' => 'Implement cache manager',
                    'agent' => 'developer',
                    'dependencies' => [],
                    'validation' => 'Unit tests pass',
                    'status' => 'Pending',
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $agent = new PlannerAgent($fake);
        $agent->boot();

        $result = $agent->execute([
            'stage' => 'planner',
            'history' => ['architect' => ['blueprint' => ['goal' => 'Build a caching plugin.']]],
        ]);

        $this->assertCount(1, $fake->calls);
        $this->assertCount(1, $result['plan']);
        $this->assertSame('Core', $result['plan'][0]['phase']);
    }

    public function testDocumentationAsksLlmWhenUpdatesNotSupplied(): void
    {
        $fake = new FakeLlmClient(json_encode([
            'updates' => ['README.md'],
            'checklist' => ['readme' => true, 'changelog' => true, 'release_notes' => true],
            'limitations' => [],
        ], JSON_THROW_ON_ERROR));

        $agent = new DocumentationAgent($fake);
        $agent->boot();

        $result = $agent->execute([
            'stage' => 'documentation',
            'history' => [
                'architect' => ['blueprint' => ['goal' => 'Build a caching plugin.']],
                'performance' => ['status' => 'APPROVED'],
                'developer' => ['implementation' => ['tasks' => []]],
            ],
        ]);

        $this->assertCount(1, $fake->calls);
        $this->assertSame('COMPLETE', $result['status']);
        $this->assertSame(['README.md'], $result['documentation']['updates']);
    }
}
