# SF-TAXONOMY-011 — Multisite Error Taxonomy

## Document Information

**Document ID:** SF-TAXONOMY-011

**Title:** Multisite Error Taxonomy

**Classification:** Engineering Taxonomy — a lightweight, informally-adopted planning artifact, governed by **SF-SPEC-013 — Knowledge Category Lifecycle Specification** for its role in the category lifecycle, but not itself templated by any `SF-TEMPLATE-XXX` document (none currently exists for this artifact type). It is not governed by **SF-SPEC-005**'s review process as a knowledge artifact and does not itself require an author/independent review pair, though an independent review of it is planned per this project's established practice (`SF-REVIEW-034`, `045`, `069`, `080`, `096`, `105`, `114`, `121`, `128`), not as a normative requirement this document imposes on itself.

**Status:** Frozen — the entry set in Section 3 is fixed until this document is deliberately revised (see Section 6). "Frozen" here is an informal, self-defined term describing this document's own adopted-plan state; it is not a claim of the `Version Frozen` **SF-SPEC-001** Section 18 lifecycle stage, nor of any **SF-SPEC-008** Section 6 Version Status value, nor of the category-level `Baseline Certified` designation **SF-SPEC-013** Section 5.5 defines. This document carries a `Version` and `Revision History` for traceability only; it does not present itself as a "versioned engineering artifact" within **SF-SPEC-008**'s own scope, the same disclaimer `SF-TAXONOMY-001`–`010` make.

**Version:** 1.0

**Owner:** SquirrelForge

---

## 1. Purpose

Declare the `Multisite` category's boundary and complete planned `WP-ERROR` entry set *before* any entry in it is authored, per **SF-SPEC-013** Section 5.1's Category Entry Criteria — resolving `SF-TAXONOMY-009` Section 2's own explicit forward-reference to Multisite as the owner of "WordPress Multisite's own site-resolution mechanism failing for an HTTP request... the future `Multisite` category's own territory, per `SF-SPEC-001` Section 7's separate `Multisite` category value, not yet given its own taxonomy."

This document does not itself contain any `WP-ERROR` knowledge content and is not cited as a `WP-ERROR` entry.

---

## 2. Category Boundary

**Multisite** owns failures in WordPress's own network-of-sites mechanism specific to that mechanism itself — most centrally, the process by which an incoming request is resolved to a specific site within the network. It does not own any condition that already has a fully-claimed owner elsewhere in this catalog merely because that condition happens to manifest, or manifest differently, in a Multisite context.

This category's own available territory is unusually narrow for a reason directly analogous to `SF-TAXONOMY-009`'s own finding for CLI, though arrived at differently: rather than WP-CLI being cited as an *alternate entry point* into already-owned mechanisms, Multisite's own behavior has been woven into the *conditions* of numerous existing entries from the outset, as this catalog was authored. The proactive ownership sweep for this taxonomy found substantial, specific, pre-existing claims across at least eight entries:

**Explicitly not owned by Multisite:**

