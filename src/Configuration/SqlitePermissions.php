<?php

declare(strict_types=1);

namespace SquirrelForge\Configuration;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Governance\SqlitePolicyEngine;

/**
 * The declarative, configuration-scoped policy describing which
 * actors may perform which actions on which resources -- the static
 * rulebook, not the runtime decision, per
 * 21_CONFIGURATION/PERMISSIONS.md -- the third real component in
 * 21_CONFIGURATION.
 *
 * "Permissions is a policy document, not a decision engine. It does
 * not evaluate a specific request or grant or deny access at
 * runtime -- that is `24_SECURITY/AUTHORIZATION-MANAGER.md`'s
 * responsibility" (Purpose) is the hard line this class draws around
 * itself: `isDeclared()` answers a narrower, honest question --
 * "does an active declaration exist for this actor/capability/
 * resource" -- combining no identity verification, role context, or
 * governance signal the way a real authorization decision would. That
 * distinction matters: this method never becomes the "grant/deny
 * decision" the Purpose forbids this class from making, it only makes
 * the declaration "available for `AUTHORIZATION-MANAGER.md` to
 * evaluate at runtime" (Process step 4), which is this spec's own
 * Responsibility.
 *
 * "Validate the declaration against `POLICY-ENGINE.md`" and "keep
 * permission declarations consistent with" it are genuine composition
 * of the already-real `SqlitePolicyEngine::evaluate()` -- when
 * configured, every `declare()` call is checked against it, and
 * `SqlitePolicyEngine`'s own real fail-closed default ("no applicable
 * policy... denying by default") means a declaration is only accepted
 * when an actual governance policy allows it, the same discipline
 * every other governance-backed component in this codebase already
 * requires (`SqliteIntegrationGovernance`, `IntegrationManager`).
 *
 * "Each capability is declared and evaluated independently; holding
 * one does not imply another" is upheld literally: a declaration
 * names exactly one of the six real Capability Types, and
 * `isDeclared()` never widens a lookup across capabilities -- checking
 * `write` never returns true because `read` happened to be declared.
 *
 * "Expire or revoke declarations when duration lapses or governance
 * requires it" is real, checked logic: `expireDue()` marks every
 * `time_limited` declaration whose `expires_at` has passed as
 * `expired`, and `revoke()` marks one `active` declaration `revoked`
 * with a stated reason -- `isDeclared()` only ever matches `active`
 * declarations, so an expired or revoked one silently stops counting,
 * upholding "absence of a matching declaration is treated as denial"
 * (Rule) without this class ever issuing that denial itself.
 *
 * SQLite-backed for "version and record changes to permission
 * declarations" (Responsibilities) and "record every change... for
 * audit" (Process step 6) -- both genuinely need state that persists
 * across separate calls, the same reasoning this codebase's other
 * `Sqlite*` policy/registry components already established.
 */
final class SqlitePermissions
{
    private const CAPABILITIES = ['read', 'write', 'execute', 'network', 'secret', 'external_side_effect'];

    private const DURATIONS = ['persistent', 'session_scoped', 'time_limited'];

    private PDO $database;

