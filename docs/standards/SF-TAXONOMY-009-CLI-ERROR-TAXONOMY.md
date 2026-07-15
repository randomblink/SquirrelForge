# SF-TAXONOMY-009 — CLI (WP-CLI) Error Taxonomy

## Document Information

**Document ID:** SF-TAXONOMY-009

**Title:** CLI (WP-CLI) Error Taxonomy

**Classification:** Engineering Taxonomy — a lightweight, informally-adopted planning artifact, governed by **SF-SPEC-013 — Knowledge Category Lifecycle Specification** for its role in the category lifecycle, but not itself templated by any `SF-TEMPLATE-XXX` document (none currently exists for this artifact type). It is not governed by **SF-SPEC-005**'s review process as a knowledge artifact and does not itself require an author/independent review pair, though an independent review of it is planned per this project's established practice (`SF-REVIEW-034`, `045`, `069`, `080`, `096`, `105`, `114`), not as a normative requirement this document imposes on itself.

**Status:** Frozen — the entry set in Section 3 is fixed until this document is deliberately revised (see Section 6). "Frozen" here is an informal, self-defined term describing this document's own adopted-plan state; it is not a claim of the `Version Frozen` **SF-SPEC-001** Section 18 lifecycle stage, nor of any **SF-SPEC-008** Section 6 Version Status value, nor of the category-level `Baseline Certified` designation **SF-SPEC-013** Section 5.5 defines. This document carries a `Version` and `Revision History` for traceability only; it does not present itself as a "versioned engineering artifact" within **SF-SPEC-008**'s own scope, the same disclaimer `SF-TAXONOMY-001`–`008` make.

**Version:** 1.1

**Owner:** SquirrelForge

---

## 1. Purpose

Declare the `CLI` category's boundary and complete planned `WP-ERROR` entry set *before* any entry in it is authored, per **SF-SPEC-013** Section 5.1's Category Entry Criteria — the first category in this catalog whose execution context is not an HTTP request at all, and consequently the first genuine test of whether this project's taxonomy-first methodology holds up outside the request/response failure domain every prior category has occupied.

This document does not itself contain any `WP-ERROR` knowledge content and is not cited as a `WP-ERROR` entry.

---

## 2. Category Boundary

**CLI** owns failures specific to WP-CLI's own execution context — mechanisms that exist only because WP-CLI runs as a standalone process with no incoming HTTP request, and therefore has no analog in any web-request-driven category. It does **not** own any WordPress-level mechanism WP-CLI merely *reaches* as an alternate, non-interactive entry point once that mechanism is running normally.

This distinction is unusually sharp for this taxonomy because of how this catalog has already been written: WP-CLI is cited, by name, in over a dozen existing entries as a valid, explicitly-acknowledged alternate entry point into a mechanism that entry already fully owns — `WP-ERROR-013` (bootstrap fatal error, "the WP-CLI bootstrap path... shares the same core, must-use plugin, drop-in, plugin, and theme bootstrap code"), `WP-ERROR-016` (`wp core verify-checksums`), `WP-ERROR-003`/`004` (`wp db query`), `WP-ERROR-031`/`032` (`wp plugin activate`/`update`), `WP-ERROR-039`/`040` (`wp theme activate`/`update`), `WP-ERROR-026` (`wp user list-caps`), and others. In every one of these cases, the underlying WordPress-level mechanism is identical whether reached through `wp-admin`, the REST API, or WP-CLI; the diagnostic and recovery content already lives in the owning entry, and this taxonomy creates no duplicate.

What genuinely has no such owner is WP-CLI's own pre-WordPress, tool-level behavior — the steps WP-CLI itself performs *before* it ever hands control to WordPress's own `wp-load.php`, which a web request never performs at all because a web server's docroot already *is* the WordPress installation, with no discovery step required.

**Explicitly not owned by CLI:**

