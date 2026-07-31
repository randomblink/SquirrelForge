<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Engine\ContextManager;
use SquirrelForge\Engine\PromptCompiler;

final class PromptCompilerTest extends TestCase
{
    public function testWithoutContextManagerIsNotConfigured(): void
    {
        $compiler = new PromptCompiler();

        $result = $compiler->compile();

        $this->assertSame('not_configured', $result['outcome']);
        $this->assertNull($result['prompt']);
    }

    public function testWithNoLoadedContextEverySectionShowsAPlaceholder(): void
    {
        $compiler = new PromptCompiler(new ContextManager());

        $result = $compiler->compile();

        $this->assertSame('compiled', $result['outcome']);
        $this->assertStringContainsString('_No content loaded for this section._', $result['sections']['Core System Rules']);
        $this->assertStringContainsString('_No goal recorded._', $result['sections']['Current Task']);
        $this->assertStringContainsString('_No evidence recorded._', $result['sections']['Current Task']);
    }

    public function testSectionsAppearInTheExactPromptAssemblyOrder(): void
    {
        $compiler = new PromptCompiler(new ContextManager());

        $result = $compiler->compile();

        $expectedOrder = ['Core System Rules', 'Project Context', 'Domain-Specific Knowledge', 'Active Workflow State', 'Current Task'];
        $positions = array_map(static fn(string $heading): int|false => strpos($result['prompt'], "# {$heading}"), $expectedOrder);

        $this->assertNotContains(false, $positions, 'Every expected section heading must appear in the prompt.');
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions, 'Sections must appear in Prompt Assembly Order.');
        $this->assertStringContainsString("\n\n---\n\n", $result['prompt']);
    }

    public function testLoadedContextIsRenderedUnderTheCorrectSection(): void
    {
        $context = new ContextManager();
        $context->load('agent_behavior', 'mandatory_rules', 'Never delete without confirmation.');
        $context->load('project', 'project_context', ['project_type' => 'php_library']);
        $context->load('domain', 'domain_context', 'WordPress plugin rules apply.');
        $context->load('workflow_state', 'active_state', 'Step 2 of 4 in progress.');
        $context->load('goal', 'user_request', 'Ship the login feature.');
        $context->load('facts', 'task_supporting_context', 'The login form is at src/Login.php.');
        $compiler = new PromptCompiler($context);

        $result = $compiler->compile();

        $this->assertStringContainsString('Never delete without confirmation.', $result['sections']['Core System Rules']);
        $this->assertStringContainsString('php_library', $result['sections']['Project Context']);
        $this->assertStringContainsString('WordPress plugin rules apply.', $result['sections']['Domain-Specific Knowledge']);
        $this->assertStringContainsString('Step 2 of 4 in progress.', $result['sections']['Active Workflow State']);
        $this->assertStringContainsString('Ship the login feature.', $result['sections']['Current Task']);
        $this->assertStringContainsString('The login form is at src/Login.php.', $result['sections']['Current Task']);
    }

    public function testCustomEvidenceTiersOverrideTheDefault(): void
    {
        $context = new ContextManager();
        $context->load('memory_note', 'memory_knowledge', 'Last time this broke tests in CheckoutTest.');
        $compiler = new PromptCompiler($context);

        $result = $compiler->compile(['evidence_tiers' => ['memory_knowledge']]);

        $this->assertStringContainsString('Last time this broke tests in CheckoutTest.', $result['sections']['Current Task']);
    }

    public function testPromptHashIsARealSha256OfTheCompiledPrompt(): void
    {
        $compiler = new PromptCompiler(new ContextManager());

        $result = $compiler->compile();

        $this->assertSame(hash('sha256', $result['prompt']), $result['prompt_hash']);
    }
}
