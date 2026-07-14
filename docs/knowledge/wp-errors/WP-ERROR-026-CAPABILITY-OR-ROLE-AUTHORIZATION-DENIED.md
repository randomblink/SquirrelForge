# WP-ERROR-026 — WordPress Capability or Role Authorization Denied

---

# 1. Knowledge Entry

WordPress Capability or Role Authorization Denied

---

# 2. Metadata

* **Error ID:** `WP-ERROR-026`
* **Title:** WordPress Capability or Role Authorization Denied
* **Category:** Authentication
* **Severity:** Critical
* **Recovery Priority:** Immediate
* **Status:** Production Ready
* **Version:** 1.0

---

# 3. Summary

WordPress has already established the user's identity (`WP-ERROR-024`'s own condition, resolved favorably) and accepted the authenticated session (`WP-ERROR-025`'s own condition, likewise resolved favorably), but denies a requested non-REST action or screen because `current_user_can()` — or an equivalent capability check — evaluates to `false` for that specific user and that specific capability. This entry is capability-centered: WordPress's actual authorization decision is made against capabilities, not role names, even though administrators most often describe and diagnose the symptom in terms of roles.

---

# 4. Primary Failure Mode

A verifiably authenticated user, holding a verifiably valid session, requests a non-REST action or screen that WordPress or a plugin/theme gates behind a specific capability check (`current_user_can( $capability )`, or the underlying `map_meta_cap`/`user_has_cap` filter chain it invokes). That check evaluates to `false` for this specific user, and the request is denied — typically with a "Sorry, you are not allowed to..." message or a `wp_die()` screen — even though authentication and session validity are not in question.

---

# 5. Severity

This entry is classified **Critical**, with impact ranging depending on scope:

- Where a single user lacks a single, narrowly-scoped capability, the impact is typically limited to that one user and that one action — every other function they are entitled to continues to work normally.
- Where a capability-resolution mechanism itself is broken (a corrupted or orphaned `wp_capabilities` user-meta record — for example following a database table-prefix change that was not applied consistently — or a `map_meta_cap`/`user_has_cap` filter added by a plugin that unconditionally denies a broad capability class), the impact can be a full administrative lockout: every user, including genuine administrators, is denied access to `wp-admin` functionality entirely, despite being correctly authenticated.
- This entry remains classified at the level of its most severe possible manifestation, consistent with the range-based Critical classification used elsewhere in this catalog (`WP-ERROR-021`, `024`, `025`).

---

# 6. Distinction

This entry applies only when verified evidence establishes that authentication and session validity are **not** the cause of the denial — the user is confirmed logged in, with a currently-valid session, and the denial is specifically a capability decision made about that already-identified, already-session-valid user.

**This entry is capability-centered, not role-centered.** A WordPress role (`Administrator`, `Editor`, and so on) is a named, configurable bundle of capabilities and a convenience/configuration mechanism (`WP_Roles`), not itself the object WordPress's authorization logic evaluates at the point of decision. `current_user_can()` and the `map_meta_cap`/`user_has_cap` filter chain it invokes always resolve to a capability-level decision, even when the check superficially appears to be about a role (`current_user_can( 'administrator' )` is itself checking whether the user holds a capability literally named `administrator`, which the default Administrator role happens to be granted — not querying the user's role assignment directly). This entry's title retains "Role" because that is how the symptom is most commonly described and searched for by administrators; its own Diagnosis and Recovery sections (11, 12) are deliberately written to avoid treating a role name as the check itself.

It is distinct from:

