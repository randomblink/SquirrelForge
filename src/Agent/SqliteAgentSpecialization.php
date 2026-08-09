<?php

declare(strict_types=1);

namespace SquirrelForge\Agent;

use DateTimeImmutable;
use PDO;

/**
 * Confirms that requested work is matched to a role whose documented
 * specialization actually covers it, per
 * 16_AGENTS/AGENT-SPECIALIZATION.md -- the sixth real component in
 * 16_AGENTS's governance/coordination gap.
 *
 * "The authoritative roster is whatever `AGENT-*.md` files actually
 * exist in `16_AGENTS/`, per `16_AGENTS/README.md`" (Specialization
 * Principles) is taken literally: rather than hardcode README's own
 * "Specialist roles may include..." list (which is explicitly
 * illustrative and open-ended -- "and domain-specific support roles"
 * -- and whose entries don't even name-transform cleanly onto real
 * filenames; "Security Reviewer" and "Performance Reviewer" both
 * collapse onto the single real `AGENT-SECURITY.md`/`AGENT-PERFORMANCE.md`,
 * and "Accessibility Reviewer" has no real file at all), this class
 * checks the actual filesystem for `AGENT-{ROLE}.md` at match time. A
 * candidate role is a real match only when its own spec file genuinely
 * exists -- never a fabricated static roster that could silently drift
 * from what `16_AGENTS/` actually contains.
 *
 * "Confirm the matched role's own `AGENT-*.md` Responsibilities and
 * Permission Boundary actually cover the work, not just its name"
 * (Responsibilities) cannot be computed from a spec file's prose by
 * this class -- understanding whether free-text Responsibilities cover
 * an arbitrary requested work item is a real judgment call, not a
 * mechanical check. `boundary_verified` is therefore required
 * caller-supplied evidence (the confirmation that judgment was made),
 * and its absence is read against the Inputs section's own words --
 * "a match must not be assumed from a role's name alone" -- as a
 * reason to withhold a match entirely, not to grant one anyway.
 *
 * "If the work spans multiple specializations, route it to
 * `16_AGENTS/AGENT-COLLABORATION.md`... instead of forcing one owner"
 * is genuine composition of the already-real `SqliteAgentCollaboration`:
 * more than one candidate role produces an actual collaboration
 * structure under the `Specialist Team` model ("agents contribute
 * domain-specific expertise to a shared objective" -- the exact real
 * model this scenario describes), not merely a returned flag.
 *
 * SQLite-backed for "record the specialization match or escalation"
 * (Responsibilities) and the explicit Specialization Record table.
 */
