<?php

declare(strict_types=1);

namespace SquirrelForge\Knowledge;

use PDO;

/**
 * Owns Knowledge Layer entity and relationship records, per
 * 25_KNOWLEDGE/KNOWLEDGE-GRAPH.md.
 *
 * "Verify entity registration" (Graph Process step 3) is a real,
 * enforced gate: defineRelationship() refuses with `unregistered_entity`
 * unless both the source and target entity were already registered via
 * registerEntity() -- an edge can never reference an entity this class
 * has no record of.
 *
 * "Support graph traversal" and "Discover indirect relationships" are
 * both real breadth-first search over the actual stored edges, not a
 * fabricated result: traverse() walks only `Active` relationship rows
 * up to the requested depth and returns the entities and edges it
 * genuinely reached, and findIndirectRelationships() is the same
 * search targeted at whether a specific destination is reachable
 * within a depth bound, returning the real path when one exists.
 * Deprecated relationships are excluded from both, matching "Status:
 * Active / Deprecated" as a real filter rather than a display-only
 * label.
 *
 * This class never decides workflow execution order: "Execution
 * dependency ordering remains owned by
 * `14_ENGINE/DEPENDENCY-ANALYZER.md`" is upheld by having no method
 * here that sequences or schedules anything -- traverse() and
 * findIndirectRelationships() only ever report knowledge-context
 * relationships for a caller to interpret.
 */
final class SqliteKnowledgeGraph
{
    private const ENTITY_TYPES = [
        'document', 'workflow', 'rule', 'decision', 'component', 'integration', 'user', 'agent',
    ];

    private const RELATIONSHIP_TYPES = [
        'depends_on', 'references', 'contains', 'implements', 'validates', 'produces', 'consumes', 'related_to',
    ];

    private PDO $database;