* **Any WordPress-level mechanism WP-CLI reaches as an alternate entry point, once that mechanism is actually running** — the full list above, and by extension any future entry that documents a WordPress mechanism generically enough that a corresponding `wp` subcommand would reach the same code path. This taxonomy does not attempt to enumerate every such entry exhaustively; the governing rule is that if a mechanism's own diagnostic and recovery content does not differ meaningfully between a web request and a WP-CLI invocation, it belongs to that mechanism's own owning entry, not here.
* **A PHP fatal error occurring during WP-CLI's own bootstrap, once `wp-load.php` has actually been found and invoked** — already fully owned by `WP-ERROR-013`, whose own Section 8 (WordPress Components) and Section 11 (Diagnosis) already explicitly name the WP-CLI bootstrap path as sharing identical code with a web request's own bootstrap sequence, predating this taxonomy.
* **`wp-config.php` being missing, unreadable, or invalid once a WordPress installation root has already been correctly identified** — the conceptual territory `WP-ERROR-013` Section 16 already assigns to the not-yet-authored `WP-ERROR-010` (Configuration File Missing) and `WP-ERROR-011` (Configuration File Invalid), a condition that occurs identically for a web request and a WP-CLI invocation once both are looking at the same, correctly-identified directory. This category's own first entry (Section 3) is deliberately scoped to a *different*, earlier failure: WP-CLI's own separate, tool-level search process for *finding* an installation root at all, which a web request never performs and which can fail even when a perfectly valid `wp-config.php` exists somewhere on disk, simply because WP-CLI was never told or could not determine where to look.
* **WordPress Multisite's own site-resolution mechanism failing for an HTTP request** (for example, a domain-mapping or `sunrise.php` defect, or corrupted `wp_blogs`/`wp_site` table data causing a legitimate request's own Host header to resolve incorrectly) — the future `Multisite` category's own territory, per `SF-SPEC-001` Section 7's separate `Multisite` category value, not yet given its own taxonomy. This category's own second entry (Section 3) is deliberately narrower: WP-CLI's own failure to *supply* a valid site-targeting input in the first place (since it has no Host header to derive one from automatically), not a defect in Multisite's own resolution logic once given valid input. Where diagnosis traces a WP-CLI site-targeting failure back to genuinely corrupted Multisite data rather than a missing or incorrect `--url`, that underlying condition would be `Multisite`'s own territory once that category exists.
* **WP-CLI's own tool-level requirements** — the `wp` binary's own required PHP CLI version, its own installation or `PATH` availability, or its own update mechanism (`wp cli update`) — none of which is a WordPress-level condition at all. This catalog documents WordPress's own behavior, not the operational requirements of a third-party client tool that happens to interact with it; out of scope for this category and this catalog entirely.
* **WP-CLI's own design choice to bypass or simplify WordPress's ordinary capability-checking machinery for many commands** — an intentional characteristic of a tool built for administrators who already have server-level access, not a failure condition. Considered and rejected as a planned entry; see Section 5.
* **Connectivity failures reaching a remote target via WP-CLI's own `--ssh=`/`--http=` transport** — an infrastructure/transport-level condition (SSH or a remote WP-CLI-compatible HTTP endpoint), not a WordPress-level condition this catalog documents elsewhere either. Considered and rejected as a planned entry; see Section 5.

---

## 3. Planned Entries

| Entry | Title | Owns | Status |
|---|---|---|---|
| `WP-ERROR-041` | WP-CLI Cannot Locate a WordPress Installation | A verified condition in which WP-CLI's own pre-bootstrap search process — walking the directory tree from the current working directory (or the location `--path` specifies) looking for a WordPress installation — fails to find one, and WP-CLI exits with its own distinct error ("This does not seem to be a WordPress installation...") without ever invoking `wp-load.php` or handing control to WordPress at all. Distinct from `wp-config.php` being missing or invalid within an installation WP-CLI has already correctly located (conceptually `WP-ERROR-010`/`011`'s own future territory) and from any bootstrap-sequence fatal error (`WP-ERROR-013`'s own territory, which presumes `wp-load.php` was actually reached). | Existing, Production Ready |
| `WP-ERROR-042` | WP-CLI Multisite Site Context Resolution Failure | A verified condition in which WP-CLI, operating against a WordPress Multisite installation, fails to establish which specific site a command should run against — because no `--url` (or equivalent configured default) was supplied at all, or because the supplied `--url` does not match any site WordPress's own Multisite site-resolution logic recognizes — and either errors outright or, more subtly, silently operates against an unintended site (commonly the network's primary site) rather than the one the operator intended. Owns WP-CLI's own failure to supply a valid site-targeting input, the substitute a WP-CLI invocation shall provide for the Host header an HTTP request supplies automatically; does not own a defect in Multisite's own site-resolution mechanism itself once given valid input, which remains the future `Multisite` category's own territory. | Planned |

Nothing else is currently planned for this category. Any future addition to this table is a revision to this document, not an ad hoc decision made while authoring an unrelated entry, per **SF-SPEC-013** Section 5.7.

---

## 4. Ownership Model

The two entries divide by **pre-WordPress WP-CLI mechanism**, a structurally different shape from every prior taxonomy in this catalog:

* `WP-ERROR-041` owns the **installation-discovery** stage — the very first thing WP-CLI attempts, before any WordPress code runs at all.
* `WP-ERROR-042` owns the **site-context-targeting** stage — reachable only once installation discovery has already succeeded, and only meaningful at all for a Multisite installation (a single-site installation has no comparable targeting step to fail, since there is only ever one possible site).

This is neither a fully independent-mechanisms model (`SF-TAXONOMY-005`'s Plugin Lifecycle) nor a fully sequential pipeline every invocation passes through (`SF-TAXONOMY-007`'s Media): it is **sequential-but-conditional** — `WP-ERROR-041`'s own condition is a precondition every WP-CLI invocation shall clear before `WP-ERROR-042`'s own condition becomes reachable at all, but `WP-ERROR-042`'s own condition is additionally gated on the discovered installation actually being a Multisite network, meaning the large majority of WP-CLI invocations (against a single-site installation) can never reach it regardless of how correctly `--url` is or isn't supplied. The two entries remain mutually exclusive by construction: a command that fails at `WP-ERROR-041`'s own stage never reaches far enough into execution for `WP-ERROR-042`'s own condition to apply at all.

