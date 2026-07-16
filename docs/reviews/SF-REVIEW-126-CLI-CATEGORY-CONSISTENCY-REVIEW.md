# SF-REVIEW-126 — CLI Category Consistency Review

# 1. Review Information

**Review ID:** SF-REVIEW-126

**Review Date:** 2026-07-15

**Reviewer:** Class B — Independent Review, per **SF-SPEC-012** Section 6.2, conducted as the category-level consistency check required by **SF-SPEC-013** Section 5.4 before baseline certification may be attempted.

**Status:** Complete

This is the seventh category consistency review in this catalog, after `SF-REVIEW-078` (Authentication), `SF-REVIEW-087` (Networking), `SF-REVIEW-094` (Plugin), `SF-REVIEW-103` (Performance), `SF-REVIEW-112` (Media), and `SF-REVIEW-119` (Theme). It treats `WP-ERROR-041` and `WP-ERROR-042` as one system, re-verifying claims fresh against current repository state rather than assuming either entry's own prior review remains accurate. This is also the first consistency review for a category whose two entries deliberately carry different severity classifications (Low and Critical) for structurally related conditions — a specific point of scrutiny below.

---

# 2. Scope

The complete set of `WP-ERROR` entries `SF-TAXONOMY-009` declares as its planned baseline:

1. `WP-ERROR-041` — WP-CLI Cannot Locate a WordPress Installation
2. `WP-ERROR-042` — WP-CLI Multisite Site Context Resolution Failure

---

# 3. Governing Specifications

- **SF-SPEC-001** Section 7 (Category Standard), Section 19 (Production Ready)
- **SF-SPEC-004** — Documentation Specification
- **SF-SPEC-012** — Engineering Review Independence Specification
- **SF-SPEC-013** Section 5.4 (Category Consistency Review)
- `SF-TAXONOMY-009` — CLI Error Taxonomy, Version 1.2

---

# 4. Evidence Examined

- Full contents of `WP-ERROR-041` and `WP-ERROR-042`, both re-read in full.
- Metadata sweep: both entries confirmed `Category: CLI`, `Status: Production Ready`. `Severity` deliberately differs (`Low` for `041`, `Critical` for `042`) — independently re-examined below rather than treated as an unexplained inconsistency.
- Cross-reference symmetry: `WP-ERROR-041` cites `WP-ERROR-042` (real link, Section 16); `WP-ERROR-042` cites `WP-ERROR-041` (real link, Section 6 and Section 16). Both citations independently re-read in full context — each accurately describes the other's own condition and the sequential-but-conditional relationship between them.
- A full Markdown link-resolution sweep across both entries (independently scripted, not reused from either entry's own prior review): zero broken links.
- `SF-TAXONOMY-009` Section 3's own status table, re-read at current (Version 1.2) state: both entries listed as `Existing, Production Ready`, matching their own actual `Status` fields.
- `find . -iname "*WP-ERROR-041*" -o -iname "*WP-ERROR-042*"`: exactly one knowledge-entry file per ID, plus the expected review-record files (`SF-REVIEW-122`/`123` for `041`, `124`/`125` for `042`), no duplicate artifact.
- **Severity-divergence review, specific to this category:** independently re-examined whether the Low/Critical split between two structurally related "WP-CLI can't establish context" conditions is a genuine, substantiated distinction or an unexplained inconsistency. Confirmed genuine: `WP-ERROR-041`'s own condition categorically cannot reach a live site (WordPress never launches at all), while `WP-ERROR-042`'s own silent-misresolution manifestation involves WordPress launching and executing completely normally, just against the wrong, live site — a live-site data-modification risk `WP-ERROR-041`'s own condition cannot produce by construction. Both entries' own Severity sections reason this explicitly and consistently with each other rather than in isolation.
- A stale-hedge sweep across the rest of the catalog for any pre-existing mention of either condition this category's own two entries now claim: one adjacent hit found (`WP-ERROR-026`'s own multisite-capability-membership bullet), independently confirmed to concern a different topic (per-site capability/role membership, not WP-CLI's own site-targeting mechanism) and correctly requiring no update.
- Terminology consistency: independently confirmed both entries consistently use "installation discovery" for `WP-ERROR-041`'s own mechanism and "site context" / "site-targeting" for `WP-ERROR-042`'s own mechanism, with no drift or conflation between the two across either entry's own text.
- The future-`Multisite`-category boundary, independently re-checked across both entries: only `WP-ERROR-042` draws this boundary (`WP-ERROR-041`'s own condition, occurring before any installation is even found, has no plausible connection to Multisite's own site-resolution mechanism at all) — confirmed this asymmetry is correct and not an oversight in `WP-ERROR-041`'s own text.
- `scripts/validate-repo.sh .`, run for this review: exit 0, all four checks clean, with no correction required within this review itself.
- `docs/engineering/FRAMEWORK-OBSERVATIONS.md`, re-read in full: no open, CLI-specific entry exists; the hub-entry observation's own two prior additions for this category (`WP-ERROR-041`'s own correction to `WP-ERROR-013`) are already reflected and do not block certification.