- **`WP-ERROR-024` — WordPress Login Authentication Failure**: presumes identity was never established. This entry presumes the opposite — identity is verifiably established, and the denial happens regardless.
- **`WP-ERROR-025` — WordPress Authentication Cookie Invalid or Expired**: presumes the session itself is invalid or was never accepted. This entry presumes the session is currently valid; the denial is a decision made *about* an already-accepted session, not a rejection of the session itself.
- **`WP-ERROR-022` — WordPress REST API Access Denied**: covers authorization denial occurring specifically within REST API request handling, including REST's own `permission_callback` mechanism. A capability check failing inside a REST endpoint's `permission_callback` belongs to `WP-ERROR-022`, not here, even though the underlying `current_user_can()` mechanism may be identical — the boundary is drawn by request context (REST versus non-REST), not by which function was called.
- **`WP-ERROR-027` — WordPress Nonce Verification Failure (Non-REST)**: the user may hold every required capability and still fail nonce verification, because a nonce checks request origin/freshness, not permission. Conversely, a user can pass nonce verification and still be denied by this entry's own capability check — the two conditions are independent and can occur in either order depending on how a given plugin or theme's own code is written.
- **Plugin-specific policy systems** — a membership plugin's own access-tier logic, a workflow/approval-state gate, or a custom access-control framework a plugin implements independently: these belong to the Plugin category *unless* they ultimately resolve to, and are correctly diagnosable as, a standard WordPress capability denial this entry's own mechanism (`current_user_can()`, `map_meta_cap`, `user_has_cap`) produces. A plugin that implements its own entirely separate authorization system with no relationship to WordPress's own capability model is Plugin-category territory regardless of how similar the symptom looks.

---

# 7. Scope

**Covered:** A verified condition in which an authenticated user with a valid session is denied a non-REST WordPress action or screen because a standard WordPress capability check (`current_user_can()`, or the `map_meta_cap`/`user_has_cap` filter chain underlying it) evaluates to `false` for that user and that capability.

**Excluded:**

- The user's identity never being established (`WP-ERROR-024`).
- The user's session being invalid, expired, or never accepted (`WP-ERROR-025`).
- Authorization decisions made within REST API request handling (`WP-ERROR-022`).
- Request-origin/nonce verification, independent of capability (`WP-ERROR-027`).
- A plugin's own, entirely independent access-control or membership system that does not route through WordPress's own capability model.
- A missing PHP extension or unsupported PHP version preventing the capability-checking code from executing at all (PHP Runtime category).
- A database-layer failure preventing the user's own capability metadata from being read at all (Database category) — this entry presumes the database is reachable and the capability metadata, whatever it contains, can be read; the condition it owns is that the resolved decision is `false` and verifiably ought not to be, or that the resolution mechanism itself is misconfigured.

---

# 8. WordPress Components

Listed as components commonly involved, not as a claim that every installation exercises every one of them:

