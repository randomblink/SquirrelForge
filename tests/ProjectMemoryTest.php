<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Memory\InMemoryStore;
use SquirrelForge\Memory\ProjectMemory;

final class ProjectMemoryTest extends TestCase
{
    public function testInitializeProjectWithoutAStoreIsNotConfigured(): void
    {
        $memory = new ProjectMemory();

        $result = $memory->initializeProject('SquirrelForge');

        $this->assertSame('not_configured', $result['outcome']);
    }

    public function testInitializeProjectCreatesTheRecordOnce(): void
    {
        $memory = new ProjectMemory(new InMemoryStore());

        $first = $memory->initializeProject('SquirrelForge', ['architecture' => 'arch_ref_1']);
        $second = $memory->initializeProject('SquirrelForge');

        $this->assertSame('initialized', $first['outcome']);
        $this->assertSame('already_exists', $second['outcome']);
        $this->assertSame($first['record_id'], $second['record_id']);
    }

    public function testGetReturnsNullForAnUninitializedProject(): void
    {
        $memory = new ProjectMemory(new InMemoryStore());

        $this->assertNull($memory->get('UnknownProject'));
    }

    public function testGetWithoutAStoreReturnsNull(): void
    {
        $memory = new ProjectMemory();

        $this->assertNull($memory->get('SquirrelForge'));
    }

    public function testReferenceMethodsRequireAnInitializedProject(): void
    {
        $memory = new ProjectMemory(new InMemoryStore());

        $this->assertSame('not_found', $memory->referenceDecision('SquirrelForge', 'decision_1')['outcome']);
    }

    public function testReferenceDecisionWithoutAStoreIsNotConfigured(): void
    {
        $memory = new ProjectMemory();

        $this->assertSame('not_configured', $memory->referenceDecision('SquirrelForge', 'decision_1')['outcome']);
    }

    public function testReferenceMethodsAccumulateRatherThanOverwrite(): void
    {
        $memory = new ProjectMemory(new InMemoryStore());
        $memory->initializeProject('SquirrelForge');

        $memory->referenceDecision('SquirrelForge', 'decision_1');
        $memory->referenceDecision('SquirrelForge', 'decision_2');
        $memory->referenceMilestone('SquirrelForge', 'milestone_1');
        $memory->referenceRisk('SquirrelForge', 'risk_1');
        $memory->referenceDependency('SquirrelForge', 'dependency_1');
        $memory->addNote('SquirrelForge', 'Initial architecture review complete.');

        $record = $memory->get('SquirrelForge');
        $this->assertSame(['decision_1', 'decision_2'], $record['decisions']);
        $this->assertSame(['milestone_1'], $record['milestones']);
        $this->assertSame(['risk_1'], $record['risks']);
        $this->assertSame(['dependency_1'], $record['dependencies']);
        $this->assertSame(['Initial architecture review complete.'], $record['notes']);
    }

    public function testSetConventionsOverwritesRatherThanAppends(): void
    {
        $memory = new ProjectMemory(new InMemoryStore());
        $memory->initializeProject('SquirrelForge', ['conventions' => ['PSR-12']]);

        $memory->setConventions('SquirrelForge', ['PSR-12', 'strict_types']);

        $this->assertSame(['PSR-12', 'strict_types'], $memory->get('SquirrelForge')['conventions']);
    }

    public function testUpdateArchitectureOverwritesTheReference(): void
    {
        $memory = new ProjectMemory(new InMemoryStore());
        $memory->initializeProject('SquirrelForge', ['architecture' => 'arch_ref_1']);

        $memory->updateArchitecture('SquirrelForge', 'arch_ref_2');

        $this->assertSame('arch_ref_2', $memory->get('SquirrelForge')['architecture']);
    }

    public function testProjectRecordsForDifferentProjectsAreIndependent(): void
    {
        $memory = new ProjectMemory(new InMemoryStore());
        $memory->initializeProject('ProjectA');
        $memory->initializeProject('ProjectB');
        $memory->referenceDecision('ProjectA', 'decision_a');

        $this->assertSame(['decision_a'], $memory->get('ProjectA')['decisions']);
        $this->assertSame([], $memory->get('ProjectB')['decisions']);
    }
}
