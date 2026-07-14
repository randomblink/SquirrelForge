# Knowledge Production Plan

## Purpose

This document is the working backlog and roadmap for `WP-ERROR` knowledge production under **Framework Baseline v2** (`SF-BASELINE-001`).

It is intentionally informal, mirroring `FRAMEWORK-OBSERVATIONS.md`'s own disclaimer:

It is not a specification. It is not governed by the engineering specification library. It is not reviewed under **SF-SPEC-005**. It is not versioned under **SF-SPEC-008**. It answers planning questions (which categories exist, which are planned, which is active, what depends on what) so that production has an explicit queue without turning the queue itself into governance.

It should be updated freely as production proceeds. It does not require engineering review to edit.

---

## 1. Current Catalog State

| Category | Entries | Baseline Certified | Taxonomy Document | Review |
|---|---|---|---|---|
| Database | 9 (`WP-ERROR-002`–`009`, `018`) | Yes | None (informal, disclosed gap) | `SF-REVIEW-033` |
| Filesystem | 3 (`WP-ERROR-016`, `019`, `020`) | Yes | `SF-TAXONOMY-001` | `SF-REVIEW-040` |
| REST API | 3 (`WP-ERROR-021`–`023`) | Yes | `SF-TAXONOMY-002` | `SF-REVIEW-053` |
| PHP Runtime | 2 (`WP-ERROR-014`, `015`) | Yes | None (informal, disclosed gap) | `SF-REVIEW-057` |
| Bootstrap | 1 (`WP-ERROR-013`) | No — single-entry, degenerate for category review | None | — |
| Plugin | 1 (`WP-ERROR-017`) | No — single-entry, degenerate for category review | None | — |

19 entries, 6 categories, 4 Baseline Certified, across a 14-specification framework at `SF-BASELINE-001`.

---

## 2. SF-SPEC-001 Approved Category Values (Current)

Per **SF-SPEC-001** Section 7, the approved list is: `Bootstrap`, `Configuration`, `PHP Runtime`, `Database`, `Filesystem`, `Plugin`, `Theme`, `REST API`, `Authentication`, `Security`, `Performance`, `Deployment`, `CLI`. That section's closing sentence — "No new category may be introduced without updating this specification" — makes this an exhaustive list, not merely illustrative examples, despite the "Examples:" heading.

Seven approved categories have **zero entries yet** and require no specification change to begin: `Configuration`, `Theme`, `Authentication`, `Security`, `Performance`, `Deployment`, `CLI`.

---

## 3. Candidate Roadmap

**Resolved:** all twelve candidates are now approved and startable with no further specification change. The project owner chose batch approval; `SF-SPEC-001` Version 1.2 (`SF-REVIEW-067`/`068`) added six category values in one revision rather than one at a time. The independent review renamed two of them for formatting consistency with the pre-existing list: `HTTP / Networking` → **`Networking`**, `Cron / Scheduled Tasks` → **`Cron`**. Use the exact approved strings (right-hand column) in any entry's own `**Category:**` field; the left-hand names remain this document's informal roadmap labels.

| Candidate (roadmap label) | Approved `SF-SPEC-001` category value | Status |
|---|---|---|
| Authentication & Authorization | `Authentication` | **Ready** — approved, zero entries |
| Plugin Lifecycle | `Plugin` | **Ready** — approved, already has `WP-ERROR-017`; a new entry extends an existing, uncertified single-entry category rather than starting fresh |
| Theme Lifecycle | `Theme` | **Ready** — approved, zero entries |
| Caching / Performance | `Performance` | **Ready** — approved, zero entries |
| WP-CLI | `CLI` | **Ready** — approved, zero entries |
| Filesystem & Updates (remaining gaps) | `Filesystem` | **Partially ready** — category is already `Baseline Certified`; a new entry here is a *post-certification change* under **SF-SPEC-013** Section 5.6, not a fresh start, and must go through that section's own four-step process (taxonomy revision, standard authoring sequence, new consistency review, new baseline certification) |
| HTTP / Networking | `Networking` | **Ready** — approved `SF-SPEC-001` Version 1.2, zero entries |
| Media | `Media` | **Ready** — approved `SF-SPEC-001` Version 1.2, zero entries |
| Cron / Scheduled Tasks | `Cron` | **Ready** — approved `SF-SPEC-001` Version 1.2, zero entries |
| Email | `Email` | **Ready** — approved `SF-SPEC-001` Version 1.2, zero entries |
| Multisite | `Multisite` | **Ready** — approved `SF-SPEC-001` Version 1.2, zero entries |
| Site Health | `Site Health` | **Ready** — approved `SF-SPEC-001` Version 1.2, zero entries; still worth deciding, before taxonomy work starts, whether this is a genuine failure domain of its own or a diagnostic lens that cuts across every other category |

---

## 4. Suggested Prioritization

Ordered by estimated operational value — how often the condition is likely to be the actual cause of a real, reported WordPress problem — not alphabetically or by ease of authoring. This is a starting proposal, not a committed order; it should be adjusted against real production/support data as it accumulates (Section 7).

