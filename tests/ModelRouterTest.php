<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\AiDriver\ModelRouter;

final class ModelRouterTest extends TestCase
{
    public function testLowestCostPicksHaikuWhenEverythingFits(): void
    {
        $router = new ModelRouter();

        $result = $router->route(5000, 'lowest_cost');

        $this->assertSame('claude-haiku-4-5', $result['selected']);
        $this->assertSame('selected', $result['outcome']);
        $this->assertSame(
            ['claude-sonnet-5', 'claude-opus-5', 'claude-fable-5'],
            $result['fallbacks']
        );
    }

    public function testHighestQualityPicksFableWhenEverythingFits(): void
    {
        $router = new ModelRouter();

        $result = $router->route(5000, 'highest_quality');

        $this->assertSame('claude-fable-5', $result['selected']);
        $this->assertSame(
            ['claude-opus-5', 'claude-sonnet-5', 'claude-haiku-4-5'],
            $result['fallbacks']
        );
    }

    public function testPromptExceedingHaikuContextExcludesItButOthersRemainEligible(): void
    {
        $router = new ModelRouter();

        $result = $router->route(500_000, 'lowest_cost');

        $this->assertSame('claude-sonnet-5', $result['selected']);
        $this->assertContains(['id' => 'claude-haiku-4-5', 'reason' => 'context_window_exceeded'], $result['rejected']);
        $this->assertCount(1, $result['rejected']);
    }

    public function testPromptExceedingEveryModelIsUnsupported(): void
    {
        $router = new ModelRouter();

        $result = $router->route(2_000_000);

        $this->assertNull($result['selected']);
        $this->assertSame('unsupported_context_size', $result['outcome']);
        $this->assertCount(4, $result['rejected']);
        $this->assertSame([], $result['fallbacks']);
    }

    public function testUnknownStrategyFallsBackToLowestCost(): void
    {
        $router = new ModelRouter();

        $result = $router->route(5000, 'fastest');

        $this->assertSame('claude-haiku-4-5', $result['selected']);
    }

    public function testRecentRoutingDecisionsRespectsLimit(): void
    {
        $router = new ModelRouter();

        for ($i = 0; $i < 5; $i++) {
            $router->route(5000);
        }

        $this->assertCount(3, $router->recentRoutingDecisions(3));
        $this->assertCount(5, $router->recentRoutingDecisions(10));
    }
}
