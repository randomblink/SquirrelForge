<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Knowledge\SqliteCitationManager;
use SquirrelForge\Knowledge\SqliteKnowledgeRegistry;
use SquirrelForge\Knowledge\SqliteKnowledgeValidator;

final class SqliteKnowledgeValidatorTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-knowledge-validator-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    public function testValidateWithNoRegistryConfiguredRecordsALimitationRatherThanRejecting(): void
    {
        $validator = new SqliteKnowledgeValidator($this->tempPath('validator'));

        $result = $validator->validate('knowledge_1');

        $this->assertSame('Warning', $result['result']);
        $this->assertStringContainsString('No Knowledge Registry configured', $result['limitations'][0]);
    }

    public function testValidateRejectsAnUnregisteredKnowledgeAssetWhenRegistryIsConfigured(): void
    {
        $registry = new SqliteKnowledgeRegistry($this->tempPath('registry'));
        $validator = new SqliteKnowledgeValidator($this->tempPath('validator'), $registry);

        $result = $validator->validate('knowledge_missing');

        $this->assertSame('Rejected', $result['result']);
        $this->assertSame('Low', $result['trust_level']);
    }

    public function testValidateWithRegistryAndNoCitationManagerYieldsWarningWithLimitation(): void
    {
        $registry = new SqliteKnowledgeRegistry($this->tempPath('registry'));
        $registered = $registry->register('Deploy Runbook', 'document', 'wiki', 'platform_team');
        $validator = new SqliteKnowledgeValidator($this->tempPath('validator'), $registry);

        $result = $validator->validate($registered['knowledge_id']);

        $this->assertSame('Warning', $result['result']);
        $this->assertStringContainsString('No Citation Manager configured', $result['limitations'][0]);
    }

    public function testValidateIsValidWithRegistryAndCitationManagerAndNoOtherIssues(): void
    {
        $registry = new SqliteKnowledgeRegistry($this->tempPath('registry'));
        $registered = $registry->register('Deploy Runbook', 'document', 'wiki', 'platform_team');
        $citations = new SqliteCitationManager($this->tempPath('citations'), $registry);
        $citations->registerCitation($registered['knowledge_id'], 'https://example.com', 'primary_source', ['url' => 'https://example.com']);
        $validator = new SqliteKnowledgeValidator($this->tempPath('validator'), $registry, $citations);

        $result = $validator->validate($registered['knowledge_id']);

        $this->assertSame('Valid', $result['result']);
        $this->assertSame('High', $result['trust_level']);
        $this->assertSame([], $result['limitations']);
        $this->assertSame('Validated', $registry->get($registered['knowledge_id'])['lifecycle_status']);
        $this->assertSame('High', $registry->get($registered['knowledge_id'])['trust_level']);
    }

    public function testValidateFlagsAnUnresolvedCitationAsALimitation(): void
    {
        $registry = new SqliteKnowledgeRegistry($this->tempPath('registry'));
        $registered = $registry->register('Deploy Runbook', 'document', 'wiki', 'platform_team');
        $citations = new SqliteCitationManager($this->tempPath('citations'), $registry);
        $citations->registerCitation($registered['knowledge_id'], '', 'primary_source', []);
        $validator = new SqliteKnowledgeValidator($this->tempPath('validator'), $registry, $citations);

        $result = $validator->validate($registered['knowledge_id']);

        $this->assertSame('Warning', $result['result']);
        $this->assertStringContainsString('unresolved', $result['limitations'][0]);
    }

    public function testValidateFlagsContradictionSignalsAndStaleFreshness(): void
    {
        $registry = new SqliteKnowledgeRegistry($this->tempPath('registry'));
        $registered = $registry->register('Deploy Runbook', 'document', 'wiki', 'platform_team');
        $citations = new SqliteCitationManager($this->tempPath('citations'), $registry);
        $citations->registerCitation($registered['knowledge_id'], 'https://example.com', 'primary_source', ['url' => 'https://example.com']);
        $validator = new SqliteKnowledgeValidator($this->tempPath('validator'), $registry, $citations);

        $result = $validator->validate($registered['knowledge_id'], [
            'contradiction_signals' => ['conflicts with knowledge_5'],
            'freshness_days' => 400,
            'max_age_days' => 365,
        ]);

        $this->assertSame('Warning', $result['result']);
        $this->assertCount(2, $result['limitations']);
    }

    public function testAssignAuthoritativeTrustRequiresAGovernanceReference(): void
    {
        $registry = new SqliteKnowledgeRegistry($this->tempPath('registry'));
        $registered = $registry->register('Deploy Runbook', 'document', 'wiki', 'platform_team');
        $validator = new SqliteKnowledgeValidator($this->tempPath('validator'), $registry);
        $validator->validate($registered['knowledge_id']);

        $result = $validator->assignAuthoritativeTrust($registered['knowledge_id'], '');

        $this->assertSame('authorization_required', $result['outcome']);
    }

    public function testAssignAuthoritativeTrustRequiresAPriorValidation(): void
    {
        $validator = new SqliteKnowledgeValidator($this->tempPath('validator'));

        $result = $validator->assignAuthoritativeTrust('knowledge_never_validated', 'governance_ref_1');

        $this->assertSame('not_validated', $result['outcome']);
    }

    public function testAssignAuthoritativeTrustSucceedsAfterAPriorValidationAndUpdatesTheRegistry(): void
    {
        $registry = new SqliteKnowledgeRegistry($this->tempPath('registry'));
        $registered = $registry->register('Deploy Runbook', 'document', 'wiki', 'platform_team');
        $validator = new SqliteKnowledgeValidator($this->tempPath('validator'), $registry);
        $validator->validate($registered['knowledge_id']);

        $result = $validator->assignAuthoritativeTrust($registered['knowledge_id'], 'governance_ref_1');

        $this->assertSame('assigned', $result['outcome']);
        $this->assertSame('Authoritative', $registry->get($registered['knowledge_id'])['trust_level']);
    }

    public function testFindByKnowledgeIdReturnsAllValidationRecords(): void
    {
        $registry = new SqliteKnowledgeRegistry($this->tempPath('registry'));
        $registered = $registry->register('Deploy Runbook', 'document', 'wiki', 'platform_team');
        $validator = new SqliteKnowledgeValidator($this->tempPath('validator'), $registry);
        $validator->validate($registered['knowledge_id']);
        $validator->validate($registered['knowledge_id']);

        $this->assertCount(2, $validator->findByKnowledgeId($registered['knowledge_id']));
    }
}
