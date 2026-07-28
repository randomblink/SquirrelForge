<?php

declare(strict_types=1);

namespace SquirrelForge\Communication;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;

/**
 * Maintains conversation lifecycle, participant state, and message
 * ordering, per 36_COMMUNICATION/CONVERSATION-MANAGER.md.
 *
 * The spec explicitly disclaims reasoning ("does not generate responses
 * or perform reasoning"), so `contextSummary()` is a deterministic,
 * structural rollup of stored state -- participant list, message count,
 * the last message, timestamps -- never a generated natural-language
 * summary. Producing one of those would need a real AI Driver, which
 * this component is documented not to be.
 *
 * This is a deliberately scoped subset of the spec's eight conversation
 * states: only `active`, `paused`, `escalated`, `completed`, and
 * `archived` are modeled, via a fixed, deny-by-default transition table
 * (an unlisted transition is rejected, mirroring SqliteSelfHealingEngine's
 * approved-actions gate). `Created` and `Active` are collapsed into one
 * -- start() begins directly in `active` -- and `Waiting`/`Expired` need
 * a real timeout/scheduler this synchronous framework doesn't have.
 * Messages can only be appended while `active`, per the safety rule
 * against modifying historical (closed) conversations, and only from a
 * participant already on the conversation's roster.
 *
 * Unlike SqliteMessageValidator (which persists every validation
 * attempt because producing that audit trail is its whole job), a
 * rejected append or an invalid transition here is not written to the
 * conversation's history: it didn't happen to the conversation, so it
 * isn't a historical event. This mirrors SqliteRecoveryManager and
 * SqliteSelfHealingEngine's stance rather than the validator's.
 * Governance status is the fixed constant `ungoverned` since
 * 23_GOVERNANCE has no code.
 */
final class SqliteConversationManager
{
    private const TRANSITIONS = [
        'active' => ['paused', 'escalated', 'completed'],
        'paused' => ['active', 'completed'],
        'escalated' => ['completed'],
        'completed' => ['archived'],
        'archived' => [],
    ];

    private PDO $database;