* **A new site's own per-site database tables failing to be created, or being created incompletely, during site creation** — already fully owned by `WP-ERROR-005` (Database Schema Missing or Incomplete), whose own Common Causes list already names "Multisite schema differences — a new site added to a network whose own per-site tables were never fully created" directly, predating this taxonomy.
* **A "CREATE TABLE" failure during site creation due to insufficient database account privilege** — already fully owned by `WP-ERROR-004` (Database Permission Denied), whose own text already names WordPress Multisite's "Add New Site" action by name as "a frequently-reported concrete manifestation," predating this taxonomy.
* **Corruption of a specific site's own per-site tables, or of the network's own shared global tables** (`wp_blogs`, `wp_site`, `wp_users`, and similar) — already fully owned by `WP-ERROR-006` (Database Table Corruption), whose own text already explicitly distinguishes per-site "blog tables" from network-wide global tables as two independently-subject variants of its own condition, predating this taxonomy.
* **A network-activated plugin's own fatal error** — already fully owned by `WP-ERROR-031` (Plugin Activation Failure) and `WP-ERROR-013` (Bootstrap PHP Fatal Error) as applicable, since `WP-ERROR-017`'s own text already establishes precisely that "a network-activated plugin is a regular plugin in `wp-content/plugins/`, activated network-wide through `wp-admin`, with a normal (network-level) activation lifecycle... [and uses] the ordinary plugin activation mechanism," predating this taxonomy. Network activation reuses the identical mechanism `WP-ERROR-031` already owns; this taxonomy does not claim a network-specific activation entry.
* **A user's capability or site-membership resolution being incorrect for a specific site in the network** (a user with full access on one site unexpectedly denied on another, or `is_super_admin()`/network-level capability resolution) — already fully and extensively owned by `WP-ERROR-026` (Capability or Role Authorization Denied), whose own text already names Multisite's own network-level capability model, per-site membership, and the specific "granted network-wide does not automatically apply per-site" distinction directly across its own Distinction, Diagnosis, Recovery, and Prevention sections, predating this taxonomy.
* **A login failing because the account does not exist on the specific site being accessed, or is flagged as spam at the network level** — already fully owned by `WP-ERROR-024` (WordPress Login Authentication Failure), whose own text already names the Multisite-specific `wp_authenticate_spam_check` hook and the "wrong site in the network" scenario directly, predating this taxonomy.
* **An upload rejected due to a multisite-specific per-site upload-space quota, or a multisite-narrower allowed-file-types list** — already fully owned by `WP-ERROR-036` (Upload Size Limit Exceeded) and `WP-ERROR-037` (Upload File Type Rejected) respectively, both of which already name their own multisite-specific quota/allowed-types behavior directly as one of their own causes, predating this taxonomy.
* **WP-CLI's own failure to supply a valid site-targeting input** (a missing or incorrect `--url`) — already fully owned by `WP-ERROR-042` (WP-CLI Multisite Site Context Resolution Failure), which explicitly reserved this category's own core territory (the next bullet) as a distinct condition it does not itself claim.
* **A `switch_to_blog()`/`restore_current_blog()` call imbalance in a plugin's or theme's own code**, leaving subsequent code in the same request executing against an unintended site's context — considered and rejected as this category's own territory; see Section 5.
* **A plugin declaring a `Network: true` (network-only) header being blocked from per-site activation, or an equivalent requirement-gate mismatch** — a genuine, observed gap in `WP-ERROR-031`'s own current text (which names only `Requires PHP`/`Requires at least`/`Requires Plugins` as its own requirement-gate causes), but not this category's own territory to claim or correct: the underlying mechanism is still Plugin category's own activation requirement gate, merely triggered by a multisite-specific plugin header. Disclosed here as an observed gap in a sibling category's own entry, not owned by or acted on within this taxonomy.

---

## 3. Planned Entries

| Entry | Title | Owns | Status |
|---|---|---|---|
| `WP-ERROR-045` | WordPress Multisite Site Resolution Failure | A verified condition in which WordPress's own Multisite site-resolution mechanism — which determines, from an incoming request's Host header and path, which specific site within the network the request belongs to — either fails to resolve any site at all (WordPress's own "no such site" refusal, before any site-specific code runs), or resolves to a site other than the one the request genuinely targets, due to a domain-mapping or `sunrise.php` misconfiguration, or corrupted/inconsistent `wp_blogs`/`wp_site` table data. The central territory `WP-ERROR-042` explicitly reserved for this category. | Planned |

Nothing else is currently planned for this category. Any future addition to this table is a revision to this document, not an ad hoc decision made while authoring an unrelated entry, per **SF-SPEC-013** Section 5.7.

---

## 4. Ownership Model

A single entry, not a multi-entry pipeline or independent-mechanisms model — the direct consequence of this taxonomy's own research finding that the overwhelming majority of "Multisite failure" candidates are already fully owned elsewhere (Section 2), leaving one genuinely novel, currently-unclaimed mechanism.

**Consolidation reasoning, modeled directly on `SF-TAXONOMY-010`'s own established precedent for `WP-ERROR-043`:** this entry deliberately keeps together two manifestations — resolving to no site at all, and resolving to the wrong site — because both stem from the same underlying mechanism (WordPress's own Host-header-driven site lookup, potentially intercepted or overridden by `sunrise.php`) and present to an observer in ways that require considering the same set of causes together to diagnose, the same reasoning that justified `WP-ERROR-043`'s own four-cause consolidation for Cron. Splitting "no site found" from "wrong site found" into separate entries would separate two symptoms of the same diagnostic investigation rather than reflect a genuine difference in mechanism.

