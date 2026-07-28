<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Communication\SqliteConversationManager;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;

final class SqliteConversationManagerTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        $this->databasePath = sys_get_temp_dir() . '/squirrelforge-conversations-' . bin2hex(random_bytes(8)) . '.sqlite';
    }

    protected function tearDown(): void
    {
        foreach ([$this->databasePath, $this->databasePath . '-shm', $this->databasePath . '-wal'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testStartCreatesAnActiveConversationWithParticipants(): void
    {
        $manager = new SqliteConversationManager($this->databasePath);
        $result = $manager->start(['user_1', 'agent_1'], 'user_conversation');

        $this->assertSame('active', $result['state']);
        $this->assertSame(['user_1', 'agent_1'], $result['participants']);
        $this->assertSame('ungoverned', $result['governance_status']);
    }

    public function testMessagesFromAParticipantAreAppendedInOrder(): void
    {
        $manager = new SqliteConversationManager($this->databasePath);
        $started = $manager->start(['user_1', 'agent_1'], 'user_conversation');
        $conversationRef = $started['conversation_ref'];

        $first = $manager->appendMessage($conversationRef, 'user_1', ['text' => 'hello']);
        $second = $manager->appendMessage($conversationRef, 'agent_1', ['text' => 'hi there']);

        $this->assertTrue($first['accepted']);
        $this->assertSame(1, $first['sequence']);
        $this->assertTrue($second['accepted']);
        $this->assertSame(2, $second['sequence']);

        $conversation = $manager->conversation($conversationRef);
        $this->assertCount(2, $conversation['messages']);
        $this->assertSame('user_1', $conversation['messages'][0]['participant']);
        $this->assertSame('agent_1', $conversation['messages'][1]['participant']);
    }

    public function testMessageFromANonParticipantIsRejected(): void
    {
        $manager = new SqliteConversationManager($this->databasePath);
        $started = $manager->start(['user_1', 'agent_1'], 'user_conversation');

        $result = $manager->appendMessage($started['conversation_ref'], 'agent_intruder', ['text' => 'hi']);

        $this->assertFalse($result['accepted']);
        $this->assertStringContainsString('not a participant', (string) $result['error']);
    }

    public function testMessageOnANonActiveConversationIsRejected(): void
    {
        $manager = new SqliteConversationManager($this->databasePath);
        $started = $manager->start(['user_1', 'agent_1'], 'user_conversation');
        $manager->transition($started['conversation_ref'], 'completed');

        $result = $manager->appendMessage($started['conversation_ref'], 'user_1', ['text' => 'too late']);

        $this->assertFalse($result['accepted']);
        $this->assertStringContainsString('can only be appended while active', (string) $result['error']);
    }

    public function testValidTransitionSequenceIsAccepted(): void
    {
        $manager = new SqliteConversationManager($this->databasePath);
        $started = $manager->start(['user_1'], 'user_conversation');
        $conversationRef = $started['conversation_ref'];

        $paused = $manager->transition($conversationRef, 'paused');
        $resumed = $manager->transition($conversationRef, 'active');
        $completed = $manager->transition($conversationRef, 'completed');
        $archived = $manager->transition($conversationRef, 'archived');

        $this->assertTrue($paused['accepted']);
        $this->assertTrue($resumed['accepted']);
        $this->assertTrue($completed['accepted']);
        $this->assertTrue($archived['accepted']);
        $this->assertSame('archived', $manager->conversation($conversationRef)['state']);
    }

    public function testInvalidTransitionIsRejectedWithoutChangingState(): void
    {
        $manager = new SqliteConversationManager($this->databasePath);
        $started = $manager->start(['user_1'], 'user_conversation');

        $result = $manager->transition($started['conversation_ref'], 'archived');

        $this->assertFalse($result['accepted']);
        $this->assertStringContainsString('Cannot transition', (string) $result['error']);
        $this->assertSame('active', $manager->conversation($started['conversation_ref'])['state']);
    }

    public function testTransitionOnAnArchivedConversationIsTerminal(): void
    {
        $manager = new SqliteConversationManager($this->databasePath);
        $started = $manager->start(['user_1'], 'user_conversation');
        $manager->transition($started['conversation_ref'], 'completed');
        $manager->transition($started['conversation_ref'], 'archived');

        $result = $manager->transition($started['conversation_ref'], 'active');

        $this->assertFalse($result['accepted']);
    }

    public function testContextSummaryIsAStructuralRollupNotAGeneratedSummary(): void
    {
        $manager = new SqliteConversationManager($this->databasePath);
        $started = $manager->start(['user_1', 'agent_1'], 'user_conversation', ['topic' => 'billing']);
        $manager->appendMessage($started['conversation_ref'], 'user_1', ['text' => 'first']);
        $manager->appendMessage($started['conversation_ref'], 'agent_1', ['text' => 'second']);

        $summary = $manager->contextSummary($started['conversation_ref']);

        $this->assertSame(2, $summary['message_count']);
        $this->assertSame('agent_1', $summary['last_message']['participant']);
        $this->assertSame(['text' => 'second'], $summary['last_message']['content']);
        $this->assertSame(['topic' => 'billing'], $summary['metadata']);
    }

    public function testContextSummaryReturnsNullForUnknownConversation(): void
    {
        $manager = new SqliteConversationManager($this->databasePath);

        $this->assertNull($manager->contextSummary('conversation_does_not_exist'));
    }

    public function testConversationReturnsNullForUnknownRef(): void
    {
        $manager = new SqliteConversationManager($this->databasePath);

        $this->assertNull($manager->conversation('conversation_does_not_exist'));
    }

    public function testUpdatedConversationDispatchesEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('conversation.updated', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $manager = new SqliteConversationManager($this->databasePath, $events);
        $manager->start(['user_1'], 'user_conversation');

        $this->assertCount(1, $captured);
        $this->assertSame('active', $captured[0]->getPayload()['state']);
    }

    public function testRecentOrdersByMostRecentActivityNotJustCreation(): void
    {
        $manager = new SqliteConversationManager($this->databasePath);
        $a = $manager->start(['user_1'], 'user_conversation');
        $b = $manager->start(['user_2'], 'user_conversation');

        // Touch the older conversation ("a") last -- it should now sort first.
        $manager->appendMessage($a['conversation_ref'], 'user_1', ['text' => 'still here']);

        $recent = $manager->recent(1);

        $this->assertCount(1, $recent);
        $this->assertSame($a['conversation_ref'], $recent[0]['conversation_ref']);
    }
}
