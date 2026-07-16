# SF-REVIEW-096 — SF-TAXONOMY-006 Independent Review

# 1. Review Information

**Review ID:** SF-REVIEW-096

**Review Date:** 2026-07-14

**Reviewer:** Class B — Independent Review, per **SF-SPEC-012** Section 6.2, conducted per this project's established practice of independently reviewing a category's taxonomy before entry authoring begins (`SF-REVIEW-034`, `045`, `069`, `080`, `089`), not as a normative requirement `SF-TAXONOMY-006` itself imposes.

**Status:** Complete

This taxonomy's own drafting required more cross-entry research than any prior taxonomy in this catalog: four existing, Production-Ready entries across three other categories (`WP-ERROR-021`, `025`, `027`, `030`) already claim a specific caching-related symptom as their own condition. Per the lesson `SF-TAXONOMY-005`'s own review-scope gap taught (disclosed in `FRAMEWORK-OBSERVATIONS.md` — a taxonomy review verifying only claims the artifact names directly can miss a conflict with an entry it never named), this review gives particular scrutiny to whether `SF-TAXONOMY-006`'s own exclusions are independently verified against each of those four entries' own actual text, not merely asserted by the taxonomy itself.

---

# 2. Artifact Reviewed

`SF-TAXONOMY-006` — Caching / Performance Error Taxonomy, Version 1.0, at `docs/standards/SF-TAXONOMY-006-CACHING-PERFORMANCE-ERROR-TAXONOMY.md`.

---

# 3. Governing Specifications

- **SF-SPEC-013 — Knowledge Category Lifecycle Specification** (Section 5.1, Category Entry Criteria)
- **SF-SPEC-001 — Error Knowledge Specification** (Section 7, confirming `Performance` is an approved category value)
- **SF-SPEC-004 — Documentation Specification** (internal consistency, cross-references)

---

# 4. Review Scope

This review independently determines whether `SF-TAXONOMY-006` satisfies **SF-SPEC-013** Section 5.1, with particular attention to two things: (1) independently re-reading each of the four entries (`WP-ERROR-021`, `025`, `027`, `030`) this taxonomy claims already own a specific caching-related symptom, to confirm the taxonomy's own characterization of each is accurate rather than assumed; and (2) applying the specific lesson `FRAMEWORK-OBSERVATIONS.md`'s newest entry names — checking for a conflicting claim in an entry this taxonomy's own text does *not* name, not only verifying the claims it does make.

---

# 5. Evidence Examined

- Full contents of `SF-TAXONOMY-006`, read in full.
- `SF-SPEC-001` Section 7, independently re-read to confirm `Performance` is an approved category value.
- `WP-ERROR-021` Section 10 and Section 11, independently re-read in full to verify the taxonomy's claim that a stale-cached-404 cause is already documented with its own dedicated diagnosis step and recovery action.
- `WP-ERROR-025` Section 10 and Section 15, independently re-read in full to verify the taxonomy's claim that a cross-user cookie-leakage-via-cache condition is already documented, including its own explicit security-incident-level treatment.
- `WP-ERROR-027` Section 10, 11, 12, and 14, independently re-read in full to verify the taxonomy's claim that stale-cached-nonce is already documented with its own dedicated diagnosis, recovery, and prevention content.
- `WP-ERROR-030` Section 10 and Section 11, independently re-read in full to verify the taxonomy's claim that stale-cached CORS headers are already documented with a dedicated diagnosis step and recovery action.
- `WP-ERROR-009` Section 10, `WP-ERROR-019` Section 8/10, `WP-ERROR-020` Section 10, and `WP-ERROR-013` Section 10/17, each independently re-read to verify the taxonomy's remaining exclusion claims (query performance, disk-backed cache capacity/permission, `wp_options` bloat, drop-in bootstrap fatal errors).
- A full-text search for "cache", "caching", "transient", "object cache", "opcache", "Redis", and "Memcached" across every file in `docs/knowledge/wp-errors/` and `docs/standards/SF-TAXONOMY-*.md`, independently re-run rather than relying on the taxonomy's own drafting-time sweep, specifically to test whether any entry *not* named in the taxonomy's own Section 2 nonetheless makes a conflicting claim — the specific failure mode `FRAMEWORK-OBSERVATIONS.md`'s newest entry names. No additional conflicting entry was found beyond the four the taxonomy already names and excludes.
- `ls docs/knowledge/wp-errors/ | grep -E "WP-ERROR-033|034|035"`, confirming none of the three planned IDs currently exist.
- `grep -n '\bmust\b'` (excluding `must-use`) and a drafting-language sweep against the full document.
- Independent technical assessment of the object-cache fallback-behavior claim in `WP-ERROR-033`'s own Owns text — this surfaced Finding IF-1 below.

---

# 6. Findings