- `current_user_can()` (`wp-includes/capabilities.php`), the primary function nearly all core and plugin authorization checks call, which delegates to `WP_User::has_cap()` for the current user.
- `WP_User::has_cap()`, which applies the `map_meta_cap` filter (translating a "meta" capability such as `edit_post` into one or more primitive capabilities based on context — the specific post's author, post type, and status) and then the `user_has_cap` filter (the lowest-level, broadest filter, receiving the user's full resolved capability array), before returning a final boolean.
- `WP_Roles` and the `wp_roles()` global, which define each role's own default capability set and provide `add_cap()`/`remove_cap()`/`add_role()`/`remove_role()` for modifying them.
- The `wp_capabilities` user-meta key (its literal name derived from the site's own table prefix, for example `wp_capabilities`), which stores each user's role and any individually-granted or individually-removed capabilities as a serialized array.
- `map_meta_cap()` (`wp-includes/capabilities.php`), the core function implementing the meta-to-primitive capability translation, and the specific `map_meta_cap` filter plugins commonly use to alter that translation for their own custom post types or custom meta capabilities.
- `is_super_admin()` and Multisite's own network-level capability model, which governs `manage_network`-class capabilities separately from any single site's own role/capability assignments, and which determines whether a user is even a recognized member of the specific site being accessed.
- `wp_die()`, commonly invoked with "Sorry, you are not allowed to..." or "You do not have sufficient permissions to access this page." messaging when a core screen's own capability check fails.

---

# 9. Typical Symptoms

- "Sorry, you are not allowed to [action]." or "You do not have sufficient permissions to access this page." displayed in place of the expected screen or action result.
- A menu item, screen, or button that other, differently-privileged users can see and use is entirely absent for the affected user, rather than visibly present but erroring — many core and plugin UIs hide, rather than merely block, actions the current user lacks capability for.
- `current_user_can()` returning `false` in a debugging context for a capability the administrator believes the user's assigned role should include.
- The condition affects one specific action or screen while every other capability-gated function continues to work normally for the same user — a signal the resolution mechanism itself is intact and only one specific capability or mapping is at fault.
- The condition affects every user simultaneously, including previously-functioning administrators — a strong signal the resolution mechanism itself (role definitions, the `wp_capabilities` meta key's own integrity, or a broadly-scoped filter) is what changed, not any individual user's own assignment.
- (Multisite) A user who has full capabilities on one site in the network is denied on another, despite believing their account should have equivalent access everywhere.

---

# 10. Common Causes

Causes are grouped by category, and are deliberately kept separate from one another rather than treated as interchangeable, since the corrective action differs materially between them. Inclusion in this list identifies a category as plausible; it does not assert that any specific cause is present without diagnostic confirmation.

- **The user genuinely lacks the required capability.** The assigned role's own default capability set, as WordPress core defines it or as the site has customized it, does not include the capability the requested action requires — the check is functioning correctly and the denial is the intended, correct behavior for this user's current assignment.
- **The capability is assigned to a different role than the administrator expects.** A common source of confusion where an administrator believes a capability is included in one role (for example, assuming `Editor` includes a capability that is actually `Administrator`-only, or vice versa after a custom role modification), rather than the resolution mechanism itself being at fault.
- **Role or capability metadata is missing, stale, or corrupted.** The `wp_capabilities` user-meta value is malformed (an improperly serialized array, often from a faulty direct database edit or import), references a role that no longer exists (deleted or renamed by a theme switch or plugin deactivation that removed a custom role), or was orphaned by a table-prefix change applied inconsistently across the database.
- **Custom code checks the wrong capability.** A plugin or theme's own `current_user_can()` call references an incorrect, misspelled, or overly-broad/narrow capability string — a verified code defect rather than a configuration issue, and Plugin-category territory for the fix even though this entry's own mechanism is what surfaces the symptom.
- **A plugin or theme filters capability resolution.** A `map_meta_cap` or `user_has_cap` callback added by a plugin alters the final resolved capability set — intentionally (a security-hardening plugin restricting a specific capability network-wide) or as a side effect of a defect in that plugin's own filter logic.
- **Multisite context changes which capability or site membership is required.** A capability that is granted network-wide does not automatically apply per-site, and vice versa; a user who is a genuine member of one site in the network with full capabilities there may have no membership, or a different role, on another site in the same network.

---

# 11. Diagnosis

Start with the broadest check before investigating specific roles, capabilities, filters, or stored metadata:

1. Confirm the user is genuinely authenticated with a currently-valid session (rule out `WP-ERROR-024`/`025` as the actual cause before proceeding, since both can produce superficially similar "you can't do that" symptoms if diagnosed carelessly).
2. Confirm the request actually reached the intended action or screen's own handler — that the denial is a capability decision made by that handler, not a routing, bootstrap, or REST-context condition (`WP-ERROR-022`) presenting a similar symptom.

Only once both are confirmed, proceed to the capability-specific investigation:

3. Identify the *specific* capability string the requested action or screen actually checks — from WordPress core's own documented capability requirements for that screen, the specific plugin's or theme's own documentation, or, where undocumented, direct inspection of the relevant `current_user_can()` call in code.
4. Retrieve the affected user's own actual, current role and capability assignment directly (via WP-CLI, `wp user list-caps <user>`, or direct inspection of the `wp_capabilities` user-meta value) rather than assuming it from the role name alone.
5. Confirm whether the identified capability is genuinely included in the user's assigned role's own default set, as currently defined on this specific site — role capability sets are commonly customized away from WordPress core's own defaults, so a general assumption about what a given role "should" include is not sufficient evidence.
6. Inspect the `wp_capabilities` meta value's own integrity — confirm it is validly serialized, references a role that currently exists, and was not orphaned by a table-prefix change or a faulty import/migration.
7. Identify every `map_meta_cap` and `user_has_cap` filter callback registered by active plugins (code search for `add_filter( 'map_meta_cap'` / `add_filter( 'user_has_cap'`) to determine whether a plugin is altering the resolution for this specific capability.
8. On Multisite, confirm the user's actual site membership and per-site role on the specific site the action was attempted on, separately from any network-level capability they may or may not hold.
9. Where the condition affects multiple or all users simultaneously, prioritize investigating the resolution mechanism itself (role definitions, a broadly-scoped filter, or metadata corruption from a recent migration) over any individual user's own assignment.
10. Preserve relevant evidence — the exact capability string involved, the user's own resolved capability list at the time of the denial, and whether the condition is single-user or site-wide — before modifying any role, capability, or filter.

```text
# Example only — illustrates retrieving a user's actual resolved capabilities
# via WP-CLI; exact syntax depends on the WP-CLI version in use.
wp user list-caps <user_login_or_ID>
```

---

# 12. Recovery Procedure

Recovery shall target the precise, verified cause identified in Diagnosis (Section 11) — the smallest faulty assignment, mapping, or check — not a broad escalation of privilege.

**Elevating the affected user to Administrator is not a permitted recovery action for this entry**, regardless of how convenient it may appear, unless diagnosis has specifically and affirmatively established that Administrator-level access is the genuinely correct, intended assignment for this user going forward — a business decision distinct from, and not a substitute for, correcting the specific capability gap that was actually diagnosed.

Permitted recovery categories, depending on the verified cause, include:

- Granting the specific, identified capability to the user's existing role, or to the individual user directly, where the current assignment is verifiably narrower than intended.
- Correcting a custom role definition that omits a capability it was intended to include, at the role level rather than the individual-user level, where the gap affects every user holding that role.
- Repairing corrupted or orphaned `wp_capabilities` metadata — restoring a valid serialized structure, or correcting an orphaned reference to a role that no longer exists, via WP-CLI or direct, careful database correction.
- Correcting a plugin's or theme's own custom code that checks an incorrect capability string — a code-level fix, tracked and applied as a Plugin-category correction even though this entry's own mechanism surfaced the symptom.
- Adjusting or removing a `map_meta_cap`/`user_has_cap` filter callback found to be incorrectly denying the capability, where that filter's own intended behavior does not require the denial that occurred.
- On Multisite, correcting the user's actual site membership or per-site role, where their intended access does not match their current membership.

This entry does not prescribe disabling capability checks entirely (for example, via a blanket `user_has_cap` filter that grants every capability unconditionally) as a diagnostic shortcut or a permanent workaround for an unresolved underlying cause.

---

# 13. Validation

Recovery is successful when:

- `current_user_can()` for the specific capability and the specific user returns `true` in the actual, unmodified production context — not merely inferred from a role name matching expectation.
- The originally-denied action or screen completes successfully for the affected user, not merely that the "Sorry, you are not allowed to" message no longer appears.
- No other user's own capability set was unintentionally broadened or narrowed by the correction — a role-level fix, in particular, shall be confirmed not to have granted the capability to users who should not hold it.
- Where a `map_meta_cap`/`user_has_cap` filter was modified, that filter's own other, legitimate behavior continues to function correctly for capabilities and users it was not intended to affect.
- On Multisite, the correction is confirmed effective on the specific site(s) intended, without unintended network-wide effect where only site-specific access was the goal.
- The affected user was not granted Administrator or another broad role as an unverified substitute for the specific capability actually diagnosed as missing.

---

# 14. Prevention

- Grant capabilities at the role level, and design custom roles deliberately, rather than accumulating individually-granted per-user capability exceptions that become difficult to audit over time.
- Document any customization to a default role's own capability set, including which plugin or manual action made the change and why, so a future "capability assigned to a different role than expected" investigation has a starting reference.
- Audit registered `map_meta_cap` and `user_has_cap` filters after installing or updating any security, membership, or access-control plugin, to understand what capability resolution those plugins alter.
- Treat `wp_capabilities` metadata integrity as part of routine post-migration and post-import validation, particularly after any database table-prefix change.
- Avoid resolving an individual access complaint by granting Administrator access as a default response; treat it as a signal to identify and correctly scope the actually-required capability instead.
- On Multisite, maintain clear documentation of intended per-site membership and role assignments, particularly for users expected to have consistent access across multiple sites in the network.

---

# 15. Security Considerations

- Recovery shall never grant a capability, or a role, broader than what diagnosis specifically established as required — the "smallest faulty assignment" principle in Section 12 is a security requirement, not only an engineering-cleanliness preference.
- A `user_has_cap` or `map_meta_cap` filter that grants capabilities unconditionally, even temporarily for diagnostic purposes, is a significant privilege-escalation exposure if left in place or if it affects users beyond the one being diagnosed; remove any such diagnostic filter immediately after use, and confirm its removal as part of Validation (Section 13).
- Corrupted `wp_capabilities` metadata should be evaluated for whether the corruption resulted from an unauthorized modification (a compromise indicator) rather than an ordinary migration or import defect, particularly where the corruption unexpectedly *broadened* rather than narrowed access.
- Where a security- or hardening-plugin's own capability filter is found to be the cause of an over-broad denial, confirm the plugin's own intended security posture before loosening it, rather than assuming the denial itself is the defect.
- On Multisite, a user's unexpected access to a site they should not be a member of is a security-relevant finding in its own right, not solely an inconvenience, and shall be investigated with corresponding urgency alongside any narrower, single-user denial being diagnosed.

---

# 16. Related Errors

The following are cited as they exist in this repository, or as conceptual distinctions where noted.

1. [WP-ERROR-024 — WordPress Login Authentication Failure](WP-ERROR-024-WORDPRESS-LOGIN-AUTHENTICATION-FAILURE.md) — exists in this repository; see Section 6 (Distinction) above for how this entry presumes identity is already established.
2. [WP-ERROR-025 — WordPress Authentication Cookie Invalid or Expired](WP-ERROR-025-AUTHENTICATION-COOKIE-INVALID-OR-EXPIRED.md) — exists in this repository; see Section 6 (Distinction) above for how this entry presumes the session is already valid.
3. [WP-ERROR-022 — WordPress REST API Access Denied](WP-ERROR-022-REST-API-ACCESS-DENIED.md) — exists in this repository; see Section 6 (Distinction) above for how REST-context authorization denial is drawn by request context rather than by mechanism.
4. [WP-ERROR-027 — WordPress Nonce Verification Failure, Non-REST](WP-ERROR-027-NONCE-VERIFICATION-FAILURE-NON-REST.md) — exists in this repository (currently `Draft`); see Section 6 (Distinction) above for why request-origin verification is independent of capability.

---

# 17. Notes

This entry documents the general, verified observable condition of a standard WordPress capability check denying an already-authenticated, already-session-valid user. It is deliberately capability-centered rather than role-centered, per explicit direction: a role name is a configuration and bundling convenience, not the object WordPress's own authorization logic evaluates at decision time, and this entry's Diagnosis and Recovery sections are written to keep that distinction operative rather than treating "change the user's role" as the default corrective action.

This entry does not own a plugin's own independent, non-capability-routed access-control or membership system; per Section 6, such a system belongs to the Plugin category unless it is verifiably implemented through WordPress's own `current_user_can()`/`map_meta_cap`/`user_has_cap` mechanism.

This is the third of four entries `SF-TAXONOMY-003` plans for the Authentication category, and explicitly assumes both `WP-ERROR-024`'s and `WP-ERROR-025`'s own conditions have already been resolved favorably as its own starting condition, per the project owner's own recommended layered-progression approach.

This entry underwent the review sequence required by **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**: an author (Class A) review at `docs/reviews/SF-REVIEW-074-WP-ERROR-026-AUTHOR-REVIEW.md`, followed by an independent (Class B) review at `docs/reviews/SF-REVIEW-075-WP-ERROR-026-INDEPENDENT-REVIEW.md`, which reached outcome **Approved with Minor Revisions**, corrected a stale generic Authentication-category hedge in `WP-ERROR-022` and `SF-TAXONOMY-002` (both caused by this entry's own existence), and satisfied the Production Ready gate per SF-SPEC-012 Section 12. Its Status was changed to Production Ready on that basis. This document does not itself constitute either review record; see the cited files for full findings, corrections, and gate decisions.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.
