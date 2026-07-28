<?php

declare(strict_types=1);

namespace SquirrelForge\Knowledge;

use DateTimeImmutable;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;

/**
 * Top-level coordinator for the Knowledge Layer, per
 * 25_KNOWLEDGE/KNOWLEDGE-MANAGER.md, mirroring how SqliteResilienceManager,
 * SqliteCommunicationManager, and SqliteDataManager coordinate their own
 * layers -- except this one has no database of its own, like
 * SemanticSearchManager: the spec forbids owning "general logging,
 * audit, storage, or observability infrastructure", so there is no
 * knowledge_operations table here, only an optional EventBusInterface
 * announcement per request.
 *
 * Of the eight components the spec lists under "Depends On", three have
 * real code to route to: Document Repository (register/read/update/
 * archive/restore a document-facing reference), Embeddings Manager
 * (generate a vector for a registered asset), and Semantic Search
 * (retrieve relevance-ranked references). Knowledge Registry, Knowledge
 * Validator, Citation Manager, Knowledge Versioning, and Knowledge
 * Graph have no implementation, so requests naming those operations
 * escalate rather than being routed somewhere invented -- this follows
 * directly from the spec's own rule that lifecycle transitions must be
 * "based on the authoritative result from the owning component": there
 * is no owning component for those operations to produce one.
 */
final class KnowledgeManager
{
    private const REQUIRED_FIELDS = [
        'register' => ['title', 'type'],
        'read' => ['document_id'],
        'update' => ['document_id', 'changes'],
        'archive' => ['document_id'],
        'restore' => ['document_id'],
        'embed' => ['document_id', 'text'],
        'search' => ['query'],
    ];

    private const UNSUPPORTED_OPERATIONS = ['validate', 'cite', 'version', 'relate'];

    public function __construct(
        private readonly ?SqliteDocumentRepository $documents = null,
        private readonly ?SqliteEmbeddingsManager $embeddings = null,
        private readonly ?SemanticSearchManager $search = null,
        private readonly ?EventBusInterface $events = null
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options forwarded to whichever component handles this operation
     * @return array{operation: string, target_component: string, outcome: string, error: ?string, result: mixed}
     */
    public function coordinate(string $operation, array $payload, array $options = []): array
    {
        if (in_array($operation, self::UNSUPPORTED_OPERATIONS, true)) {
            return $this->finish($operation, 'none', 'escalated', sprintf('Knowledge operation "%s" has no implemented owning component.', $operation), null);
        }

        if (!isset(self::REQUIRED_FIELDS[$operation])) {
            return $this->finish($operation, 'none', 'rejected', sprintf('Unknown knowledge operation "%s".', $operation), null);
        }

        $missingField = $this->firstMissingField($operation, $payload);

        if ($missingField !== null) {
            return $this->finish($operation, 'none', 'rejected', sprintf('Missing required field "%s" for operation "%s".', $missingField, $operation), null);
        }

        return match ($operation) {
            'register' => $this->coordinateRegister($payload, $options),
            'read' => $this->coordinateRead($payload, $options),
            'update' => $this->coordinateUpdate($payload),
            'archive' => $this->coordinateArchive($payload),
            'restore' => $this->coordinateRestore($payload),
            'embed' => $this->coordinateEmbed($payload, $options),
            'search' => $this->coordinateSearch($payload, $options),
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function firstMissingField(string $operation, array $payload): ?string
    {
        foreach (self::REQUIRED_FIELDS[$operation] as $field) {
            if (!array_key_exists($field, $payload)) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     */
    private function coordinateRegister(array $payload, array $options): array
    {
        if ($this->documents === null) {
            return $this->finish('register', 'document_repository', 'rejected', 'Document Repository is not configured.', null);
        }

        $result = $this->documents->registerReference($payload['title'], $payload['type'], $options);

        return $this->finish('register', 'document_repository', $result['found'] ? 'registered' : 'rejected', $result['error'], $result);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     */
    private function coordinateRead(array $payload, array $options): array
    {
        if ($this->documents === null) {
            return $this->finish('read', 'document_repository', 'rejected', 'Document Repository is not configured.', null);
        }

        $result = $this->documents->readMetadata($payload['document_id'], $options['authorization_result'] ?? null);

        return $this->finish('read', 'document_repository', $result['found'] ? 'retrieved' : 'not_found', $result['error'] ?? null, $result);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function coordinateUpdate(array $payload): array
    {
        if ($this->documents === null) {
            return $this->finish('update', 'document_repository', 'rejected', 'Document Repository is not configured.', null);
        }

        $result = $this->documents->updateMetadata($payload['document_id'], $payload['changes']);

        return $this->finish('update', 'document_repository', $result['found'] ? 'updated' : 'rejected', $result['error'], $result);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function coordinateArchive(array $payload): array
    {
        if ($this->documents === null) {
            return $this->finish('archive', 'document_repository', 'rejected', 'Document Repository is not configured.', null);
        }

        $result = $this->documents->archiveReference($payload['document_id']);

        return $this->finish('archive', 'document_repository', $result['found'] ? 'archived' : 'rejected', $result['error'], $result);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function coordinateRestore(array $payload): array
    {
        if ($this->documents === null) {
            return $this->finish('restore', 'document_repository', 'rejected', 'Document Repository is not configured.', null);
        }

        $result = $this->documents->restoreReference($payload['document_id']);

        return $this->finish('restore', 'document_repository', $result['found'] ? 'restored' : 'rejected', $result['error'], $result);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     */
    private function coordinateEmbed(array $payload, array $options): array
    {
        if ($this->embeddings === null) {
            return $this->finish('embed', 'embeddings_manager', 'rejected', 'Embeddings Manager is not configured.', null);
        }

        $result = $this->embeddings->generate($payload['document_id'], $payload['text'], $options);

        return $this->finish('embed', 'embeddings_manager', $result['error'] === null ? 'embedded' : 'rejected', $result['error'], $result);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     */
    private function coordinateSearch(array $payload, array $options): array
    {
        if ($this->search === null) {
            return $this->finish('search', 'semantic_search', 'rejected', 'Semantic Search is not configured.', null);
        }

        $result = $this->search->search($payload['query'], $options);

        return $this->finish('search', 'semantic_search', $result['error'] === null ? 'searched' : 'rejected', $result['error'], $result);
    }

    private function finish(string $operation, string $targetComponent, string $outcome, ?string $error, mixed $result): array
    {
        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'knowledge.finished',
            new DateTimeImmutable(),
            self::class,
            ['operation' => $operation, 'target_component' => $targetComponent, 'outcome' => $outcome]
        ));

        return [
            'operation' => $operation,
            'target_component' => $targetComponent,
            'outcome' => $outcome,
            'error' => $error,
            'result' => $result,
        ];
    }
}
