<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\AiDriver\ToolSelector;
use SquirrelForge\Tools\CallbackTool;
use SquirrelForge\Tools\ToolRegistry;

final class ToolSelectorTest extends TestCase
{
    public function testEmptyRequirementMatchesAnyHealthyTool(): void
    {
        $tools = new ToolRegistry();
        $tools->register($this->healthyTool('a', []));

        $selector = new ToolSelector($tools);
        $result = $selector->select([]);

        $this->assertSame('a', $result['selected']);
        $this->assertSame('selected', $result['outcome']);
    }

    public function testMatchesOnSharedCapabilityTag(): void
    {
        $tools = new ToolRegistry();
        $tools->register($this->healthyTool('reader', ['file.read']));
        $tools->register($this->healthyTool('writer', ['file.write']));

        $selector = new ToolSelector($tools);
        $result = $selector->select(['file.write']);

        $this->assertSame('writer', $result['selected']);
    }

    public function testUnhealthyToolIsRejectedAndNeverSelected(): void
    {
        $tools = new ToolRegistry();
        $tools->register($this->unhealthyTool('broken', ['file.read']));

        $selector = new ToolSelector($tools);
        $result = $selector->select(['file.read']);

        $this->assertNull($result['selected']);
        $this->assertSame('unsupported_capability', $result['outcome']);
        $this->assertSame([['id' => 'broken', 'reason' => 'unhealthy']], $result['rejected']);
    }

    public function testHealthyFallbackIsUsedWhenPrimaryIsUnhealthy(): void
    {
        $tools = new ToolRegistry();
        $tools->register($this->unhealthyTool('broken', ['file.read']));
        $tools->register($this->healthyTool('working', ['file.read']));

        $selector = new ToolSelector($tools);
        $result = $selector->select(['file.read']);

        $this->assertSame('working', $result['selected']);
        $this->assertSame([], $result['fallbacks']);
    }

    public function testTwoHealthyMatchesProduceSelectedPlusOneFallback(): void
    {
        $tools = new ToolRegistry();
        $tools->register($this->healthyTool('primary', ['file.read']));
        $tools->register($this->healthyTool('secondary', ['file.read']));

        $selector = new ToolSelector($tools);
        $result = $selector->select(['file.read']);

        $this->assertSame('primary', $result['selected']);
        $this->assertSame(['secondary'], $result['fallbacks']);
    }

    public function testNoMatchingCapabilityIsUnsupported(): void
    {
        $tools = new ToolRegistry();
        $tools->register($this->healthyTool('reader', ['file.read']));

        $selector = new ToolSelector($tools);
        $result = $selector->select(['file.delete']);

        $this->assertNull($result['selected']);
        $this->assertSame('unsupported_capability', $result['outcome']);
        $this->assertSame([], $result['rejected']); // not a candidate at all, not "rejected"
    }

    public function testRecentSelectionsRespectsLimit(): void
    {
        $tools = new ToolRegistry();
        $tools->register($this->healthyTool('a', []));

        $selector = new ToolSelector($tools);

        for ($i = 0; $i < 5; $i++) {
            $selector->select([]);
        }

        $this->assertCount(3, $selector->recentSelections(3));
        $this->assertCount(5, $selector->recentSelections(10));
    }

    private function healthyTool(string $id, array $capabilities): CallbackTool
    {
        $tool = new CallbackTool(
            $id,
            $id,
            'test tool',
            fn(string $op): bool => true,
            fn(string $op, array $params): array => [],
            capabilities: $capabilities
        );
        $tool->boot();

        return $tool;
    }

    private function unhealthyTool(string $id, array $capabilities): CallbackTool
    {
        // Deliberately never call boot() -- CallbackTool::isHealthy() is
        // false until boot() runs, which is exactly what we want here.
        return new CallbackTool(
            $id,
            $id,
            'test tool',
            fn(string $op): bool => true,
            fn(string $op, array $params): array => [],
            capabilities: $capabilities
        );
    }
}