**A single-entry category is not a weaker or less-researched outcome than a multi-entry one**, the same conclusion `SF-TAXONOMY-009` Section 4 already established for CLI's own comparably narrow scope: it is the correct, evidence-based result of a category whose adjacent territory this catalog had already substantially claimed by the time this taxonomy was drafted.

---

## 5. Candidates Considered and Rejected

* **A dedicated "Multisite Site Creation Failure" entry**: considered at length and rejected, not merely deferred. The two most plausible failure points within site creation — database-account privilege for the required `CREATE TABLE` operations, and the per-site tables themselves never being fully created — are already fully and specifically owned by `WP-ERROR-004` and `WP-ERROR-005` respectively (Section 2). What remains after excluding both (the central network-registration insert itself, or a domain/path uniqueness collision) was examined and found to be either an expected, correctly-functioning validation behavior (a uniqueness collision, analogous to a duplicate-username rejection) rather than a failure condition, or itself a variant of the same database-table-level conditions `WP-ERROR-004`/`005`/`006` already own generically. No genuine, currently-unclaimed remainder was found.
* **A dedicated `switch_to_blog()`/`restore_current_blog()` stack-imbalance entry**: considered and rejected. WordPress's own switching mechanism functions correctly when used as documented; an imbalance is caller (plugin or theme) code failing to pair its own calls correctly — a business-logic defect in calling code, the same class of exclusion this catalog has applied to a specific plugin's own defect throughout (`SF-TAXONOMY-005` Section 2, and every subsequent lifecycle taxonomy's own first exclusion). No evidence was found of a WordPress-core-level mechanism defect in `switch_to_blog()`/`restore_current_blog()` themselves.
* **A dedicated "Network Activation Failure" entry**: rejected outright, not merely folded elsewhere. `WP-ERROR-017`'s own existing text already establishes that network activation reuses the identical mechanism `WP-ERROR-031` already owns; creating a separate entry here would duplicate, not extend, existing territory.
* **A dedicated "Cross-Site Data Leakage" entry** (a plugin's own code failing to correctly scope a query, cache key, or file path to the current site in a multisite context): considered and rejected. This is a specific plugin's own multisite-awareness defect, not a failure in WordPress's own `switch_to_blog()`/`$wpdb->prefix` mechanism, which functions correctly when used as documented — the same class of exclusion applied to the `switch_to_blog()` stack-imbalance candidate above.

---

## 6. Revision History

| Version | Date | Summary of Changes | Status |
|---|---|---|---|
| 1.0 | 2026-07-15 | Initial taxonomy, drafted from zero entries. Resolves `SF-TAXONOMY-009` Section 2's own explicit forward-reference to Multisite as the owner of WordPress's own site-resolution mechanism. The proactive cross-category ownership sweep (standard since `SF-TAXONOMY-006`) found this category's own adjacent territory already extensively and specifically claimed across at least eight existing entries (`WP-ERROR-004`/`005`/`006`/`017`/`024`/`026`/`036`/`037`) and one reserving entry (`WP-ERROR-042`), narrowing this category to a single genuinely novel, currently-unclaimed mechanism — the sixth consecutive taxonomy to pass the sweep during drafting with no boundary correction required. Plans one entry, `WP-ERROR-045` (Multisite Site Resolution Failure), deliberately consolidating two manifestations (no site resolved, wrong site resolved) on the same precedent `SF-TAXONOMY-010` already established for `WP-ERROR-043`'s own four-cause consolidation. Site-creation, `switch_to_blog()` stack-imbalance, network-activation, and cross-site-data-leakage candidates each considered and rejected, per Section 5. Also discloses, without claiming or correcting, an observed gap in `WP-ERROR-031`'s own text (no `Network: true` header requirement-gate cause named) as Plugin category's own future maintenance, not this category's territory. | Frozen |
