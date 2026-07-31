<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Engine\DependencyAnalyzer;

final class DependencyAnalyzerTest extends TestCase
{
    /** @var array<int, string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
    }

    private function tempFile(): string
    {
        $path = sys_get_temp_dir() . '/sfda-fixture-' . bin2hex(random_bytes(8)) . '.txt';
        file_put_contents($path, 'fixture');
        $this->tempFiles[] = $path;

        return $path;
    }

    public function testAnalyzeWithNoDependenciesReportsThatOutcome(): void
    {
        $analyzer = new DependencyAnalyzer();

        $result = $analyzer->analyze([]);

        $this->assertSame('no_dependencies', $result['outcome']);
    }

    public function testAnalyzeRejectsADuplicateDependencyId(): void
    {
        $analyzer = new DependencyAnalyzer();

        $result = $analyzer->analyze([
            ['id' => 'dep_a', 'name' => 'A', 'type' => 'tool'],
            ['id' => 'dep_a', 'name' => 'A again', 'type' => 'tool'],
        ]);

        $this->assertSame('invalid_graph', $result['outcome']);
        $this->assertStringContainsString('Duplicate', $result['error']);
    }

    public function testAnalyzeRejectsAnUnknownDependsOnReference(): void
    {
        $analyzer = new DependencyAnalyzer();

        $result = $analyzer->analyze([
            ['id' => 'dep_a', 'name' => 'A', 'type' => 'tool', 'depends_on' => ['dep_ghost']],
        ]);

        $this->assertSame('invalid_graph', $result['outcome']);
    }

    public function testAnalyzeRejectsACycle(): void
    {
        $analyzer = new DependencyAnalyzer();

        $result = $analyzer->analyze([
            ['id' => 'dep_a', 'name' => 'A', 'type' => 'tool', 'depends_on' => ['dep_b']],
            ['id' => 'dep_b', 'name' => 'B', 'type' => 'tool', 'depends_on' => ['dep_a']],
        ]);

        $this->assertSame('invalid_graph', $result['outcome']);
        $this->assertStringContainsString('cycle', $result['error']);
    }

    public function testAnExistingFileDependencyIsReallyCheckedAsAvailable(): void
    {
        $analyzer = new DependencyAnalyzer();
        $path = $this->tempFile();

        $result = $analyzer->analyze([
            ['id' => 'dep_a', 'name' => 'config file', 'type' => 'file', 'path' => $path],
        ]);

        $this->assertSame('clear', $result['outcome']);
        $this->assertSame('AVAILABLE', $result['dependencies'][0]['status']);
        $this->assertSame('informational', $result['dependencies'][0]['severity']);
    }

    public function testAMissingFileDependencyProducesARealBlocker(): void
    {
        $analyzer = new DependencyAnalyzer();

        $result = $analyzer->analyze([
            ['id' => 'dep_a', 'name' => 'config file', 'type' => 'file', 'path' => '/nonexistent/path.txt'],
        ]);

        $this->assertSame('blocked', $result['outcome']);
        $this->assertSame('MISSING', $result['dependencies'][0]['status']);
        $this->assertSame('blocking', $result['dependencies'][0]['severity']);
        $this->assertCount(1, $result['blockers']);
        $this->assertStringContainsString('Provide or install', $result['blockers'][0]['next_safe_action']);
    }

    public function testANonRequiredMissingDependencyIsInformationalNotABlocker(): void
    {
        $analyzer = new DependencyAnalyzer();

        $result = $analyzer->analyze([
            ['id' => 'dep_a', 'name' => 'nice to have', 'type' => 'tool', 'required' => false, 'status' => 'missing'],
        ]);

        $this->assertSame('clear', $result['outcome']);
        $this->assertSame('informational', $result['dependencies'][0]['severity']);
    }

    public function testAPermissionConflictIsEscalatedToCritical(): void
    {
        $analyzer = new DependencyAnalyzer();

        $result = $analyzer->analyze([
            ['id' => 'dep_a', 'name' => 'write access', 'type' => 'permission', 'status' => 'conflict'],
        ]);

        $this->assertSame('critical', $result['dependencies'][0]['severity']);
        $this->assertCount(1, $result['blockers']);
    }

    public function testAnUnrecognizedStatusStringDefaultsHonestlyToUnknown(): void
    {
        $analyzer = new DependencyAnalyzer();

        $result = $analyzer->analyze([
            ['id' => 'dep_a', 'name' => 'mystery tool', 'type' => 'tool', 'status' => 'not_a_real_status'],
        ]);

        $this->assertSame('UNKNOWN', $result['dependencies'][0]['status']);
        $this->assertSame('limiting', $result['dependencies'][0]['severity']);
        $this->assertSame('clear', $result['outcome']);
    }

    public function testAWaivedDependencyIsInformationalEvenWhenRequired(): void
    {
        $analyzer = new DependencyAnalyzer();

        $result = $analyzer->analyze([
            ['id' => 'dep_a', 'name' => 'legacy tool', 'type' => 'tool', 'status' => 'waived'],
        ]);

        $this->assertSame('informational', $result['dependencies'][0]['severity']);
        $this->assertSame('clear', $result['outcome']);
    }

    public function testDependenciesAreSortedByTheSpecsFixedPriorityOrder(): void
    {
        $analyzer = new DependencyAnalyzer();

        $result = $analyzer->analyze([
            ['id' => 'dep_external', 'name' => 'External API', 'type' => 'external', 'status' => 'available'],
            ['id' => 'dep_permission', 'name' => 'Write access', 'type' => 'permission', 'status' => 'available'],
            ['id' => 'dep_file', 'name' => 'Config', 'type' => 'file', 'status' => 'available'],
        ]);

        $this->assertSame(['dep_permission', 'dep_file', 'dep_external'], array_column($result['dependencies'], 'id'));
    }

    public function testLevelsReflectDependsOnOrdering(): void
    {
        $analyzer = new DependencyAnalyzer();

        $result = $analyzer->analyze([
            ['id' => 'dep_a', 'name' => 'A', 'type' => 'tool', 'status' => 'available'],
            ['id' => 'dep_b', 'name' => 'B', 'type' => 'tool', 'status' => 'available', 'depends_on' => ['dep_a']],
        ]);

        $this->assertSame([['dep_a'], ['dep_b']], $result['levels']);
    }
}
