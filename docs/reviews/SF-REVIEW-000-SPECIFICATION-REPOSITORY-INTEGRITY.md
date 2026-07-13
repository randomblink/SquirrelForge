# SF-REVIEW-000 — Specification Repository Integrity

## Review Information

**Review ID:** SF-REVIEW-000

**Scope:** All files in `docs/standards/`

**Review Type:** Repository integrity audit — filename/ID/title agreement, duplicate content, missing specification numbers, invalid cross-references, placeholder or mismatched documents. Read-only; no files were modified to produce this report.

**Status:** Findings Recorded — Remediation Not Yet Applied

**Provenance Note:** The repository contains inconsistent specification artifacts whose provenance is unknown. No claim is made here about what process or session produced them.

---

## Audit Method

For every file in `docs/standards/`: recorded filename, the internal `Document ID` field, the internal `Title` field, and the top `H1` heading; computed a SHA-256 hash to detect byte-identical duplicates; searched each file's body (excluding its own header block) for `SF-SPEC-[0-9][0-9][0-9]` mentions to check cross-reference validity against the current state of the referenced file.

---

## Findings Table

| Filename | Internal ID | Internal Title | Filename/ID/Title Agree? | Notes |
|---|---|---|---|---|
| SF-SPEC-001-ERROR-KNOWLEDGE.md | SF-SPEC-001 | Error Knowledge Specification | Yes | Clean. |
| SF-SPEC-002-RUNTIME-EVIDENCE.md | SF-SPEC-002 | Runtime Evidence Specification | Yes | Clean. |
| SF-SPEC-003-SCENARIO-ENGINEERING.md | SF-SPEC-003 | Scenario Engineering Specification | Yes | Clean. Contains one cross-reference (§4.5, "SF-SPEC-002 — Runtime Evidence Specification") that resolves correctly against SF-SPEC-002's current title. |
| SF-SPEC-004-DOCUMENTATION.md | SF-SPEC-005 | SquirrelForge Runtime Evidence Specification | **No** | Filename claims Documentation; content is a second, differently-worded Runtime Evidence specification (duplicate topic of SF-SPEC-002, not identical text). |
| SF-SPEC-005-ENGINEERING-REVIEW.md | SF-SPEC-012 | SquirrelForge Observability Specification | **No** | Filename claims Engineering Review; content is an Observability specification (production logs/metrics/traces), an unrelated topic. |
| SF-SPEC-006-REPOSITORY-VALIDATION.md | SF-SPEC-013 | SquirrelForge Test Quality Specification | **No** | Filename claims Repository Validation; content is Test Quality (test-suite standards), an unrelated topic. |
| SF-SPEC-007-SCENARIO-LIFECYCLE.md | SF-SPEC-013 | SquirrelForge Test Quality Specification | **No** | Same content as SF-SPEC-006 above (see Duplicate Content). |
| SF-SPEC-008-VERSIONING.md | SF-SPEC-013 | SquirrelForge Test Quality Specification | **No** | Same content as SF-SPEC-006 above. |
| SF-SPEC-009-TEST-FIXTURE.md | SF-SPEC-013 | SquirrelForge Test Quality Specification | **No** | Same content as SF-SPEC-006 above. Filename ironically claims "Test Fixture," a plausible-sounding but incorrect near-match to the actual "Test Quality" content. |
| SF-SPEC-010-RELEASE-READINESS.md | SF-SPEC-013 | SquirrelForge Test Quality Specification | **No** | Same content as SF-SPEC-006 above. |
| SF-SPEC-011-EVIDENCE-GOVERNANCE.md | SF-SPEC-013 | SquirrelForge Test Quality Specification | **No** | Same content as SF-SPEC-006 above. |

**Filename/ID/Title agreement: 3 of 11 (27%).**

---

## Duplicate Content

One duplicate set, confirmed by identical SHA-256 hash across six files:

```
0c61c102f3aaecf924d7fe60ff9cd6460a1969bc3b60f3bf56e9e0a73a5dfaf4
  SF-SPEC-006-REPOSITORY-VALIDATION.md
  SF-SPEC-007-SCENARIO-LIFECYCLE.md
  SF-SPEC-008-VERSIONING.md
  SF-SPEC-009-TEST-FIXTURE.md
  SF-SPEC-010-RELEASE-READINESS.md
  SF-SPEC-011-EVIDENCE-GOVERNANCE.md
```

All six are byte-for-byte identical copies of a "Test Quality Specification" (internal ID `SF-SPEC-013`). No other duplicate pairs were found; SF-SPEC-004 and SF-SPEC-005 each have unique (non-duplicate) content, despite also disagreeing with their filenames.

---

## Missing Specification Content

Filenames exist for all eleven canonical slots (001–011), so no filename is missing. However, only 3 of the 11 slots (001, 002, 003) contain content matching what their filename claims. The following eight topics currently have **no valid content**, regardless of a file existing at that path:

