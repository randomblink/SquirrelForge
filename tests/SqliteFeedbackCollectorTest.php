<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Learning\SqliteFeedbackCollector;

final class SqliteFeedbackCollectorTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        $this->databasePath = sys_get_temp_dir() . '/squirrelforge-feedback-' . bin2hex(random_bytes(8)) . '.sqlite';
    }

    protected function tearDown(): void
    {
        foreach ([$this->databasePath, $this->databasePath . '-shm', $this->databasePath . '-wal'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testValidSubmissionIsCollected(): void
    {
        $collector = new SqliteFeedbackCollector($this->databasePath);

        $result = $collector->submit('user', ['rating' => 5, 'comment' => 'great']);

        $this->assertSame('collected', $result['outcome']);
        $this->assertNotNull($result['feedback_id']);
    }

    public function testUnknownSourceTypeIsRejected(): void
    {
        $collector = new SqliteFeedbackCollector($this->databasePath);

        $result = $collector->submit('telepathy', ['x' => 1]);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('Unknown feedback source type', (string) $result['error']);
    }

    public function testEmptyContentIsRejected(): void
    {
        $collector = new SqliteFeedbackCollector($this->databasePath);

        $result = $collector->submit('user', []);

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testIdentityRequiredWithoutAnAuthenticatedResultIsRejected(): void
    {
        $collector = new SqliteFeedbackCollector($this->databasePath);

        $result = $collector->submit('security', ['finding' => 'x'], ['require_identity' => true]);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('authenticated identity', (string) $result['error']);
    }

    public function testIdentityRequiredWithAnAuthenticatedResultSucceedsAndRecordsIdentity(): void
    {
        $collector = new SqliteFeedbackCollector($this->databasePath);

        $result = $collector->submit('security', ['finding' => 'x'], [
            'require_identity' => true,
            'authentication_result' => ['authenticated' => true, 'identity_ref' => 'user_1'],
        ]);

        $this->assertSame('collected', $result['outcome']);
        $this->assertSame('user_1', $collector->get($result['feedback_id'])['identity_ref']);
    }

    public function testIdenticalSubmissionsAreDetectedAsDuplicatesAndDoNotCreateANewRecord(): void
    {
        $collector = new SqliteFeedbackCollector($this->databasePath);
        $first = $collector->submit('user', ['rating' => 5]);

        $second = $collector->submit('user', ['rating' => 5]);

        $this->assertSame('duplicate', $second['outcome']);
        $this->assertSame($first['feedback_id'], $second['feedback_id']);
        $this->assertCount(1, $collector->list());
    }

    public function testDifferentContentIsNotADuplicate(): void
    {
        $collector = new SqliteFeedbackCollector($this->databasePath);
        $collector->submit('user', ['rating' => 5]);

        $result = $collector->submit('user', ['rating' => 1]);

        $this->assertSame('collected', $result['outcome']);
    }

    public function testContentAndEvidenceReferencesArePreservedVerbatim(): void
    {
        $collector = new SqliteFeedbackCollector($this->databasePath);
        $content = ['nested' => ['a' => 1, 'b' => [2, 3]]];
        $result = $collector->submit('workflow', $content, ['evidence_references' => ['evidence_1', 'evidence_2']]);

        $fetched = $collector->get($result['feedback_id']);

        $this->assertSame($content, $fetched['content']);
        $this->assertSame(['evidence_1', 'evidence_2'], $fetched['evidence_references']);
    }

    public function testGetReturnsNullForUnknownFeedbackId(): void
    {
        $collector = new SqliteFeedbackCollector($this->databasePath);

        $this->assertNull($collector->get('feedback_does_not_exist'));
    }

    public function testMarkForwardedTransitionsStatus(): void
    {
        $collector = new SqliteFeedbackCollector($this->databasePath);
        $submitted = $collector->submit('user', ['rating' => 5]);

        $result = $collector->markForwarded($submitted['feedback_id'], 'evaluation_engine');

        $this->assertTrue($result['found']);
        $fetched = $collector->get($submitted['feedback_id']);
        $this->assertSame('forwarded', $fetched['status']);
        $this->assertSame('evaluation_engine', $fetched['forwarded_to']);
    }

    public function testMarkForwardedTwiceIsRejected(): void
    {
        $collector = new SqliteFeedbackCollector($this->databasePath);
        $submitted = $collector->submit('user', ['rating' => 5]);
        $collector->markForwarded($submitted['feedback_id'], 'evaluation_engine');

        $result = $collector->markForwarded($submitted['feedback_id'], 'experience_store');

        $this->assertFalse($result['found']);
        $this->assertStringContainsString('already been forwarded', (string) $result['error']);
    }

    public function testMarkForwardedForUnknownFeedbackIsRejected(): void
    {
        $collector = new SqliteFeedbackCollector($this->databasePath);

        $result = $collector->markForwarded('feedback_does_not_exist', 'evaluation_engine');

        $this->assertFalse($result['found']);
    }

    public function testListFiltersBySourceTypeAndStatus(): void
    {
        $collector = new SqliteFeedbackCollector($this->databasePath);
        $collector->submit('user', ['a' => 1]);
        $workflowFeedback = $collector->submit('workflow', ['b' => 2]);
        $collector->markForwarded($workflowFeedback['feedback_id'], 'evaluation_engine');

        $this->assertCount(1, $collector->list(['source_type' => 'workflow']));
        $this->assertCount(1, $collector->list(['status' => 'forwarded']));
        $this->assertCount(1, $collector->list(['status' => 'collected']));
    }

    public function testSubmittedFeedbackDispatchesEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('feedback.collected', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $collector = new SqliteFeedbackCollector($this->databasePath, $events);
        $collector->submit('user', ['a' => 1]);

        $this->assertCount(1, $captured);
    }
}