| Finding ID | Severity | Requirement or Criterion | Observation | Evidence | Required Action | Resolution Status |
|---|---|---|---|---|---|---|
| — | Conforming | SF-SPEC-013 §5.1, bullet 1 (boundary) | Section 2 declares a clear positive boundary (caching *mechanisms'* own operational state) and explicitly, extensively distinguishes it from the far broader territory a naive reading of "Caching/Performance" might suggest (any stale-cache symptom anywhere). | Section 2. | None. |
| — | Conforming | Accurate representation of WP-ERROR-021 | Independently re-read `WP-ERROR-021` Section 10/11 in full: confirmed "a caching layer or CDN continuing to serve a stale, previously-cached 404 response" is present as a named Common Cause, with a dedicated Diagnosis step (11) and Recovery action (12) addressing it specifically. The taxonomy's characterization is accurate. | `WP-ERROR-021`, full text, Section 5 above. | None. |
| — | Conforming | Accurate representation of WP-ERROR-025 | Independently re-read `WP-ERROR-025` Section 10/15 in full: confirmed a caching layer serving one user's `Set-Cookie` header to another is documented as this entry's own condition, and Section 15 explicitly treats it as a session-hijacking-equivalent security exposure, not merely a functional annoyance. The taxonomy's characterization is accurate. | `WP-ERROR-025`, full text, Section 5 above. | None. |
| — | Conforming | Accurate representation of WP-ERROR-027 | Independently re-read `WP-ERROR-027` Section 10/11/12/14 in full: confirmed "cached markup serving a stale nonce" is a named Common Cause with its own dedicated Diagnosis, Recovery, and Prevention content, including an explicit instruction to exclude nonce-bearing markup from caching. The taxonomy's characterization is accurate. | `WP-ERROR-027`, full text, Section 5 above. | None. |
| — | Conforming | Accurate representation of WP-ERROR-030 | Independently re-read `WP-ERROR-030` Section 10/11 in full: confirmed stale-cached CORS headers computed for a different origin is a named Common Cause with its own dedicated Diagnosis step and Recovery action. The taxonomy's characterization is accurate. | `WP-ERROR-030`, full text, Section 5 above. | None. |
| — | Conforming | Accurate representation of WP-ERROR-009/019/020/013 | Independently re-read all four: `WP-ERROR-009` confirmed to name `wp_options` autoloaded-data bloat as its own cause; `WP-ERROR-019`/`020` confirmed to name disk-backed caching as a contributor to their own respective permission/capacity conditions; `WP-ERROR-013` confirmed to already own drop-in-caused bootstrap fatal errors generally, per its own Section 10 and Section 17 disclaimer. All four taxonomy claims independently confirmed accurate. | Section 5 above. | None. |
| — | Conforming | Applying the `FRAMEWORK-OBSERVATIONS.md` lesson (unnamed-entry conflict check) | Independently re-ran a full-text sweep for cache-related terminology across every knowledge entry and taxonomy in the repository, not only the entries `SF-TAXONOMY-006`'s own text already names. No additional entry — named or unnamed by the taxonomy — was found making a conflicting caching-related claim. This is a genuine, if negative, test of the specific review-scope limitation `SF-TAXONOMY-005`'s own production cycle surfaced; disclosed as a second data point in `FRAMEWORK-OBSERVATIONS.md` (Section 8 below) since this is exactly the check that gap's own recommendation called for. | Section 5 above. | Disclosed, not corrective (no conflict found). |
| IF-1 | Minor | Technical accuracy (Principle: Evidence Over Assertion) | `WP-ERROR-033`'s original Owns text asserted that WordPress's own object-cache API "is designed to tolerate a missing or non-functional external backend," implying a WordPress-core-level guarantee of graceful degradation. Independent technical assessment found this overstated: WordPress core's own built-in object cache has no external backend to begin with, so it cannot "tolerate losing" one; graceful fallback on a backend connection failure is the specific behavior of the third-party `object-cache.php` drop-in in use, not a core-level guarantee — a poorly-written drop-in can instead produce a fatal error or a hang rather than a graceful degradation. | `WP-ERROR-033`'s original Owns text (Section 3, pre-correction). | Reword to cover both possible observable consequences (graceful degradation or a fatal error/hang, depending on the specific drop-in) rather than asserting a single guaranteed outcome; correct the Ownership Model's parallel wording (Section 4) to match. | Resolved (already corrected during drafting, prior to this review being opened — see Section 7) |
| — | Conforming | SF-SPEC-013 §5.1, bullet 2 (planned entries, ownership) | Three entries, each with a one-line ownership statement in Section 3's table. | Section 3. | None. |
| — | Conforming | Ownership Model (Section 4) internal consistency | Independently re-derived: the three entries divide by distinct caching mechanism (object-cache connectivity, page-cache activation, opcode-cache invalidation), each independently reachable with no shared precondition, correctly distinguished from `SF-TAXONOMY-004`'s own sequential-pair model rather than forcing an artificial similarity. No logical gap found. | Section 4. | None. |
| — | Conforming | SF-SPEC-013 §5.1, bullet 3 (rejected/deferred candidates, reasoning) | Five candidates addressed (a generic stale-cache entry, a separate Transients API entry, a CDN-specific entry, a cache-stampede entry, a Heartbeat API entry), each with specific reasoning distinguishing rejection from deferral. | Section 5. | None. |
| — | Conforming | `SF-SPEC-001` Section 7 conformance | `Performance` independently confirmed present in the approved category-value list. | `SF-SPEC-001` Section 7. | None. |
| — | Conforming | ID availability | `WP-ERROR-033` through `035` independently confirmed to not currently exist in the repository. | `ls` sweep, Section 5 above. | None. |
| — | Conforming | Structural sweep | Zero bare `must` outside `must-use`/legitimate use; zero drafting-language matches (the sole `XXX` match is the standard `SF-TAXONOMY-XXX` placeholder-ID convention used catalog-wide). | Section 5 above. | None. |

No Major or Critical findings.

---

# 7. Note on IF-1's Resolution Timing

IF-1 was identified during this review's own independent technical assessment, but the correction had already been applied to `SF-TAXONOMY-006` during its own drafting pass, before this review formally began, once the same overclaim was noticed while re-checking the WP-ERROR-033 description prior to publication. This review independently re-confirms the correction is accurate and complete (Section 6 above) rather than treating it as newly discovered; it is recorded as a finding here so the correction has a review record documenting it, consistent with this catalog's evidence-governance expectations, rather than being an undocumented change — the same disclosure pattern `SF-REVIEW-080`/`089` each established.

---

# 8. Second Data Point on the FRAMEWORK-OBSERVATIONS.md Review-Scope Limitation

`FRAMEWORK-OBSERVATIONS.md`'s newest entry (logged during `WP-ERROR-032`'s own production) disclosed that a taxonomy's own independent review can verify every claim the artifact makes while still missing a conflict with an entry it never named — and recommended, as a possible future process change, a mandatory sweep for every category a taxonomy's own boundary touches even implicitly. This review performed exactly that sweep for `SF-TAXONOMY-006` (Section 6 above) and found no additional conflict — a clean result, not because the check was skipped, but because `SF-TAXONOMY-006`'s own drafting process already incorporated the broader search this recommendation calls for, before this independent review even began. This is disclosed as a second data point: one prior occurrence where the broader check was *not* performed and found a real conflict (`SF-TAXONOMY-005`), and one occurrence where it *was* performed proactively and found none (`SF-TAXONOMY-006`) — evidence that incorporating the broader sweep into the taxonomy's own drafting process, rather than deferring it to a later review or to mid-production discovery, is a viable and effective practice going forward.

