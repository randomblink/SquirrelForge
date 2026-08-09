<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Configuration;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Automation\RuleEngine;
use SquirrelForge\Configuration\SqliteModelConfig;
use SquirrelForge\Governance\SqlitePolicyEngine;

final class SqliteModelConfigTest extends TestCase
{
    /** @var array<int, string> */
    private array $databasePaths = [];

    protected function tearDown(): void
    {
        foreach ($this->databasePaths as $path) {
            foreach ([$path, $path . '-shm', $path . '-wal'] as $candidate) {
                if (is_file($candidate)) {
                    unlink($candidate);
                }
            }
        }
    }

    private function tempPath(string $label): string
    {
        $path = sys_get_temp_dir() . "/squirrelforge-model-config-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function config(?SqlitePolicyEngine $policyEngine = null): SqliteModelConfig
    {
        return new SqliteModelConfig($this->tempPath('db'), $policyEngine);
    }

    /**
     * @return array{model_id: string, max_context_window: int, reserved_response_tokens: int}
     */
    private function minimalDeclaration(array $overrides = []): array
    {
        return array_replace([
            'model_id' => 'claude-sonnet-5',
            'max_context_window' => 200000,
            'reserved_response_tokens' => 8000,
        ], $overrides);
    }

    // --- shape validation ---

    public function testMissingModelIdIsInvalid(): void
    {
        $config = $this->config();

        $result = $config->declare(['max_context_window' => 1000]);

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testMissingMaxContextWindowIsInvalid(): void
    {
        $config = $this->config();

        $result = $config->declare(['model_id' => 'm1']);

        $this->assertSame('invalid', $result['outcome']);
        $this->assertStringContainsString('max_context_window', $result['error']);
    }

    public function testReservedResponseTokensAtOrAboveWindowIsInvalid(): void
    {
        $config = $this->config();

        $result = $config->declare($this->minimalDeclaration(['max_context_window' => 1000, 'reserved_response_tokens' => 1000]));

        $this->assertSame('invalid', $result['outcome']);
        $this->assertStringContainsString('reserved_response_tokens', $result['error']);
    }

    public function testNegativeReservedResponseTokensIsInvalid(): void
    {
        $config = $this->config();

        $result = $config->declare($this->minimalDeclaration(['reserved_response_tokens' => -1]));

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testUnknownSensitivityLevelIsInvalid(): void
    {
        $config = $this->config();

        $result = $config->declare($this->minimalDeclaration(['data_handling_classification' => ['top_secret']]));

        $this->assertSame('invalid', $result['outcome']);
        $this->assertStringContainsString('top_secret', $result['error']);
    }

    public function testValidDeclarationSucceeds(): void
    {
        $config = $this->config();

        $result = $config->declare($this->minimalDeclaration(['data_handling_classification' => ['public', 'internal']]));

        $this->assertSame('declared', $result['outcome']);
        $this->assertNotNull($result['config_id']);
    }

    // --- duplicate handling ---

    public function testDuplicateActiveDeclarationIsRejected(): void
    {
        $config = $this->config();
        $config->declare($this->minimalDeclaration());

        $result = $config->declare($this->minimalDeclaration());

        $this->assertSame('duplicate', $result['outcome']);
    }

    public function testRedeclarationAfterRevokeIsAllowed(): void
    {
        $config = $this->config();
        $config->declare($this->minimalDeclaration());
        $config->revoke('claude-sonnet-5');

        $result = $config->declare($this->minimalDeclaration(['max_context_window' => 250000]));

        $this->assertSame('declared', $result['outcome']);
        $this->assertSame(250000, $config->get('claude-sonnet-5')['max_context_window']);
    }

    // --- fallback: reference and cycle checks ---

    public function testSelfReferentialFallbackIsInvalid(): void
    {
        $config = $this->config();

        $result = $config->declare($this->minimalDeclaration(['fallback_model_id' => 'claude-sonnet-5']));

        $this->assertSame('invalid', $result['outcome']);
        $this->assertStringContainsString('own fallback', $result['error']);
    }

    public function testFallbackToAnUndeclaredModelIsInvalid(): void
    {
        $config = $this->config();

        $result = $config->declare($this->minimalDeclaration(['fallback_model_id' => 'ghost-model']));

        $this->assertSame('invalid', $result['outcome']);
        $this->assertStringContainsString('ghost-model', $result['error']);
    }

    public function testFallbackToARevokedModelIsInvalid(): void
    {
        $config = $this->config();
        $config->declare($this->minimalDeclaration(['model_id' => 'claude-haiku-4-5', 'max_context_window' => 100000, 'reserved_response_tokens' => 4000]));
        $config->revoke('claude-haiku-4-5');

        $result = $config->declare($this->minimalDeclaration(['fallback_model_id' => 'claude-haiku-4-5']));

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testFallbackToAnActiveDeclaredModelSucceeds(): void
    {
        $config = $this->config();
        $config->declare($this->minimalDeclaration(['model_id' => 'claude-haiku-4-5', 'max_context_window' => 100000, 'reserved_response_tokens' => 4000]));

        $result = $config->declare($this->minimalDeclaration(['fallback_model_id' => 'claude-haiku-4-5']));

        $this->assertSame('declared', $result['outcome']);
    }

    public function testDirectFallbackCycleIsRejected(): void
    {
        $config = $this->config();
        $config->declare($this->minimalDeclaration(['model_id' => 'model_a', 'max_context_window' => 1000, 'reserved_response_tokens' => 100]));
        $config->declare($this->minimalDeclaration(['model_id' => 'model_b', 'max_context_window' => 1000, 'reserved_response_tokens' => 100, 'fallback_model_id' => 'model_a']));

        $result = $config->declare($this->minimalDeclaration(['model_id' => 'model_a', 'max_context_window' => 1000, 'reserved_response_tokens' => 100, 'fallback_model_id' => 'model_b']));

        // model_a already declared (active), so this is a duplicate first -- revoke then attempt the cycle for real.
        $this->assertSame('duplicate', $result['outcome']);

        $config->revoke('model_a');
        $cycleAttempt = $config->declare($this->minimalDeclaration(['model_id' => 'model_a', 'max_context_window' => 1000, 'reserved_response_tokens' => 100, 'fallback_model_id' => 'model_b']));

        $this->assertSame('invalid', $cycleAttempt['outcome']);
        $this->assertStringContainsString('cycle', $cycleAttempt['error']);
    }

    public function testIndirectFallbackCycleIsRejected(): void
    {
        $config = $this->config();
        $config->declare($this->minimalDeclaration(['model_id' => 'model_a', 'max_context_window' => 1000, 'reserved_response_tokens' => 100]));
        $config->declare($this->minimalDeclaration(['model_id' => 'model_b', 'max_context_window' => 1000, 'reserved_response_tokens' => 100, 'fallback_model_id' => 'model_a']));
        $config->declare($this->minimalDeclaration(['model_id' => 'model_c', 'max_context_window' => 1000, 'reserved_response_tokens' => 100, 'fallback_model_id' => 'model_b']));
        $config->revoke('model_a');

        $result = $config->declare($this->minimalDeclaration(['model_id' => 'model_a', 'max_context_window' => 1000, 'reserved_response_tokens' => 100, 'fallback_model_id' => 'model_c']));

        $this->assertSame('invalid', $result['outcome']);
        $this->assertStringContainsString('cycle', $result['error']);
    }

    public function testNonCyclicChainOfFallbacksIsAccepted(): void
    {
        $config = $this->config();
        $config->declare($this->minimalDeclaration(['model_id' => 'model_a', 'max_context_window' => 1000, 'reserved_response_tokens' => 100]));

        $result = $config->declare($this->minimalDeclaration(['model_id' => 'model_b', 'max_context_window' => 1000, 'reserved_response_tokens' => 100, 'fallback_model_id' => 'model_a']));

        $this->assertSame('declared', $result['outcome']);
    }

    // --- PolicyEngine composition ---

    public function testDeclareIsRejectedWhenNoPolicyAllowsIt(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $config = $this->config($policyEngine);

        $result = $config->declare($this->minimalDeclaration());

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testDeclareSucceedsWhenPolicyEngineAllows(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('p1', [
            'category' => 'resource_access',
            'priority' => 1,
            'condition' => ['type' => 'boolean', 'field' => 'model_id', 'equals' => true],
            'effect' => 'allow',
        ]);
        $config = $this->config($policyEngine);

        $result = $config->declare($this->minimalDeclaration());

        $this->assertSame('declared', $result['outcome']);
    }

    // --- get() / revoke() / history() ---

    public function testGetReturnsNullForUndeclaredModel(): void
    {
        $config = $this->config();

        $this->assertNull($config->get('ghost'));
    }

    public function testGetReturnsNullAfterRevoke(): void
    {
        $config = $this->config();
        $config->declare($this->minimalDeclaration());
        $config->revoke('claude-sonnet-5');

        $this->assertNull($config->get('claude-sonnet-5'));
    }

    public function testRevokeUnknownModelIsNotFound(): void
    {
        $config = $this->config();

        $result = $config->revoke('ghost');

        $this->assertSame('not_found', $result['outcome']);
    }

    public function testHistoryReturnsEveryDeclarationForAModel(): void
    {
        $config = $this->config();
        $config->declare($this->minimalDeclaration());
        $config->revoke('claude-sonnet-5');
        $config->declare($this->minimalDeclaration(['max_context_window' => 300000]));

        $history = $config->history('claude-sonnet-5');

        $this->assertCount(2, $history);
        $this->assertSame(200000, $history[0]['max_context_window']);
        $this->assertSame(300000, $history[1]['max_context_window']);
    }

    public function testGetHydratesJsonFields(): void
    {
        $config = $this->config();
        $config->declare($this->minimalDeclaration([
            'capability_requirements' => ['tool_calling' => true],
            'data_handling_classification' => ['public'],
        ]));

        $record = $config->get('claude-sonnet-5');

        $this->assertSame(['tool_calling' => true], $record['capability_requirements']);
        $this->assertSame(['public'], $record['data_handling_classification']);
    }
}
