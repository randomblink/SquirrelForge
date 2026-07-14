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
| Authentication | 4 (`WP-ERROR-024`–`027`) | Yes | `SF-TAXONOMY-003` | `SF-REVIEW-079` |
| Networking | 3 (`WP-ERROR-028`–`030`) | Yes | `SF-TAXONOMY-004` | `SF-REVIEW-088` |
| Bootstrap | 1 (`WP-ERROR-013`) | No — single-entry, degenerate for category review | None | — |
| Plugin | 3 (`WP-ERROR-017`, `031`, `032`) | Yes | `SF-TAXONOMY-005` | `SF-REVIEW-095` |

28 entries, 8 categories, 7 Baseline Certified, across the 14-specification framework at `SF-BASELINE-001`.

---

## 2. SF-SPEC-001 Approved Category Values (Current)

Per **SF-SPEC-001** Section 7, the approved list is: `Bootstrap`, `Configuration`, `PHP Runtime`, `Database`, `Filesystem`, `Plugin`, `Theme`, `REST API`, `Authentication`, `Security`, `Performance`, `Deployment`, `CLI`. That section's closing sentence — "No new category may be introduced without updating this specification" — makes this an exhaustive list, not merely illustrative examples, despite the "Examples:" heading.

Seven approved categories have **zero entries yet** and require no specification change to begin: `Configuration`, `Theme`, `Authentication`, `Security`, `Performance`, `Deployment`, `CLI`.

---

## 3. Candidate Roadmap

**Resolved:** all twelve candidates are now approved and startable with no further specification change. The project owner chose batch approval; `SF-SPEC-001` Version 1.2 (`SF-REVIEW-067`/`068`) added six category values in one revision rather than one at a time. The independent review renamed two of them for formatting consistency with the pre-existing list: `HTTP / Networking` → **`Networking`**, `Cron / Scheduled Tasks` → **`Cron`**. Use the exact approved strings (right-hand column) in any entry's own `**Category:**` field; the left-hand names remain this document's informal roadmap labels.

| Candidate (roadmap label) | Approved `SF-SPEC-001` category value | Status |
|---|---|---|
| Authentication & Authorization | `Authentication` | **Done** — Baseline Certified (`SF-REVIEW-079`) |
| Plugin Lifecycle | `Plugin` | **Done** — Baseline Certified (`SF-REVIEW-095`) |
| Theme Lifecycle | `Theme` | **Ready** — approved, zero entries |
| Caching / Performance | `Performance` | **Ready** — approved, zero entries |
| WP-CLI | `CLI` | **Ready** — approved, zero entries |
| Filesystem & Updates (remaining gaps) | `Filesystem` | **Partially ready** — category is already `Baseline Certified`; a new entry here is a *post-certification change* under **SF-SPEC-013** Section 5.6, not a fresh start, and must go through that section's own four-step process (taxonomy revision, standard authoring sequence, new consistency review, new baseline certification) |
| HTTP / Networking | `Networking` | **Done** — Baseline Certified (`SF-REVIEW-088`) |
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

No category is currently active as of this update. Per the roadmap (Section 3), the next unstarted "ready now" candidate is now the first entry in the list without an existing category already claimed by this project's own production so far.

Plugin Lifecycle is complete and Baseline Certified (`SF-REVIEW-095`) — the seventh category to reach that designation, and the fourth (after REST API, Authentication, and Networking) built from a dedicated taxonomy document — and the first whose taxonomy was declared *after* an existing, unbaselined entry (`WP-ERROR-017`) already occupied part of the category, and required a mid-production correction (`SF-TAXONOMY-005` v1.1→v1.2) before its final entry was authored. Category Work Order: `SF-TAXONOMY-005` (Plugin Lifecycle Error Taxonomy), Version 1.3. Entries: `WP-ERROR-017` (Must-Use Plugin Fatal Error), `WP-ERROR-031` (Plugin Activation Failure), `WP-ERROR-032` (Plugin Update Failure), all Production Ready.