All twelve are now approved (Section 3); this ordering is unaffected by that resolution.

1. **Authentication & Authorization** (`Authentication`) — ready now; login/access failures are among the most common real-world WordPress support issues, and the category already borders `WP-ERROR-022` (REST API Access Denied), so the boundary work is partly done.
2. **Plugin Lifecycle** (`Plugin`) — ready now; plugin activation/conflict failures are extremely common and the category already has one entry (`WP-ERROR-017`) establishing precedent.
3. **Networking** (`Networking`) — highest-value remaining category; outbound API calls, SSL, DNS, and reverse-proxy failures are a frequent, currently uncovered failure surface.
4. **Caching / Performance** (`Performance`) — ready now; borders `WP-ERROR-009` (Database Query Timeout), so needs an explicit boundary statement in its own taxonomy.
5. **Media** (`Media`) — common failure surface (uploads, image processing); borders `WP-ERROR-014` (PHP extension) and Filesystem.
6. **Theme Lifecycle** (`Theme`) — ready now; lower volume than Plugin Lifecycle but a clear, well-bounded domain.
7. **Email** (`Email`) — common but frequently a downstream symptom of Networking or Configuration failures rather than its own root cause; worth sequencing after Networking so the boundary is easier to draw.
8. **Cron** (`Cron`) — real but often silent-failure territory, lower reported-incident volume.
9. **Filesystem & Updates (remaining gaps)** (`Filesystem`) — valuable but procedurally heavier (post-certification change to an already-certified category); sequence once the team has practiced the post-certification process on paper via **SF-SPEC-013** Section 5.6.
10. **WP-CLI** (`CLI`) — ready now; developer-facing rather than production-incident-driven, lower priority.
11. **Site Health** (`Site Health`) — resolve the "domain vs. diagnostic lens" design question (Section 3) before taxonomy work; likely lower urgency.
12. **Multisite** (`Multisite`) — specialized, smaller installed base; lowest priority absent a specific signal it's needed sooner.

---

## 5. Category Completion Criteria (Template)

Unchanged from the process **SF-SPEC-013** already governs; restated here only as a planning checklist, not a new requirement:

1. Category value approved in **SF-SPEC-001** Section 7, if not already present.
2. `SF-TAXONOMY-XXX` authored: category boundary, planned entries with one-line ownership each, rejected candidates with reasoning (**SF-SPEC-013** Section 5.1).
3. Each planned entry authored and independently reviewed to Production Ready (Section 5.2, 5.3).
4. Category-level consistency review (Section 5.4's sibling criteria: no overlap, cross-reference symmetry, consistent recovery guidance).
5. Category-level baseline certification review (Section 5.4, Section 8 checklist) — `Baseline Certified` designation.
6. `scripts/validate-repo.sh` clean before and after.

---

## 6. Dependencies and Boundary Risks

Flagged in advance so taxonomy authors design the boundary deliberately rather than discovering the overlap during a later consistency review:

* **HTTP / Networking vs. REST API** — REST API already owns "request accepted, callback executing" territory (`WP-ERROR-021`–`023`); HTTP/Networking should own the outbound/lower-layer transport conditions (DNS, TLS, timeouts, reverse proxies) rather than re-litigating inbound REST request handling.
* **Authentication & Authorization vs. REST API** — `WP-ERROR-022` (REST API Access Denied) already covers authentication/authorization failure *within* a REST request. A general Authentication category needs an explicit boundary statement distinguishing site-wide login/session/capability failures from that REST-specific case, the same way `WP-ERROR-014`/`015` had to disambiguate PHP Runtime from Bootstrap.
* **Caching / Performance vs. Database** — `WP-ERROR-009` (Database Query Timeout) already covers one performance-adjacent condition. Caching/Performance should own application- and object-cache-layer conditions, not re-absorb database-specific timeouts.
* **Media vs. Filesystem vs. PHP Runtime** — media failures often manifest as filesystem permission issues (`WP-ERROR-019`/`020`) or missing PHP extensions (`WP-ERROR-014`, e.g. `gd`/`imagick`). Media's own taxonomy should explicitly defer to those existing entries for that subset and scope itself to media-specific conditions (format/codec handling, thumbnail generation logic) instead.
* **Site Health** — before authoring, decide whether it is a distinct failure domain (Site Health's own reporting logic malfunctioning) or a diagnostic lens referencing every other category's own conditions. The latter would not need its own `WP-ERROR` entries at all.

---

## 7. Evidence Log

Empty scaffold. Per the project owner's own direction, this is the evidence base a future Framework Baseline v3 would need to justify reopening the specification layer — not a place to record routine entry authoring, but a place to note when production repeatedly exposes the *same* governance deficiency (as opposed to a one-off inconvenience).

| Date | Category / Entry | Observation | Governance Deficiency Suspected? |
|---|---|---|---|
| — | — | — | — |

---

## 8. Active Category

None yet. All twelve candidates are approved and ready (Section 3); awaiting a decision on which to start first. Section 4's proposed order (`Authentication` first) is a starting recommendation, not a commitment.
