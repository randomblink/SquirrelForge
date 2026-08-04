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
 * Of the eight components the spec lists under "Depends On", all eight
 * now have real code to route to: Document Repository (register/read/
 * update/archive/restore a document-facing reference), Embeddings
 * Manager (generate a vector for a registered asset), Semantic Search
 * (retrieve relevance-ranked references), Knowledge Registry (catalog
 * metadata), Knowledge Validator (validate), Citation Manager (cite),
 * Knowledge Versioning (version), and Knowledge Graph (relate).
 *
 * The last five were the last real gap in this codebase's "coordinator
 * predates its own specialist" pattern -- unlike the security and
 * storage instances of that same situation, these five specs
 * (`25_KNOWLEDGE/KNOWLEDGE-REGISTRY.md`, `25_KNOWLEDGE/KNOWLEDGE-VALIDATOR.md`,
 * `25_KNOWLEDGE/CITATION-MANAGER.md`, `25_KNOWLEDGE/KNOWLEDGE-VERSIONING.md`,
 * `25_KNOWLEDGE/KNOWLEDGE-GRAPH.md`) already existed in full; only their
 * implementations were missing.
 *
 * `catalog` is a deliberately separate operation from the pre-existing
 * `register`, not a rename of it: `register` still means Document
 * Repository's document-facing reference (where content lives),
 * exactly as it always has, and `catalog` means a genuinely distinct
 * concern -- Knowledge Registry's catalog entry (identifiers, trust,
 * lifecycle, and references to version/citation/relationship records).
 * A real knowledge asset plausibly needs both, and neither replaces
 * the other.
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
        'catalog' => ['name', 'type', 'source', 'owner'],
        'validate' => ['knowledge_id'],
        'cite' => ['knowledge_id', 'source_reference', 'citation_type'],
        'version' => ['knowledge_id', 'content'],
        'relate' => ['source_entity', 'relationship', 'target_entity'],
    ];

    public function __construct(
        private readonly ?SqliteDocumentRepository $documents = null,
        private readonly ?SqliteEmbeddingsManager $embeddings = null,
        private readonly ?SemanticSearchManager $search = null,
        private readonly ?SqliteKnowledgeRegistry $registry = null,
        private readonly ?SqliteKnowledgeValidator $validator = null,
        private readonly ?SqliteCitationManager $citations = null,
        private readonly ?SqliteKnowledgeVersioning $versioning = null,
        private readonly ?SqliteKnowledgeGraph $graph = null,
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
            'catalog' => $this->coordinateCatalog($payload),
            'validate' => $this->coordinateValidate($payload, $options),
            'cite' => $this->coordinateCite($payload, $options),
            'version' => $this->coordinateVersion($payload, $options),
            'relate' => $this->coordinateRelate($payload, $options),
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

    /**
     * @param array<string, mixed> $payload
     */
    private function coordinateCatalog(array $payload): array
    {
        if ($this->registry === null) {
            return $this->finish('catalog', 'knowledge_registry', 'rejected', 'Knowledge Registry is not configured.', null);
        }

        $result = $this->registry->register($payload['name'], $payload['type'], $payload['source'], $payload['owner']);

        return $this->finish('catalog', 'knowledge_registry', $result['outcome'], $result['error'], $result);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     */
    private function coordinateValidate(array $payload, array $options): array
    {
        if ($this->validator === null) {
            return $this->finish('validate', 'knowledge_validator', 'rejected', 'Knowledge Validator is not configured.', null);
        }

        $result = $this->validator->validate($payload['knowledge_id'], $options);

        return $this->finish('validate', 'knowledge_validator', $result['result'], $result['error'], $result);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     */
    private function coordinateCite(array $payload, array $options): array
    {
        if ($this->citations === null) {
            return $this->finish('cite', 'citation_manager', 'rejected', 'Citation Manager is not configured.', null);
        }

        $result = $this->citations->registerCitation(
            $payload['knowledge_id'],
            $payload['source_reference'],
            $payload['citation_type'],
            $options['locator_metadata'] ?? [],
            $options['source_version_reference'] ?? null
        );

        return $this->finish('cite', 'citation_manager', $result['outcome'], $result['error'], $result);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     */
    private function coordinateVersion(array $payload, array $options): array
    {
        if ($this->versioning === null) {
            return $this->finish('version', 'knowledge_versioning', 'rejected', 'Knowledge Versioning is not configured.', null);
        }

        $result = $this->versioning->createVersion(
            $payload['knowledge_id'],
            $payload['content'],
            $options['change_type'] ?? 'Create',
            $options['parent_version_id'] ?? null,
            $options['author'] ?? null,
            $options['validation_reference'] ?? null
        );

        return $this->finish('version', 'knowledge_versioning', $result['outcome'], $result['error'], $result);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     */
    private function coordinateRelate(array $payload, array $options): array
    {
        if ($this->graph === null) {
            return $this->finish('relate', 'knowledge_graph', 'rejected', 'Knowledge Graph is not configured.', null);
        }

        $result = $this->graph->defineRelationship(
            $payload['source_entity'],
            $payload['relationship'],
            $payload['target_entity'],
            $options['confidence'] ?? 1.0,
            $options['evidence_reference'] ?? null
        );

        return $this->finish('relate', 'knowledge_graph', $result['outcome'], $result['error'], $result);
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