**Evidentiary basis for this category's own unusually narrow scope:** unlike every prior category, this taxonomy's own research found the overwhelming majority of "could WP-CLI fail here" candidates already fully claimed by existing entries the moment the underlying WordPress mechanism was examined, because this catalog has consistently written its own prior entries to name WP-CLI explicitly as a valid alternate entry point from the outset (see Section 2). This is not a sign of insufficient research; it is the expected outcome of a catalog that has, by this point, already documented most of the WordPress-level mechanisms WP-CLI's own commands ultimately reach. A category is not required to reach a particular entry count to be complete — two well-bounded, genuinely novel entries are a complete category here, not an artificially truncated one.

---

## 5. Candidates Considered and Rejected

* **A generic "WP-CLI reaches a WordPress-level failure" catch-all entry:** rejected outright. This is precisely the territory Section 2 already establishes is comprehensively owned, entry by entry, by more than a dozen existing entries that explicitly name WP-CLI as an entry point. An entry attempting to "receive" this territory would either duplicate existing diagnostic content or, worse, create a second, competing description of a mechanism another entry already owns — the same reasoning `SF-TAXONOMY-005` Section 2 applied to reject a "generic plugin defect" entry for Plugin.
* **WP-CLI's own tool-level version/compatibility requirements as an entry:** rejected. Not a WordPress-level condition; see Section 2's own final exclusion bullets.
* **WP-CLI's own capability/`--user` context design as an entry:** rejected. WP-CLI's default behavior of running many commands without WordPress's ordinary capability checks is an intentional design characteristic of a tool built for users who already have server-level access, not a failure condition with a diagnosable root cause and a corrective recovery procedure — the same class of reasoning `SF-TAXONOMY-005` Section 2 applied to a specific plugin's own business-logic defect.
* **A dedicated `--ssh=`/`--http=` remote-transport connectivity entry:** deferred, not rejected outright. WP-CLI's own remote-execution transport genuinely has no owner elsewhere in this catalog, but this taxonomy found no evidence yet that its own failure modes differ meaningfully from ordinary SSH or HTTP connectivity conditions already outside this catalog's own WordPress-level scope (this catalog documents WordPress's own behavior, not SSH's). Disclosed as a genuinely undecided gap rather than silently rejected; a future revision to this taxonomy could still carve it out per **SF-SPEC-013** Section 5.6 if evidence emerges that WP-CLI's own remote-transport layer produces a WordPress-specific (not merely SSH-specific) failure mode.
* **A dedicated "WP-CLI exit code / structured error output" entry:** rejected. WP-CLI's own error-reporting conventions (`WP_CLI::error()`, non-zero exit codes) are the tool's own reporting mechanism for whatever underlying condition actually occurred; they are not themselves a failure condition to diagnose, any more than an HTTP 500 status code would be cataloged as its own condition independent of what produced it.

---

## 6. Revision History

| Version | Date | Summary of Changes | Status |
|---|---|---|---|
| 1.0 | 2026-07-15 | Initial taxonomy, drafted from zero entries. The proactive cross-category ownership sweep (standard since `SF-TAXONOMY-006`) found the overwhelming majority of candidate WP-CLI failure conditions already fully owned by more than a dozen existing entries that explicitly name WP-CLI as an alternate entry point, predating this taxonomy — requiring no boundary correction to any of them, but substantially narrowing this category's own genuinely available territory. Plans two entries — `WP-ERROR-041` (WP-CLI Cannot Locate a WordPress Installation) and `WP-ERROR-042` (WP-CLI Multisite Site Context Resolution Failure) — dividing the category as a sequential-but-conditional pair, a structurally new ownership model for this catalog. Explicitly distinguishes both from the not-yet-authored `WP-ERROR-010`/`011` (Configuration) and from the future `Multisite` category's own eventual territory. A generic WP-CLI catch-all entry, WP-CLI's own tool-level requirements, its `--user`/capability design, and its own error-reporting conventions considered and rejected; a dedicated remote-transport (`--ssh=`) entry considered and deferred as a genuinely undecided gap. | Frozen |
| 1.1 | 2026-07-15 | WP-ERROR-041 reached Production Ready (SF-REVIEW-122 author review, no findings; SF-REVIEW-123 independent review, which corrected a cross-document completeness gap in WP-ERROR-013's own Distinction section rather than finding a defect in this entry or this taxonomy). Status column updated from Planned to Existing, Production Ready. No boundary content changed; this entry was drafted directly from this taxonomy's own Version 1.0 declaration and required no revision to it. Also notable: the first entry in this catalog reasoned to Low severity from first principles, a deliberate departure from the usual range-based Critical pattern, independently substantiated by both reviews. | Frozen |