    public function __construct(string $databasePath, private readonly ?EventBusInterface $events = null)
    {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS conversations (
                conversation_ref TEXT PRIMARY KEY,
                session_ref TEXT NOT NULL,
                type TEXT NOT NULL,
                state TEXT NOT NULL,
                participants_json TEXT NOT NULL,
                metadata_json TEXT NOT NULL,
                message_count INTEGER NOT NULL DEFAULT 0,
                activity_seq INTEGER NOT NULL,
                started_at TEXT NOT NULL,
                last_activity_at TEXT NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS conversation_messages (
                message_ref TEXT PRIMARY KEY,
                conversation_ref TEXT NOT NULL,
                participant TEXT NOT NULL,
                sequence INTEGER NOT NULL,
                content_json TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array<int, string> $participants
     * @param array<string, mixed> $metadata
     * @return array{conversation_ref: string, session_ref: string, type: string, state: string, participants: array<int, string>, governance_status: string}
     */
    public function start(array $participants, string $type, array $metadata = []): array
    {
        $conversationRef = 'conversation_' . bin2hex(random_bytes(12));
        $sessionRef = 'session_' . bin2hex(random_bytes(12));
        $now = gmdate(DATE_RFC3339);

        $statement = $this->database->prepare(
            'INSERT INTO conversations (
                conversation_ref, session_ref, type, state, participants_json,
                metadata_json, message_count, activity_seq, started_at, last_activity_at
            ) VALUES (
                :conversation_ref, :session_ref, :type, :state, :participants_json,
                :metadata_json, 0, (SELECT COALESCE(MAX(activity_seq), 0) + 1 FROM conversations), :started_at, :last_activity_at
            )'
        );
        $statement->execute([
            'conversation_ref' => $conversationRef,
            'session_ref' => $sessionRef,
            'type' => $type,
            'state' => 'active',
            'participants_json' => json_encode($participants, JSON_THROW_ON_ERROR),
            'metadata_json' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'started_at' => $now,
            'last_activity_at' => $now,
        ]);

        $this->publish($conversationRef, 'active');

        return [
            'conversation_ref' => $conversationRef,
            'session_ref' => $sessionRef,
            'type' => $type,
            'state' => 'active',
            'participants' => $participants,
            'governance_status' => 'ungoverned',
        ];
    }

    /**
     * @param array<string, mixed> $content
     * @return array{accepted: bool, message_ref: ?string, sequence: ?int, error: ?string}
     */
    public function appendMessage(string $conversationRef, string $participant, array $content): array
    {
        $conversation = $this->fetchConversation($conversationRef);

        if ($conversation === null) {
            return ['accepted' => false, 'message_ref' => null, 'sequence' => null, 'error' => sprintf('Unknown conversation "%s".', $conversationRef)];
        }

        if ($conversation['state'] !== 'active') {
            return ['accepted' => false, 'message_ref' => null, 'sequence' => null, 'error' => sprintf('Conversation is "%s"; messages can only be appended while active.', $conversation['state'])];
        }

        $participants = json_decode((string) $conversation['participants_json'], true, flags: JSON_THROW_ON_ERROR);

        if (!in_array($participant, $participants, true)) {
            return ['accepted' => false, 'message_ref' => null, 'sequence' => null, 'error' => sprintf('"%s" is not a participant in this conversation.', $participant)];
        }

        $sequence = (int) $conversation['message_count'] + 1;
        $messageRef = 'conversation_message_' . bin2hex(random_bytes(12));
        $now = gmdate(DATE_RFC3339);

        $insert = $this->database->prepare(
            'INSERT INTO conversation_messages (message_ref, conversation_ref, participant, sequence, content_json, created_at)
             VALUES (:message_ref, :conversation_ref, :participant, :sequence, :content_json, :created_at)'
        );
        $insert->execute([
            'message_ref' => $messageRef,
            'conversation_ref' => $conversationRef,
            'participant' => $participant,
            'sequence' => $sequence,
            'content_json' => json_encode($content, JSON_THROW_ON_ERROR),
            'created_at' => $now,
        ]);

        $update = $this->database->prepare(
            'UPDATE conversations
             SET message_count = :message_count, last_activity_at = :last_activity_at,
                 activity_seq = (SELECT COALESCE(MAX(activity_seq), 0) + 1 FROM conversations)
             WHERE conversation_ref = :conversation_ref'
        );
        $update->execute(['message_count' => $sequence, 'last_activity_at' => $now, 'conversation_ref' => $conversationRef]);

        $this->publish($conversationRef, $conversation['state']);

        return ['accepted' => true, 'message_ref' => $messageRef, 'sequence' => $sequence, 'error' => null];
    }

    /**
     * @return array{accepted: bool, state: ?string, error: ?string}
     */
    public function transition(string $conversationRef, string $toState): array
    {
        $conversation = $this->fetchConversation($conversationRef);

        if ($conversation === null) {
            return ['accepted' => false, 'state' => null, 'error' => sprintf('Unknown conversation "%s".', $conversationRef)];
        }

        $fromState = $conversation['state'];

        if (!in_array($toState, self::TRANSITIONS[$fromState] ?? [], true)) {
            return ['accepted' => false, 'state' => $fromState, 'error' => sprintf('Cannot transition from "%s" to "%s".', $fromState, $toState)];
        }

        $now = gmdate(DATE_RFC3339);
        $update = $this->database->prepare(
            'UPDATE conversations
             SET state = :state, last_activity_at = :last_activity_at,
                 activity_seq = (SELECT COALESCE(MAX(activity_seq), 0) + 1 FROM conversations)
             WHERE conversation_ref = :conversation_ref'
        );
        $update->execute(['state' => $toState, 'last_activity_at' => $now, 'conversation_ref' => $conversationRef]);

        $this->publish($conversationRef, $toState);

        return ['accepted' => true, 'state' => $toState, 'error' => null];
    }

    /**
     * A deterministic, structural rollup of stored state -- never a
     * generated summary.
     *
     * @return array<string, mixed>|null
     */
    public function contextSummary(string $conversationRef): ?array
    {
        $conversation = $this->fetchConversation($conversationRef);

        if ($conversation === null) {
            return null;
        }

        $statement = $this->database->prepare(
            'SELECT participant, content_json, created_at FROM conversation_messages
             WHERE conversation_ref = :conversation_ref ORDER BY sequence DESC LIMIT 1'
        );
        $statement->execute(['conversation_ref' => $conversationRef]);
        $lastMessageRow = $statement->fetch();

        return [
            'conversation_ref' => $conversation['conversation_ref'],
            'session_ref' => $conversation['session_ref'],
            'type' => $conversation['type'],
            'state' => $conversation['state'],
            'participants' => json_decode((string) $conversation['participants_json'], true, flags: JSON_THROW_ON_ERROR),
            'message_count' => (int) $conversation['message_count'],
            'last_message' => is_array($lastMessageRow) ? [
                'participant' => $lastMessageRow['participant'],
                'content' => json_decode((string) $lastMessageRow['content_json'], true, flags: JSON_THROW_ON_ERROR),
                'created_at' => $lastMessageRow['created_at'],
            ] : null,
            'started_at' => $conversation['started_at'],
            'last_activity_at' => $conversation['last_activity_at'],
            'metadata' => json_decode((string) $conversation['metadata_json'], true, flags: JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function conversation(string $conversationRef): ?array
    {
        $conversation = $this->fetchConversation($conversationRef);

        if ($conversation === null) {
            return null;
        }

        $statement = $this->database->prepare(
            'SELECT message_ref, participant, sequence, content_json, created_at FROM conversation_messages
             WHERE conversation_ref = :conversation_ref ORDER BY sequence ASC'
        );
        $statement->execute(['conversation_ref' => $conversationRef]);

        $messages = array_map(
            static fn(array $row): array => [
                'message_ref' => $row['message_ref'],
                'participant' => $row['participant'],
                'sequence' => (int) $row['sequence'],
                'content' => json_decode((string) $row['content_json'], true, flags: JSON_THROW_ON_ERROR),
                'created_at' => $row['created_at'],
            ],
            $statement->fetchAll()
        );

        return [
            'conversation_ref' => $conversation['conversation_ref'],
            'session_ref' => $conversation['session_ref'],
            'type' => $conversation['type'],
            'state' => $conversation['state'],
            'participants' => json_decode((string) $conversation['participants_json'], true, flags: JSON_THROW_ON_ERROR),
            'messages' => $messages,
            'started_at' => $conversation['started_at'],
            'last_activity_at' => $conversation['last_activity_at'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 50): array
    {
        $statement = $this->database->prepare(
            'SELECT conversation_ref, session_ref, type, state, message_count, started_at, last_activity_at
             FROM conversations ORDER BY activity_seq DESC LIMIT :limit'
        );
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchConversation(string $conversationRef): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM conversations WHERE conversation_ref = :conversation_ref');
        $statement->execute(['conversation_ref' => $conversationRef]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    private function publish(string $conversationRef, string $state): void
    {
        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'conversation.updated',
            new DateTimeImmutable(),
            self::class,
            ['conversation_ref' => $conversationRef, 'state' => $state]
        ));
    }
}