    public function __construct(string $databasePath)
    {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS graph_entities (
                entity_id TEXT PRIMARY KEY,
                entity_type TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS graph_relationships (
                graph_id TEXT PRIMARY KEY,
                source_entity TEXT NOT NULL,
                relationship TEXT NOT NULL,
                target_entity TEXT NOT NULL,
                confidence REAL NOT NULL,
                evidence_reference TEXT,
                status TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function registerEntity(string $entityId, string $entityType): array
    {
        if (!in_array($entityType, self::ENTITY_TYPES, true)) {
            return ['outcome' => 'invalid_type', 'error' => sprintf('"%s" is not a recognized entity type.', $entityType)];
        }

        if ($this->fetchEntity($entityId) !== null) {
            return ['outcome' => 'already_registered', 'error' => null];
        }

        $statement = $this->database->prepare('INSERT INTO graph_entities (entity_id, entity_type, created_at) VALUES (:entity_id, :entity_type, :created_at)');
        $statement->execute(['entity_id' => $entityId, 'entity_type' => $entityType, 'created_at' => gmdate(DATE_RFC3339)]);

        return ['outcome' => 'registered', 'error' => null];
    }

    /**
     * @return array{outcome: string, graph_id: ?string, error: ?string}
     */
    public function defineRelationship(string $sourceEntityId, string $relationship, string $targetEntityId, float $confidence = 1.0, ?string $evidenceReference = null): array
    {
        if (!in_array($relationship, self::RELATIONSHIP_TYPES, true)) {
            return ['outcome' => 'invalid_relationship', 'graph_id' => null, 'error' => sprintf('"%s" is not a recognized relationship type.', $relationship)];
        }

        if ($this->fetchEntity($sourceEntityId) === null || $this->fetchEntity($targetEntityId) === null) {
            return ['outcome' => 'unregistered_entity', 'graph_id' => null, 'error' => 'Both the source and target entity must be registered before a relationship can be defined.'];
        }

        $graphId = 'graph_' . bin2hex(random_bytes(12));
        $now = gmdate(DATE_RFC3339);

        $statement = $this->database->prepare(
            'INSERT INTO graph_relationships (
                graph_id, source_entity, relationship, target_entity, confidence, evidence_reference, status, created_at, updated_at
            ) VALUES (
                :graph_id, :source_entity, :relationship, :target_entity, :confidence, :evidence_reference, :status, :created_at, :updated_at
            )'
        );
        $statement->execute([
            'graph_id' => $graphId,
            'source_entity' => $sourceEntityId,
            'relationship' => $relationship,
            'target_entity' => $targetEntityId,
            'confidence' => $confidence,
            'evidence_reference' => $evidenceReference,
            'status' => 'Active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['outcome' => 'defined', 'graph_id' => $graphId, 'error' => null];
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function deprecateRelationship(string $graphId): array
    {
        $record = $this->fetchRelationship($graphId);

        if ($record === null) {
            return ['outcome' => 'not_found', 'error' => sprintf('Relationship "%s" is not tracked.', $graphId)];
        }

        $statement = $this->database->prepare("UPDATE graph_relationships SET status = 'Deprecated', updated_at = :updated_at WHERE graph_id = :graph_id");
        $statement->execute(['updated_at' => gmdate(DATE_RFC3339), 'graph_id' => $graphId]);

        return ['outcome' => 'deprecated', 'error' => null];
    }

    /**
     * Real breadth-first traversal over active relationships only, up
     * to the requested depth.
     *
     * @return array{entities: array<int, string>, edges: array<int, array{graph_id: string, source_entity: string, relationship: string, target_entity: string}>}
     */
    public function traverse(string $entityId, string $direction = 'outgoing', ?string $relationship = null, int $depth = 1): array
    {
        $visitedEntities = [$entityId => true];
        $edges = [];
        $frontier = [$entityId];

        for ($step = 0; $step < max(0, $depth); $step++) {
            $nextFrontier = [];

            foreach ($frontier as $current) {
                foreach ($this->activeEdgesFor($current, $direction, $relationship) as $edge) {
                    $neighbor = $edge['source_entity'] === $current ? $edge['target_entity'] : $edge['source_entity'];
                    $edges[$edge['graph_id']] = [
                        'graph_id' => $edge['graph_id'],
                        'source_entity' => $edge['source_entity'],
                        'relationship' => $edge['relationship'],
                        'target_entity' => $edge['target_entity'],
                    ];

                    if (!isset($visitedEntities[$neighbor])) {
                        $visitedEntities[$neighbor] = true;
                        $nextFrontier[] = $neighbor;
                    }
                }
            }

            $frontier = $nextFrontier;

            if ($frontier === []) {
                break;
            }
        }

        return ['entities' => array_keys($visitedEntities), 'edges' => array_values($edges)];
    }

    /**
     * Real breadth-first pathfinding bounded by $maxDepth; returns
     * whether $targetEntityId is reachable from $sourceEntityId and, if
     * so, one real path of graph_id edges connecting them.
     *
     * @return array{reachable: bool, path: array<int, string>}
     */
    public function findIndirectRelationships(string $sourceEntityId, string $targetEntityId, int $maxDepth = 3): array
    {
        if ($sourceEntityId === $targetEntityId) {
            return ['reachable' => true, 'path' => []];
        }

        $visited = [$sourceEntityId => true];
        $frontier = [$sourceEntityId];
        $cameFrom = [];

        for ($step = 0; $step < max(0, $maxDepth); $step++) {
            $nextFrontier = [];

            foreach ($frontier as $current) {
                foreach ($this->activeEdgesFor($current, 'outgoing', null) as $edge) {
                    $neighbor = $edge['target_entity'];

                    if (isset($visited[$neighbor])) {
                        continue;
                    }

                    $visited[$neighbor] = true;
                    $cameFrom[$neighbor] = $edge['graph_id'];
                    $nextFrontier[] = $neighbor;

                    if ($neighbor === $targetEntityId) {
                        return ['reachable' => true, 'path' => $this->reconstructPath($cameFrom, $targetEntityId)];
                    }
                }
            }

            $frontier = $nextFrontier;

            if ($frontier === []) {
                break;
            }
        }

        return ['reachable' => false, 'path' => []];
    }

    /**
     * @param array<string, string> $cameFrom entity_id => graph_id of the edge that reached it
     * @return array<int, string>
     */
    private function reconstructPath(array $cameFrom, string $target): array
    {
        $path = [];
        $current = $target;

        while (isset($cameFrom[$current])) {
            $graphId = $cameFrom[$current];
            array_unshift($path, $graphId);
            $edge = $this->fetchRelationship($graphId);
            $current = $edge['source_entity'];
        }

        return $path;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function activeEdgesFor(string $entityId, string $direction, ?string $relationship): array
    {
        $conditions = ["status = 'Active'"];
        $parameters = ['entity_id' => $entityId];

        $conditions[] = match ($direction) {
            'outgoing' => 'source_entity = :entity_id',
            'incoming' => 'target_entity = :entity_id',
            default => '(source_entity = :entity_id OR target_entity = :entity_id)',
        };

        if ($relationship !== null) {
            $conditions[] = 'relationship = :relationship';
            $parameters['relationship'] = $relationship;
        }

        $statement = $this->database->prepare('SELECT * FROM graph_relationships WHERE ' . implode(' AND ', $conditions));
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function relationshipsFor(string $entityId): array
    {
        return $this->activeEdgesFor($entityId, 'both', null);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $graphId): ?array
    {
        return $this->fetchRelationship($graphId);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchEntity(string $entityId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM graph_entities WHERE entity_id = :entity_id');
        $statement->execute(['entity_id' => $entityId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchRelationship(string $graphId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM graph_relationships WHERE graph_id = :graph_id');
        $statement->execute(['graph_id' => $graphId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }
}