    public function __construct(string $databasePath, private readonly ?SqlitePolicyEngine $policyEngine = null)
    {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS permission_declarations (
                declaration_id TEXT PRIMARY KEY,
                actor TEXT NOT NULL,
                capability TEXT NOT NULL,
                resource TEXT NOT NULL,
                duration TEXT NOT NULL,
                expires_at TEXT,
                status TEXT NOT NULL,
                revocation_reason TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
    }

    /**
     * Process steps 1-3, 6: registers a permission declaration.
     *
     * @param array{actor?: ?string, capability?: ?string, resource?: ?string, duration?: ?string, expires_at?: ?string} $declaration
     * @return array{outcome: string, declaration_id: ?string, error: ?string}
     */
    public function declare(array $declaration): array
    {
        $actor = $declaration['actor'] ?? null;
        $capability = $declaration['capability'] ?? null;
        $resource = $declaration['resource'] ?? null;
        $duration = $declaration['duration'] ?? null;

        if (!$this->present($actor) || !$this->present($resource)) {
            return ['outcome' => 'invalid', 'declaration_id' => null, 'error' => 'A declaration requires a non-empty actor and resource.'];
        }

        if (!is_string($capability) || !in_array($capability, self::CAPABILITIES, true)) {
            return ['outcome' => 'invalid', 'declaration_id' => null, 'error' => sprintf('"%s" is not one of this spec\'s named Capability Types.', (string) ($capability ?? ''))];
        }

        if (!is_string($duration) || !in_array($duration, self::DURATIONS, true)) {
            return ['outcome' => 'invalid', 'declaration_id' => null, 'error' => sprintf('"%s" is not one of this spec\'s named Duration values.', (string) ($duration ?? ''))];
        }

        $expiresAt = $declaration['expires_at'] ?? null;

        if ($duration === 'time_limited' && !$this->present($expiresAt)) {
            return ['outcome' => 'invalid', 'declaration_id' => null, 'error' => 'A time_limited declaration requires an expires_at.'];
        }

        if ($this->policyEngine !== null) {
            $decision = $this->policyEngine->evaluate(
                'permission_declaration_' . bin2hex(random_bytes(8)),
                ['actor' => $actor, 'capability' => $capability, 'resource' => $resource, 'duration' => $duration],
                'resource_access'
            );

            if ($decision['decision'] !== 'allowed') {
                return ['outcome' => 'rejected', 'declaration_id' => null, 'error' => sprintf('Policy Engine did not allow this declaration: %s', $decision['rationale'] ?? 'no rationale given.')];
            }
        }

        $declarationId = 'permission_' . bin2hex(random_bytes(12));
        $now = (new DateTimeImmutable())->format(DATE_ATOM);

        $statement = $this->database->prepare(
            'INSERT INTO permission_declarations (declaration_id, actor, capability, resource, duration, expires_at, status, created_at, updated_at)
             VALUES (:declaration_id, :actor, :capability, :resource, :duration, :expires_at, :status, :created_at, :updated_at)'
        );
        $statement->execute([
            'declaration_id' => $declarationId,
            'actor' => $actor,
            'capability' => $capability,
            'resource' => $resource,
            'duration' => $duration,
            'expires_at' => $duration === 'time_limited' ? $expiresAt : null,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['outcome' => 'declared', 'declaration_id' => $declarationId, 'error' => null];
    }

    /**
     * A narrow, honest declaration lookup -- not an authorization
     * decision. True only when an `active` declaration exists for this
     * exact actor/capability/resource combination.
     */
    public function isDeclared(string $actor, string $capability, string $resource): bool
    {
        $statement = $this->database->prepare(
            "SELECT 1 FROM permission_declarations
             WHERE actor = :actor AND capability = :capability AND resource = :resource AND status = 'active'
             LIMIT 1"
        );
        $statement->execute(['actor' => $actor, 'capability' => $capability, 'resource' => $resource]);

        return $statement->fetch() !== false;
    }

    /**
     * @return array{outcome: string, declaration_id: string, error: ?string}
     */
    public function revoke(string $declarationId, string $reason): array
    {
        $existing = $this->get($declarationId);

        if ($existing === null) {
            return ['outcome' => 'not_found', 'declaration_id' => $declarationId, 'error' => sprintf('Declaration "%s" does not exist.', $declarationId)];
        }

        if ($existing['status'] !== 'active') {
            return ['outcome' => 'already_' . $existing['status'], 'declaration_id' => $declarationId, 'error' => sprintf('Declaration "%s" is already "%s".', $declarationId, $existing['status'])];
        }

        $statement = $this->database->prepare(
            "UPDATE permission_declarations SET status = 'revoked', revocation_reason = :reason, updated_at = :updated_at WHERE declaration_id = :declaration_id"
        );
        $statement->execute(['reason' => $reason, 'updated_at' => (new DateTimeImmutable())->format(DATE_ATOM), 'declaration_id' => $declarationId]);

        return ['outcome' => 'revoked', 'declaration_id' => $declarationId, 'error' => null];
    }

    /**
     * Marks every `time_limited` declaration whose `expires_at` has
     * passed as `expired`.
     *
     * @return array<int, string> the declaration_id of every entry just expired.
     */
    public function expireDue(?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable();
        $nowFormatted = $now->format(DATE_ATOM);

        $select = $this->database->prepare(
            "SELECT declaration_id FROM permission_declarations
             WHERE status = 'active' AND duration = 'time_limited' AND expires_at <= :now"
        );
        $select->execute(['now' => $nowFormatted]);
        $due = array_column($select->fetchAll(), 'declaration_id');

        if ($due === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($due), '?'));
        $update = $this->database->prepare("UPDATE permission_declarations SET status = 'expired', updated_at = ? WHERE declaration_id IN ({$placeholders})");
        $update->execute([$nowFormatted, ...$due]);

        return $due;
    }

    /**
     * @return ?array<string, mixed>
     */
    public function get(string $declarationId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM permission_declarations WHERE declaration_id = :declaration_id');
        $statement->execute(['declaration_id' => $declarationId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history(string $actor): array
    {
        $statement = $this->database->prepare('SELECT * FROM permission_declarations WHERE actor = :actor ORDER BY rowid ASC');
        $statement->execute(['actor' => $actor]);

        return $statement->fetchAll();
    }

    private function present(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }
}