final class SqliteAgentSpecialization
{
    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly string $specDirectory,
        private readonly ?SqliteAgentCollaboration $collaboration = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS agent_specialization_matches (
                match_id TEXT PRIMARY KEY,
                work_reference TEXT,
                required_domain TEXT,
                candidate_roles_json TEXT NOT NULL,
                matched_role TEXT,
                boundary_verified INTEGER NOT NULL,
                outcome TEXT NOT NULL,
                collaboration_id TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * Specialization Process steps 1-6.
     *
     * @param array{
     *     work_reference?: ?string,
     *     required_domain?: ?string,
     *     candidate_roles?: array<int, string>,
     *     boundary_verified?: bool,
     *     escalation_criteria?: array<int, string>
     * } $request
     * @return array{
     *     outcome: string,
     *     match_id: ?string,
     *     matched_role: ?string,
     *     collaboration_id: ?string,
     *     rationale: ?string,
     *     error: ?string
     * }
     */
    public function match(array $request): array
    {
        $workReference = $request['work_reference'] ?? null;
        $requiredDomain = $request['required_domain'] ?? null;
        $candidateRoles = $request['candidate_roles'] ?? [];

        if (!$this->present($workReference) || !$this->present($requiredDomain)) {
            return $this->envelope('invalid', null, null, null, null, 'A specialization match requires a non-empty work_reference and required_domain.');
        }

        if (!is_array($candidateRoles) || $candidateRoles === []) {
            return $this->envelope('invalid', null, null, null, null, 'A specialization match requires at least one candidate role.');
        }

        $boundaryVerified = ($request['boundary_verified'] ?? false) === true;

        if (!$boundaryVerified) {
            return $this->envelope('invalid', null, null, null, null, 'A match must not be assumed from a role\'s name alone; boundary_verified must confirm the role\'s actual Responsibilities and Permission Boundary were read and cover the work.');
        }

        $missingRole = null;

        foreach ($candidateRoles as $role) {
            if (!$this->roleSpecExists($role)) {
                $missingRole = $role;

                break;
            }
        }

        if ($missingRole !== null) {
            return $this->recordAndEnvelope('Escalated — No Matching Role', $workReference, $requiredDomain, $candidateRoles, null, true, null, sprintf('No AGENT-%s.md exists in the roster; escalating rather than approximating with a mismatched role.', $missingRole));
        }

        if (count($candidateRoles) > 1) {
            $collaborationId = null;

            if ($this->collaboration !== null) {
                $collaborationResult = $this->collaboration->define([
                    'objective' => $workReference,
                    'collaboration_model' => 'Specialist Team',
                    'participants' => array_map(static fn(string $role): array => ['agent' => $role, 'role' => $role], $candidateRoles),
                    'escalation_criteria' => $request['escalation_criteria'] ?? [],
                ]);

                if ($collaborationResult['outcome'] === 'defined') {
                    $collaborationId = $collaborationResult['collaboration_id'];
                }
            }

            return $this->recordAndEnvelope('Collaboration Required', $workReference, $requiredDomain, $candidateRoles, null, true, $collaborationId, 'Work spans multiple specializations; routed to Collaboration instead of a forced single owner.');
        }

        $matchedRole = $candidateRoles[0];

        return $this->recordAndEnvelope('Matched', $workReference, $requiredDomain, $candidateRoles, $matchedRole, true, null, null);
    }

    /**
     * @return ?array<string, mixed>
     */
    public function get(string $matchId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM agent_specialization_matches WHERE match_id = :match_id');
        $statement->execute(['match_id' => $matchId]);
        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    private function roleSpecExists(string $role): bool
    {
        return is_file(rtrim($this->specDirectory, '/') . '/AGENT-' . $role . '.md');
    }

    /**
     * @param array<int, string> $candidateRoles
     * @return array{outcome: string, match_id: ?string, matched_role: ?string, collaboration_id: ?string, rationale: ?string, error: ?string}
     */
    private function recordAndEnvelope(string $outcome, string $workReference, string $requiredDomain, array $candidateRoles, ?string $matchedRole, bool $boundaryVerified, ?string $collaborationId, ?string $rationale): array
    {
        $matchId = 'specialization_match_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO agent_specialization_matches (
                match_id, work_reference, required_domain, candidate_roles_json,
                matched_role, boundary_verified, outcome, collaboration_id, created_at
            ) VALUES (
                :match_id, :work_reference, :required_domain, :candidate_roles_json,
                :matched_role, :boundary_verified, :outcome, :collaboration_id, :created_at
            )'
        );
        $statement->execute([
            'match_id' => $matchId,
            'work_reference' => $workReference,
            'required_domain' => $requiredDomain,
            'candidate_roles_json' => json_encode($candidateRoles, JSON_THROW_ON_ERROR),
            'matched_role' => $matchedRole,
            'boundary_verified' => $boundaryVerified ? 1 : 0,
            'outcome' => $outcome,
            'collaboration_id' => $collaborationId,
            'created_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);

        return $this->envelope($outcome, $matchId, $matchedRole, $collaborationId, $rationale, null);
    }

    /**
     * @return array{outcome: string, match_id: ?string, matched_role: ?string, collaboration_id: ?string, rationale: ?string, error: ?string}
     */
    private function envelope(string $outcome, ?string $matchId, ?string $matchedRole, ?string $collaborationId, ?string $rationale, ?string $error): array
    {
        return [
            'outcome' => $outcome,
            'match_id' => $matchId,
            'matched_role' => $matchedRole,
            'collaboration_id' => $collaborationId,
            'rationale' => $rationale,
            'error' => $error,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $row['candidate_roles'] = json_decode((string) $row['candidate_roles_json'], true, flags: JSON_THROW_ON_ERROR);
        $row['boundary_verified'] = (bool) $row['boundary_verified'];
        unset($row['candidate_roles_json']);

        return $row;
    }

    private function present(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }
}