---

# 5. Findings

| Finding ID | Severity | Observation | Resolution |
|---|---|---|---|
| — | Conforming | Two-entry sequential-but-conditional partition holds exactly; no overlap or gap found between the two entries' own Scope sections. | N/A |
| — | Conforming | Cross-reference symmetry between `WP-ERROR-041` and `WP-ERROR-042` confirmed accurate on both sides. | N/A |
| — | Conforming | `SF-TAXONOMY-009` Section 3's own status table accurately reflects both entries' actual `Status` fields. | N/A |
| — | Conforming | Severity divergence (Low versus Critical) independently re-confirmed as a genuine, substantiated distinction reasoned consistently across both entries, not an unexplained inconsistency. | N/A |
| — | Conforming | The asymmetric future-`Multisite`-category boundary (drawn only by `WP-ERROR-042`) confirmed correct, not an oversight. | N/A |
| — | Conforming | Terminology ("installation discovery" versus "site context"/"site-targeting") used consistently with no drift. | N/A |
| — | Conforming | Zero broken links across both entries; zero duplicate artifacts found. | N/A |
| — | Conforming | Related Errors (Section 16) intro sentences in both entries match the catalog's own majority wording; `scripts/validate-repo.sh` Check D confirms this mechanically. | N/A |
| — | Conforming | Stale-hedge sweep: one adjacent mention found (`WP-ERROR-026`), independently confirmed unrelated and correctly requiring no update. | N/A |

No Minor, Major, or Critical findings.

---

# 6. Second Confirmation: Category Shape and the Hub-Entry Pattern

This category's own shape differs from every prior one in this catalog: two entries, narrower in scope than a typical category, with severities that deliberately diverge rather than share a common classification. This review confirms that divergence is not a defect — it is the correct outcome of `SF-TAXONOMY-009`'s own reasoning (Section 4) that the category's own genuinely available territory was unusually narrow because so much adjacent territory was already claimed elsewhere, and that the two entries it does contain describe conditions with genuinely different worst cases.

This category also produced two further, confirmed instances of the hub-entry cross-reference-accumulation pattern `docs/engineering/FRAMEWORK-OBSERVATIONS.md` already tracks (`WP-ERROR-041`'s own correction to `WP-ERROR-013`'s Distinction section) and one instance of ordinary sequential-authoring staleness (`WP-ERROR-041`'s own forward citation to `WP-ERROR-042` going stale once the latter was authored, caught by `validate-repo.sh` Check A during `SF-REVIEW-125`). Both are consistent with this catalog's own established, well-functioning detection pattern — caught by the new entry's own independent review before certification, in both cases — and require no framework action beyond what is already tracked.

---

# 7. Outcome

**Approved.**

**Basis:** Zero findings requiring correction within this review itself. Every criterion — partition integrity, cross-reference symmetry, taxonomy status accuracy, the deliberate severity divergence, terminology consistency, and stale-hedge freedom — independently verified as conforming.

---

# 8. Remaining Risks

- This review, like every review in this catalog, was conducted entirely by the same class of agent (Claude Code).
- The deferred `--ssh=`/`--http=` remote-transport candidate (`SF-TAXONOMY-009` Section 5) remains genuinely undecided, unchanged by this review.
- Both of this category's own forward-looking boundary claims — against the not-yet-authored `WP-ERROR-010`/`011` and against the future `Multisite` category — remain untestable until those categories are eventually taxonomized, per `SF-REVIEW-121`'s own Remaining Risks.
- This is the first category in this catalog whose own entry count (two) and severity spread (Low to Critical) were both driven primarily by how much adjacent territory was already claimed elsewhere, rather than by the category's own internal richness; if a future category exhibits the same shape, that would be a second data point worth naming explicitly rather than treating each occurrence as a one-off.

---

# 9. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-15 | Initial consistency review of the CLI category. Zero findings requiring correction. Confirmed the two-entry sequential-but-conditional partition, cross-reference symmetry, taxonomy status accuracy, and — the review's own specific point of scrutiny — that the deliberate Low/Critical severity divergence between WP-ERROR-041 and WP-ERROR-042 is a genuine, substantiated distinction rather than an unexplained inconsistency. Confirmed the asymmetric future-Multisite-category boundary is correct, terminology is consistent, and one adjacent stale-hedge candidate (WP-ERROR-026) correctly requires no update. Noted this category's own unusual shape (narrow entry count, diverging severities) as a direct, well-reasoned consequence of the taxonomy's own research rather than a defect. Approved. | Approved |