- `WP-ERROR-031` (`SF-REVIEW-090`/`091`) — drafted directly from `SF-TAXONOMY-005`'s own declared scope, per explicit project-owner direction, as a deliberate test of whether the taxonomy is complete enough to support entry authoring without a fresh boundary discussion. Keeps three causes distinct: WordPress's own native pre-activation requirement gate (`Requires PHP`/`Requires at least`/`Requires Plugins`, refused before any plugin code runs), an activation-time fatal error during the plugin's own file include (WordPress's own built-in protection, distinguished explicitly from a generic PHP-runtime fatal error), and the plugin's own `register_activation_hook()` callback failing or self-halting. Diagnosis starts from confirming activation actually failed and capturing WordPress's own exact message, before narrowing to which of the three mechanisms is responsible. Hands off to `WP-ERROR-014`/`015` for extension/version root causes rather than duplicating their own diagnostic content. Independent review caught one real finding, outside this entry's own text: `WP-ERROR-017`'s own "ordinary plugin activation/deactivation" exclusion bullet described this entry's own territory without citing it — corrected. `SF-TAXONOMY-005` itself required no revision to support this entry, satisfying the project owner's own stated completeness test.
- `WP-ERROR-032` (`SF-REVIEW-092`/`093`) — required a pre-authoring taxonomy correction (`SF-TAXONOMY-005` v1.1→v1.2): research surfaced two real, previously unaddressed overlaps `SF-REVIEW-089` had not caught — `WP-ERROR-019`/`020` (Filesystem) already explicitly claim the `wp-content/upgrade` staging directory's own permission/capacity dimension of an update failure, and `WP-ERROR-028`/`029` (Networking) already explicitly claim the update package's own download-connection/TLS dimension. Narrowed the entry to the update *mechanism's* own process (pre-update compatibility gate, package integrity/extraction, non-permission/capacity file-swap interruption, automatic-update rollback) as the diagnostic entry point, handing off to all four sibling entries once root-caused. Flags a genuinely more severe risk profile than `WP-ERROR-031`: an interrupted update on an *already-active* plugin can leave the site attempting to load mismatched files on the very next request, a site-wide outage rather than a contained inactive-plugin state. Independent review caught one real finding: `WP-ERROR-013`'s own Common Causes list didn't name an interrupted-update-caused file inconsistency as one of its own downstream-symptom causes — added, cross-referencing this entry. Disclosed as a new `FRAMEWORK-OBSERVATIONS.md` entry: a taxonomy's own independent review verifies every claim it makes about entries it names, but can still miss a claim the artifact never thought to name in the first place.
- **Consistency review** (`SF-REVIEW-094`) treated all three as a system: confirmed the three-stage ownership model holds exactly, and found one Minor finding — `WP-ERROR-017` and `WP-ERROR-031`'s own Related Errors intro sentences used non-standard wording rather than the majority convention 25 of this repository's 28 entries use — corrected.
- **Baseline certification** (`SF-REVIEW-095`) required no correction of its own, since the consistency review had already caught what would otherwise have surfaced here.

Networking is complete and Baseline Certified (`SF-REVIEW-088`) — the sixth category to reach that designation, and the third (after REST API and Authentication) built from a dedicated taxonomy from the outset. Category Work Order: `SF-TAXONOMY-004` (Networking Error Taxonomy), Version 1.4. Entries: `WP-ERROR-028` (Outbound HTTP Request Failure), `WP-ERROR-029` (Outbound TLS Negotiation Failure), `WP-ERROR-030` (CORS Policy Failure), all Production Ready.