- Documentation (claimed by SF-SPEC-004's filename)
- Engineering Review (claimed by SF-SPEC-005's filename)
- Repository Validation (claimed by SF-SPEC-006's filename)
- Scenario Lifecycle (claimed by SF-SPEC-007's filename)
- Versioning (claimed by SF-SPEC-008's filename)
- Test Fixture (claimed by SF-SPEC-009's filename)
- Release Readiness (claimed by SF-SPEC-010's filename)
- Evidence Governance (claimed by SF-SPEC-011's filename)

---

## Invalid Cross-References

Cross-reference mentions found in file bodies (excluding each file's own header), and whether they resolve against the *current* state of the repository:

| Citing File | Cites | Resolves? | Why |
|---|---|---|---|
| SF-SPEC-003-SCENARIO-ENGINEERING.md | SF-SPEC-002 | **Yes** | SF-SPEC-002's current title matches exactly ("Runtime Evidence Specification"). |
| SF-SPEC-004-DOCUMENTATION.md | SF-SPEC-001, 002, 004, 005, 006, 007 | **No** | Describes a numbering scheme (SF-SPEC-001 = Documentation, SF-SPEC-002 = Evidence Governance, SF-SPEC-004 = Repository Validation, SF-SPEC-006 = Scenario Engineering, SF-SPEC-007 = Test Fixture) that matches none of the IDs' current titles. |
| SF-SPEC-005-ENGINEERING-REVIEW.md | SF-SPEC-003, 005, 012 | **No** | Describes itself as distinct from "SF-SPEC-005's scenario-execution evidence" while also being filed at path 005; internally incoherent regardless of external state. |
| SF-SPEC-006-REPOSITORY-VALIDATION.md (and its 5 duplicates) | SF-SPEC-004, 009, 013 | **No** | Describes SF-SPEC-009 as "Test Fixture Specification" as a stable reference point, while the current SF-SPEC-009 file is itself one of these same six duplicates, not a Test Fixture specification. |

Every invalid cross-reference found is a citation to a **numbering scheme that is not the one currently reflected by filenames on disk** — each of the three mismatched groups (004, 005, 006/dupes) appears to encode a different historical numbering attempt.

---

## Placeholder or Mismatched Documents

Eight files are mismatched (filename disagrees with internal ID/title): `SF-SPEC-004` through `SF-SPEC-011`. No file contains literal placeholder markers (e.g. "TBD", "Lorem ipsum", empty sections) — all eight contain complete, real specification text; it is simply the *wrong* specification for the filename, or a duplicate of another wrong specification.

---

## Summary

| Check | Result |
|---|---|
| Every filename matches its internal Document ID | **No** — 3 of 11 |
| Every Document ID is unique | **No** — `SF-SPEC-013` appears six times |
| Every specification has the expected title (per its filename) | **No** — 3 of 11 |
| No duplicate documents exist | **No** — one 6-way duplicate set |
| No placeholder content remains | Yes — no literal placeholders found, but 8 files contain complete wrong-content specifications, which is a distinct defect from placeholders |
| All cross-references resolve to the intended specifications | **No** — only 1 of 4 citing relationships resolves |

**Gate outcome: SF-REVIEW-000 does not pass.** Repository integrity is not yet restored.

---

## Proposed Minimum Remediation (Not Yet Applied)

Presented for review before any file is touched.

1. **SF-SPEC-004-DOCUMENTATION.md** — replace content with an actual Documentation Specification (topic currently has zero valid content anywhere in the directory), or restore a prior draft if one is known to exist elsewhere. Do not reuse the Runtime Evidence text currently inside it — that topic is already owned by SF-SPEC-002.
2. **SF-SPEC-005-ENGINEERING-REVIEW.md** — replace content with an actual Engineering Review Specification (topic currently has zero valid content anywhere in the directory). The Observability content currently inside it describes a real, distinct topic with no assigned slot in the current 11-item registry; before discarding it, confirm whether Observability should be preserved under a different, deliberately-chosen identifier.
3. **SF-SPEC-006 through SF-SPEC-011** (six files) — each currently holds an identical, wrongly-labeled copy of a Test Quality Specification. Each needs its own distinct, correct content (Repository Validation, Scenario Lifecycle, Versioning, Test Fixture, Release Readiness, Evidence Governance respectively). The genuine Test Quality content itself describes a real, distinct topic with no assigned slot in the current registry; before discarding five of its six copies, confirm whether Test Quality should be preserved under a different, deliberately-chosen identifier — the same question applies here as for Observability above.
4. **After both topics above are resolved** — re-run this audit (or an equivalent check) to confirm: every filename matches its internal ID and title, no duplicate hashes remain, and no cross-reference cites a numbering scheme other than the one on disk.

No estimate is given here for the effort of *authoring* the seven missing specifications (Documentation, Engineering Review, Repository Validation, Scenario Lifecycle, Versioning, Test Fixture, Release Readiness) — that is new-content work, not a repository-integrity fix, and is out of scope for this audit.

## Gate Status

Not satisfied. SF-SPEC-004 through SF-SPEC-011 remain unedited, per instruction. No further specification architecture work should proceed until this gate passes.
