<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use SquirrelForge\Agent\AgentPipelineModule;
use SquirrelForge\Tools\ToolRegistry;
use SquirrelForge\Tools\LocalFileSystem;

/**
 * Regression coverage for a real bug found while building ToolSelector:
 * AgentPipelineModule previously registered the three file tools into the
 * ToolRegistry handed to DeveloperAgent without ever calling boot() on them,
 * so isHealthy() silently reported false for all three in production.
 */
final class AgentPipelineFileToolsTest extends TestCase
{
    public function testFileToolsAreBootedAndHealthyAfterRegistryIsBuilt(): void
    {
        $module = new AgentPipelineModule();
        $method = new ReflectionMethod($module, 'buildFileToolRegistry');
        $fileSystem = new LocalFileSystem(sys_get_temp_dir());

        /** @var ToolRegistry $registry */
        $registry = $method->invoke($module, $fileSystem);

        $tools = $registry->all();
        $this->assertCount(3, $tools);

        foreach ($tools as $tool) {
            $this->assertTrue($tool->isHealthy(), sprintf('Tool "%s" should be healthy (booted).', $tool->getId()));
        }

        $ids = array_map(static fn($tool): string => $tool->getId(), $tools);
        sort($ids);
        $this->assertSame(['delete_file', 'read_file', 'write_file'], $ids);
    }
}