---

# 9. Outcome

**Approved.**

**Basis:** `SF-TAXONOMY-006` satisfies every element of **SF-SPEC-013** Section 5.1. Its central claim — that four existing entries already own the specific, symptom-level manifestation of caching-related staleness — was independently verified as accurate against each of those four entries' own actual text, not merely asserted. A broader sweep for any additional, unnamed conflicting entry, applying the specific lesson `SF-TAXONOMY-005`'s own production cycle taught, found none. The one technical-accuracy issue found (IF-1, an overstated core-level fallback-behavior guarantee) was already corrected in the artifact as reviewed.

---

# 10. Gate Decision

Per **SF-SPEC-013** Section 5.1, entry authoring for the Performance category (`WP-ERROR-033` through `035`) may now begin — this taxonomy exists, declares the category's boundary with independently-verified accuracy against every entry it claims already occupies adjacent territory, enumerates every planned entry, documents rejected/deferred candidates, and has been independently reviewed per this project's established practice, including a proactive application of this project's own most recently learned review-scope lesson.

---

# 11. Remaining Risks

- This review was conducted by the same class of agent (Claude Code) as the authoring pass.
- This category's own scope is unusually narrow and unusually dependent on four *other* categories' own text remaining stable; a future revision to `WP-ERROR-021`/`025`/`027`/`030` that altered their own caching-related content could, in principle, open or close territory this taxonomy currently treats as settled — a dependency no prior taxonomy in this catalog has carried to this degree.
- The `WP-ERROR-034`/"Page Cache Not Active" boundary against a functioning-but-stale cache (`WP-ERROR-021`/`025`/`027`/`030`'s own territory) is a design choice not yet tested against a real, ambiguous field case where a cache is *partially* active (populated for some request types but not others) — if drafting `WP-ERROR-034` reveals this boundary is harder to keep cleanly separated in practice than this taxonomy assumes, that should surface as a finding in that entry's own author review rather than being silently absorbed.
- Section 8's own second-data-point disclosure is itself only two data points; whether incorporating a broader sweep into every future taxonomy's own drafting process should become a formal requirement (as opposed to the informal practice this and `SF-TAXONOMY-005`'s correction together demonstrate) remains an open question for `FRAMEWORK-OBSERVATIONS.md` to continue tracking, not resolved by this review.

---

# 12. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-14 | Initial independent review of SF-TAXONOMY-006. Independently re-verified all four claimed pre-existing overlaps (WP-ERROR-021/025/027/030) against those entries' own actual text, and independently re-ran a full-text cache-terminology sweep across the entire repository to test for any additional, unnamed conflict — none found, disclosed as a second FRAMEWORK-OBSERVATIONS.md data point. One Minor finding (IF-1: an overstated WordPress-core-level object-cache fallback guarantee, corrected during drafting and independently re-confirmed here) recorded for documentation completeness. Approved. Entry authoring for WP-ERROR-033 through 035 may begin. | Approved |
