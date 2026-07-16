# SF-REVIEW-046 — WP-ERROR-021 Author Review

# 1. Review Information

**Review ID:** SF-REVIEW-046

**Review Date:** 2026-07-14

**Reviewer:** Class A — Author Review, per **SF-SPEC-012** Section 6.1. Performed by the same authoring process that drafted WP-ERROR-021, within the same work-order execution.

**Status:** Complete

Per **SF-SPEC-012** Section 6.1, this Class A review may identify and correct defects but shall not, by itself, authorize a **Production Ready** designation. For purposes of this review, "Approved" means the artifact is ready to proceed to independent review, not that any lifecycle promotion is authorized.

---

# 2. Artifact Reviewed

`WP-ERROR-021` — WordPress REST API Route Not Found, Version 1.0, at `docs/knowledge/wp-errors/WP-ERROR-021-REST-API-ROUTE-NOT-FOUND.md`. Status at time of this review: Draft.

---

# 3. Governing Specifications

- **SF-SPEC-001 — Error Knowledge Specification**
- **SF-SPEC-012 — Engineering Review Independence Specification** (for this review's own classification and authority)
- **SF-SPEC-013 — Knowledge Category Lifecycle Specification** (governing the category lifecycle this entry is the first product of)
- **SF-TEMPLATE-004 — WP-ERROR Knowledge Template**
- **SF-GLOSSARY-001 — Engineering Terminology**
- `SF-TAXONOMY-002` — REST API Error Taxonomy, Version 1.2, whose Section 3 declaration for this entry ("route resolution — the request fails before a callback is selected") is the governing failure boundary

---

# 4. Review Scope

This review evaluates whether WP-ERROR-021, as drafted, satisfies `SF-TAXONOMY-002`'s declared boundary for this entry without narrowing or widening it, correctly implements the internal distinction the taxonomy's own Category Boundary corrections require (genuine route removal versus post-match interception, and the general-bootstrap/filesystem/security exclusions `SF-REVIEW-045` added), and satisfies SF-SPEC-001's authoring standards, and whether it is ready to proceed to independent review. It does not authorize Production Ready. This review is also the first conducted under `SF-SPEC-013`'s now-normative Section 5.1/5.2/5.3 requirements as applied to an individual entry's own authoring — the taxonomy document already existed, with its boundary corrections and open questions already resolved, before this entry was drafted.

---

# 5. Review Criteria

- SF-SPEC-001 Section 5 (Required Document Structure), Section 6 (Metadata Standard), Section 7 (Category Standard — confirming `REST API` is an approved category value), Section 8 (Severity Standard)
- SF-SPEC-001 Section 4.3 (Single Responsibility), Section 9 (Writing Standard), Section 10 (Scope Standard)
- `SF-TAXONOMY-002` Section 2 (Category Boundary, including all eight adjacent-category exclusions as corrected by `SF-REVIEW-045`), Section 3 (this entry's declared boundary), Section 4 (the argument-validation placement decision, checked for whether it bears on this entry — it does not, since both `WP-ERROR-022` and `023` presume a route has already been found)

---

# 6. Precondition Verification

Before authoring, `SF-TAXONOMY-002` was re-read at its current state (Version 1.2, incorporating both `SF-REVIEW-045`'s correction and the subsequent argument-validation placement decision) to confirm this entry is drafted against its fully current boundary rather than an earlier draft. `WP-ERROR-022` and `WP-ERROR-023` do not exist, or have ever existed, in this repository (`git log --all --diff-filter=A --name-only -- "*WP-ERROR-022*"` and `"*WP-ERROR-023*"`, run during this review, both return no result); they are cited as conceptual references only, explicitly disclosed as planned per `SF-TAXONOMY-002` Section 3, with no link.

---

# 7. Evidence Examined

- Full contents of `WP-ERROR-021-REST-API-ROUTE-NOT-FOUND.md`, read in full.
- `grep -c '^# [0-9]\+\.'` (17, matching SF-TEMPLATE-004).
- `grep -Ein 'TODO|TBD|placeholder|future work|should consider|to be determined|intended to be added'` (zero matches).
- `grep -n '\bmust\b' | grep -v "must-use"` (zero matches).
- `git diff --check` (clean).
- `git log --all --diff-filter=A --name-only -- "*WP-ERROR-021*"` (empty, confirming no version of this document existed prior to this work order); the same check for `WP-ERROR-022`/`023` (both empty, confirming correctly unlinked conceptual citations).
- `grep -n "REST API" docs/standards/SF-SPEC-001-ERROR-KNOWLEDGE.md` confirming `REST API` is a valid, approved category value.
- `grep -rln "WP-ERROR-021" docs/` confirming the only pre-existing references to this ID were in `SF-TAXONOMY-002` and its own independent review, both correctly anticipating this entry's eventual creation.
- Independent verification of technical claims before inclusion, performed via current WordPress documentation: the `rest_no_route` error code and its exact message text; `register_rest_route()` and the `rest_api_init` action as the real registration mechanism; the `rest_endpoints` filter's real, documented capability to remove routes from the match table (distinct from `rest_authentication_errors`/`rest_pre_dispatch`, which intercept an already-matched route rather than removing it); the `rest_route` public query variable's independence from permalink structure; the REST API's own real discovery mechanism (`Link` header and `<link rel="https://api.w.org/">` tag, with the query-string variant for non-pretty permalinks); and that a dedicated route-listing WP-CLI command is not a core capability, requiring the optional `wp-cli/restful` package instead.

---

# 8. Findings

| Finding ID | Severity | Observation | Correction |
|---|---|---|---|
| — | Conforming | Failure boundary matches `SF-TAXONOMY-002` Section 3 exactly: owns only route-resolution failure (no callback ever selected), covering both never-registered and removed-from-table manifestations as one cohesive condition; excludes `WP-ERROR-022`'s post-match interception, `WP-ERROR-023`'s callback-execution failures, and all eight category exclusions `SF-TAXONOMY-002` Section 2 (as corrected by `SF-REVIEW-045`) names. | None. |
| — | Conforming | The specific internal distinction the taxonomy's own correction required — genuine route removal (`rest_endpoints`) versus post-match interception (`rest_authentication_errors`/`rest_pre_dispatch`) — is explicitly drawn in Section 4 (Primary Failure Mode) and Section 6 (Distinction), not left to be inferred from the taxonomy document alone. | None. |
| — | Conforming | The argument-validation placement decision `SF-TAXONOMY-002` Section 4 records is correctly identified as not bearing on this entry (Section 17, Notes), since both `WP-ERROR-022` and `023` presume a route has already been found, and this entry's own boundary ends before that point. | None. |
| — | Conforming | Severity classification (`Critical`, with an honestly acknowledged range from full-outage — headless sites, the Block Editor — to a single narrow endpoint) mirrors the precedent established for `WP-ERROR-004`, `005`, `006`, `019`, and `020`. | None. |
| — | Conforming | Structure: all 17 SF-TEMPLATE-004 sections present, in order, sequentially numbered, none empty. | None. |
| — | Conforming | Related Errors: both conceptual citations (`WP-ERROR-022`, `023`) correctly disclosed as planned-but-nonexistent with no link, ordered numerically. | None. |
| — | Conforming | Technical grounding (the `rest_no_route` code/message, `rest_endpoints`'s real capability, the `rest_route` query variable, the REST API's own discovery mechanism, and the WP-CLI core/package distinction) independently verified against current documentation rather than asserted from unverified recall. | None. |

No Minor, Major, or Critical findings.

---

# 9. Recommendations

None.

---

# 10. Outcome

**Approved (for purposes of proceeding to independent review only).**

**Basis:** No defect was found. The entry's failure boundary, its internal genuine-removal-versus-interception distinction, its correct disclaiming of the argument-validation placement decision's irrelevance to it, technical grounding, structure, and cross-references all conform exactly to `SF-TAXONOMY-002`'s own (fully corrected, Version 1.2) declaration for this entry. This outcome does not authorize Production Ready; per SF-SPEC-012 Section 6.1, a Class A review cannot do so regardless of its outcome.

WP-ERROR-021 remains `Draft`.

---

# 11. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-14 | Initial author review of WP-ERROR-021. No findings; zero corrections required. Confirmed WP-ERROR-022/023 do not exist; confirmed the entry's boundary matches SF-TAXONOMY-002 v1.2 exactly, including the genuine-removal-versus-interception distinction and the correct disclaiming of the argument-validation placement decision. | Approved (Class A; does not authorize Production Ready) |