- `WP-ERROR-028` (`SF-REVIEW-081`/`082`) — kept deliberately transport-agnostic (`curl`/streams named only as diagnostic detail, never the entry's own definition), separated connection-establishment from protocol (TLS reserved to `WP-ERROR-029`), and explicitly disclosed two conditions this catalog does not yet own under any entry (read/response timeout; an HTTP-level error status from an otherwise-successful outbound exchange) rather than silently absorbing either. Independent review caught a real boundary-precision gap: "connection reset" as originally drafted didn't specify whether it meant a reset during establishment (in scope) or after (out of scope, same as the disclosed read-timeout gap) — qualified to the former.
- `WP-ERROR-029` (`SF-REVIEW-083`/`084`) — `SF-TAXONOMY-004` was widened (v1.0 → v1.2) *before* this entry was authored, per explicit direction: the original scope ("Certificate Verification Failure") was narrower than the category needs, since protocol/cipher negotiation failures aren't certificate problems at all. Retitled "Outbound TLS Negotiation Failure," now covering eight explicitly separated causes rather than blending them. Independent review found a real cross-category overlap: `WP-ERROR-014`'s own diagnosis text already names "a curl build without a specific SSL backend or protocol" as its own territory, which sounds identical to this entry's protocol/cipher causes. Resolved by scope, not mechanism — `WP-ERROR-014` owns a categorical, environment-wide capability gap; this entry owns the observable, request-specific negotiation failure, escalating to `WP-ERROR-014` only once root-caused that deep.
- `WP-ERROR-030` (`SF-REVIEW-085`/`086`) — matched `SF-TAXONOMY-004`'s already-declared Version 1.3 scope exactly, requiring no widening. Reverses WordPress's own role from client (`028`/`029`) to server: CORS is enforced by the browser, WordPress only emits or omits the relevant headers. Carries the two-directional relationship with `WP-ERROR-022` explicitly (a `200 OK` REST response can still be CORS-blocked; a correct CORS policy doesn't override an auth/authz denial). Independent review caught a real internal-precision gap: the entry's own Section 3 claimed WordPress "never becomes aware" a request was cross-origin, which overstated the boundary against the entry's own Section 8 documentation of the origin-aware header-emission mechanism (`get_http_origin()`/`is_allowed_http_origin()`) — narrowed to "never rejects or blocks on the basis of origin, though it does read the Origin header to decide what to emit." This entry's own creation also required updating `WP-ERROR-021`/`022`'s pre-existing CORS-exclusion bullets (previously citing only `SF-TAXONOMY-002`'s forward-reference promise) to cite this entry by a real link — the same class of stale-hedge cleanup `SF-REVIEW-075` performed during the Authentication phase.
- **Consistency review** (`SF-REVIEW-087`) treated all three as a system: confirmed the two-axis ownership model (`028`/`029` sequential, `030` independent) holds exactly, and found two Minor cross-document staleness artifacts of sequential authoring — `WP-ERROR-028`'s own Section 6 still cited `WP-ERROR-029` by its pre-widening title, and Section 16 still carried a stale "(currently Draft)" parenthetical predating `WP-ERROR-029`'s own promotion — both corrected. Disclosed as a second `FRAMEWORK-OBSERVATIONS.md` data point for the `SF-SPEC-013` Section 5.7 staleness family: a sibling entry's own prose, not a placeholder citation, drifting after a retitle/promotion — outside `scripts/validate-repo.sh` Check A's current scope.
- **Baseline certification** (`SF-REVIEW-088`) required no correction of its own, since the consistency review had already caught what would otherwise have surfaced here.

Authentication is complete and Baseline Certified (`SF-REVIEW-079`) — the fifth category to reach that designation, and the second (after REST API) built from a dedicated taxonomy from the outset. Category Work Order: `SF-TAXONOMY-003` (Authentication Error Taxonomy), Version 1.5. Entries: `WP-ERROR-024` (Login Authentication Failure), `WP-ERROR-025` (Authentication Cookie Invalid or Expired), `WP-ERROR-026` (Capability or Role Authorization Denied), `WP-ERROR-027` (Nonce Verification Failure, Non-REST), all Production Ready.

- `WP-ERROR-024` (`SF-REVIEW-070`/`071`) — the first entry in the category and the first authored entirely after `SF-BASELINE-001`. Its independent review caught a real internal-consistency gap in the entry's own lockout-plugin exclusion; its author review caught and fixed a bug in `scripts/validate-repo.sh` itself (Check B was flagging a false-positive "Planned vs. Draft" mismatch; it now only flags once an entry reaches Production Ready, matching established taxonomy-update convention).
- `WP-ERROR-025` (`SF-REVIEW-072`/`073`) — its independent review caught a cross-document attribution error: the entry claimed a boundary case (browser with cookies entirely disabled) belonged to `WP-ERROR-024`, a claim `WP-ERROR-024`'s own text never made and that, on independent technical review, was likely misattributed (credential verification succeeds in that scenario; only cookie persistence fails). Reattributed to `WP-ERROR-025`'s own domain.
- `WP-ERROR-026` (`SF-REVIEW-074`/`075`) — capability-centered per explicit direction, not role-centered; six causes kept deliberately separate; "start broad" diagnostic ordering; explicit prohibition on "make them Administrator" as a default fix. Its independent review found something outside the entry's own file: `WP-ERROR-022` and `SF-TAXONOMY-002` (both already Production Ready/Frozen, authored before this category existed) still carried a generic "Authentication category, once a taxonomy exists for it" hedge — stale now, but invisible to `scripts/validate-repo.sh`, which only matches ID-specific conceptual-reference citations, not generic category-level hedges. Corrected both.
- `WP-ERROR-027` (`SF-REVIEW-076`/`077`) — leads with "a nonce is not authentication, authorization, or replay prevention" per explicit direction; nine causes kept separate; diagnosis pairs generation against verification (action, field, user context, timing, transport) rather than inspecting either side alone; explicit prohibition on disabling nonce verification. Independent review found no new defect — an honest all-Conforming outcome, the category's fourth review and the first with nothing further to correct.
- **Consistency review** (`SF-REVIEW-078`) treated all four as a system: found `WP-ERROR-025`'s Diagnosis lacked the REST-context ruling-out step its siblings already had, and that the specifically-flagged `WP-ERROR-024`-cookies-disabled and `WP-ERROR-025`/`WP-ERROR-027` nonce-before-session-change overlaps were correctly implemented but not stated as explicit rules — added one to `SF-TAXONOMY-003` Section 4. Also tested whether the `WP-ERROR-022`/`SF-TAXONOMY-002` stale-hedge defect (`SF-REVIEW-075`) recurred anywhere else in the category: it did not — the five other similar-looking matches all correctly reference the still-taxonomy-less Security category. Logged as a negative result in `FRAMEWORK-OBSERVATIONS.md`, not yet enough evidence to extend the validator.
- **Baseline certification** (`SF-REVIEW-079`) required no correction of its own, since the consistency review had already caught what would otherwise have surfaced here.

