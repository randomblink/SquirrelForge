<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Agent;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Agent\SqliteAgentCollaboration;
use SquirrelForge\Agent\SqliteAgentSpecialization;

final class SqliteAgentSpecializationTest extends TestCase
{
    /** @var array<int, string> */
    private array $databasePaths = [];

    /** @var array<int, string> */
    private array $specDirectories = [];

    protected function tearDown(): void
    {
        foreach ($this->databasePaths as $path) {
            foreach ([$path, $path . '-shm', $path . '-wal'] as $candidate) {
                if (is_file($candidate)) {
                    unlink($candidate);
                }
            }
        }

        foreach ($this->specDirectories as $directory) {
            foreach (glob($directory . '/*.md') ?: [] as $file) {
                unlink($file);
            }

            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }

    private function tempPath(string $label): string
    {
        $path = sys_get_temp_dir() . "/squirrelforge-agent-specialization-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    /**
     * A real, on-disk fixture spec directory -- this class's roster
     * check is a genuine filesystem check, so tests exercise it against
     * real files rather than mocking existence.
     *
     * @param array<int, string> $roles Bare role tokens, e.g. 'DEVELOPER' for AGENT-DEVELOPER.md.
     */
    private function specDirectoryWith(array $roles): string
    {
        $directory = sys_get_temp_dir() . '/squirrelforge-agent-specialization-specs-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $this->specDirectories[] = $directory;

        foreach ($roles as $role) {
            file_put_contents($directory . '/AGENT-' . $role . '.md', "# {$role}\n");
        }

        return $directory;
    }

    /**
     * @return array<string, mixed>
     */
    private function requestFor(array $overrides = []): array
    {
        return array_replace([
            'work_reference' => 'task_123',
            'required_domain' => 'API integration',
            'candidate_roles' => ['DEVELOPER'],
            'boundary_verified' => true,
        ], $overrides);
    }

    // --- shape validation ---

    public function testMissingWorkReferenceIsInvalid(): void
    {
        $specialization = new SqliteAgentSpecialization($this->tempPath('db'), $this->specDirectoryWith(['DEVELOPER']));

        $result = $specialization->match($this->requestFor(['work_reference' => '']));

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testMissingRequiredDomainIsInvalid(): void
    {
        $specialization = new SqliteAgentSpecialization($this->tempPath('db'), $this->specDirectoryWith(['DEVELOPER']));

        $result = $specialization->match($this->requestFor(['required_domain' => null]));

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testEmptyCandidateRolesIsInvalid(): void
    {
        $specialization = new SqliteAgentSpecialization($this->tempPath('db'), $this->specDirectoryWith(['DEVELOPER']));

        $result = $specialization->match($this->requestFor(['candidate_roles' => []]));

        $this->assertSame('invalid', $result['outcome']);
    }

    // --- boundary_verified is required, fail-closed ---

    public function testMissingBoundaryVerifiedIsInvalid(): void
    {
        $specialization = new SqliteAgentSpecialization($this->tempPath('db'), $this->specDirectoryWith(['DEVELOPER']));

        $result = $specialization->match($this->requestFor(['boundary_verified' => null]));

        $this->assertSame('invalid', $result['outcome']);
        $this->assertStringContainsString('must not be assumed from a role\'s name alone', $result['error']);
    }

    public function testFalseBoundaryVerifiedIsInvalid(): void
    {
        $specialization = new SqliteAgentSpecialization($this->tempPath('db'), $this->specDirectoryWith(['DEVELOPER']));

        $result = $specialization->match($this->requestFor(['boundary_verified' => false]));

        $this->assertSame('invalid', $result['outcome']);
    }

    // --- real filesystem roster check ---

    public function testSingleCandidateWithRealSpecFileIsMatched(): void
    {
        $specialization = new SqliteAgentSpecialization($this->tempPath('db'), $this->specDirectoryWith(['DEVELOPER']));

        $result = $specialization->match($this->requestFor());

        $this->assertSame('Matched', $result['outcome']);
        $this->assertSame('DEVELOPER', $result['matched_role']);
        $this->assertNotNull($result['match_id']);
    }

    public function testCandidateWithNoRealSpecFileIsEscalatedNotApproximated(): void
    {
        $specialization = new SqliteAgentSpecialization($this->tempPath('db'), $this->specDirectoryWith(['DEVELOPER']));

        $result = $specialization->match($this->requestFor(['candidate_roles' => ['QUANTUM_WIZARD']]));

        $this->assertSame('Escalated — No Matching Role', $result['outcome']);
        $this->assertStringContainsString('No AGENT-QUANTUM_WIZARD.md exists', $result['rationale']);
    }

    public function testAgainstTheRealSixteenAgentsDirectoryDeveloperMatches(): void
    {
        // A genuine integration point: the actual 16_AGENTS/ directory this repo ships.
        $realSpecDirectory = dirname(__DIR__, 2) . '/16_AGENTS';
        $specialization = new SqliteAgentSpecialization($this->tempPath('db'), $realSpecDirectory);

        $result = $specialization->match($this->requestFor(['candidate_roles' => ['DEVELOPER']]));

        $this->assertSame('Matched', $result['outcome']);
    }

    public function testAgainstTheRealSixteenAgentsDirectoryAFabricatedRoleEscalates(): void
    {
        $realSpecDirectory = dirname(__DIR__, 2) . '/16_AGENTS';
        $specialization = new SqliteAgentSpecialization($this->tempPath('db'), $realSpecDirectory);

        $result = $specialization->match($this->requestFor(['candidate_roles' => ['ACCESSIBILITY']]));

        // README's own illustrative "Accessibility Reviewer" has no real AGENT-*.md file.
        $this->assertSame('Escalated — No Matching Role', $result['outcome']);
    }

    // --- multi-specialization: real Collaboration composition ---

    public function testMultipleCandidatesWithoutCollaborationComposedIsStillRecordedAsCollaborationRequired(): void
    {
        $specialization = new SqliteAgentSpecialization($this->tempPath('db'), $this->specDirectoryWith(['DEVELOPER', 'REVIEWER']));

        $result = $specialization->match($this->requestFor(['candidate_roles' => ['DEVELOPER', 'REVIEWER']]));

        $this->assertSame('Collaboration Required', $result['outcome']);
        $this->assertNull($result['collaboration_id']);
    }

    public function testMultipleCandidatesWithCollaborationComposedProducesARealCollaborationStructure(): void
    {
        $collaboration = new SqliteAgentCollaboration($this->tempPath('collab'));
        $specialization = new SqliteAgentSpecialization($this->tempPath('db'), $this->specDirectoryWith(['DEVELOPER', 'REVIEWER']), $collaboration);

        $result = $specialization->match($this->requestFor([
            'candidate_roles' => ['DEVELOPER', 'REVIEWER'],
            'escalation_criteria' => ['unresolved_dependency'],
        ]));

        $this->assertSame('Collaboration Required', $result['outcome']);
        $this->assertNotNull($result['collaboration_id']);

        $record = $collaboration->get($result['collaboration_id']);
        $this->assertSame('Specialist Team', $record['collaboration_model']);
        $this->assertCount(2, $record['participants']);
    }

    public function testMultiCandidateEscalationTakesPriorityOverCollaborationRouting(): void
    {
        // A missing role among the candidates must escalate, not be silently folded into a collaboration.
        $specialization = new SqliteAgentSpecialization($this->tempPath('db'), $this->specDirectoryWith(['DEVELOPER']));

        $result = $specialization->match($this->requestFor(['candidate_roles' => ['DEVELOPER', 'QUANTUM_WIZARD']]));

        $this->assertSame('Escalated — No Matching Role', $result['outcome']);
    }

    // --- traceability ---

    public function testGetUnknownMatchReturnsNull(): void
    {
        $specialization = new SqliteAgentSpecialization($this->tempPath('db'), $this->specDirectoryWith(['DEVELOPER']));

        $this->assertNull($specialization->get('ghost'));
    }

    public function testGetReturnsTheFullRecordedMatch(): void
    {
        $specialization = new SqliteAgentSpecialization($this->tempPath('db'), $this->specDirectoryWith(['DEVELOPER']));

        $result = $specialization->match($this->requestFor());
        $record = $specialization->get($result['match_id']);

        $this->assertSame('task_123', $record['work_reference']);
        $this->assertSame(['DEVELOPER'], $record['candidate_roles']);
        $this->assertTrue($record['boundary_verified']);
        $this->assertSame('Matched', $record['outcome']);
    }
}
