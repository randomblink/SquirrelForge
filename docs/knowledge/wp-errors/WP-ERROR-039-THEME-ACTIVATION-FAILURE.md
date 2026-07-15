# WP-ERROR-039 — WordPress Theme Activation (Switching) Failure

---

# 1. Knowledge Entry

WordPress Theme Activation (Switching) Failure

---

# 2. Metadata

* **Error ID:** `WP-ERROR-039`
* **Title:** WordPress Theme Activation (Switching) Failure
* **Category:** Theme
* **Severity:** Critical
* **Recovery Priority:** Immediate
* **Status:** Production Ready
* **Version:** 1.0

---

# 3. Summary

WordPress fails to bring a specific target theme into the active state when a switch is attempted, through one of three mechanically distinct points: WordPress's own pre-switch requirement check (the target theme's declared `Requires PHP`, `Requires at least`, or `Requires Plugins` header values) refuses to attempt the switch at all; WordPress's own theme-integrity check detects the target theme is "broken" — a missing or unreadable `style.css`, or, for a child theme, a `Template` header naming a parent theme that is not installed — and refuses the switch before any of the target theme's own code executes; or the target theme's own `after_switch_theme` callback itself fails, throws, or leaves setup incomplete, which is the most severe of the three causes because WordPress commits the theme-option change *before* this specific hook fires, so the target theme becomes the site's active theme regardless of whether its own callback succeeds.

---

# 4. Primary Failure Mode

An administrator, or an automated process such as WP-CLI's `wp theme activate`, attempts to switch the site's active theme to a specific target theme. WordPress's own switching mechanism — a discrete, deliberately-triggered event distinct from the ordinary bootstrap sequence a normal request runs, since reaching the switch action itself requires WordPress to have already bootstrapped successfully — fails to complete that transition correctly. The failure occurs at exactly one of three distinguishable points: before any of the target theme's own code has executed at all (a requirement-gate refusal or a broken-theme detection, both evaluated from the theme's own header metadata and file presence rather than by executing its PHP), or after WordPress has already committed the theme-option change, within the target theme's own `after_switch_theme` callback. Unlike the first two causes, the third leaves the target theme genuinely active despite its own failure, since WordPress's `switch_theme()` function updates the `template`/`stylesheet` options and only then fires `after_switch_theme` — there is no equivalent, for theme switching, of the sandboxed pre-flight test WordPress's own plugin-activation mechanism performs before ever recording a plugin as active.

---

# 5. Severity

This entry is classified **Critical**, with impact ranging depending on which of the three causes occurs:

- Where WordPress's own requirement gate or broken-theme detection refuses the switch cleanly (causes 1 and 2), the impact is narrow — the administrator receives a clear, WordPress-presented explanation, no code belonging to the target theme ever ran, the previously active theme remains active and fully functional, and the rest of the site is entirely unaffected.
- Where the target theme's own `after_switch_theme` callback fails (cause 3), the impact is materially more severe than either of the other two causes, and more severe than this catalog's own comparable plugin-activation entry (`WP-ERROR-031`) at its own worst case: the theme-option change has already been committed, so the target theme — including whatever defect caused its own callback to fail — is now the site's genuinely active theme. If that defect is severe enough to constitute a fatal PHP error rather than a caught, gracefully-handled failure, the very same code becomes the active theme's `functions.php` and will execute unconditionally on the next page load, which is `WP-ERROR-013`'s own bootstrap-fatal-error condition (see Section 6). A failed plugin activation, by contrast, never leaves a fatally-broken plugin recorded as active, because `WP-ERROR-031`'s own activation mechanism performs a sandboxed pre-flight check specifically to prevent that outcome — theme switching has no equivalent safeguard.
- This entry remains classified at the level of its most severe possible manifestation, consistent with the range-based Critical classification used elsewhere in this catalog (`WP-ERROR-021`, `024`–`030`, `032`–`033`, `035`, `038`).

---

# 6. Distinction

This entry applies only when verified evidence establishes that a specific, deliberately-triggered attempt to switch the active theme to a named target theme did not complete correctly, and that the failure occurred at one of the three points Section 4 describes.

**This entry is not a bootstrap-sequence condition, with one explicit downstream exception.** Reaching the theme-switching action at all — whether through the Appearance → Themes screen, the Customizer's own "Activate & Publish" action, or a WP-CLI `wp theme activate` command — requires WordPress to have already completed its own bootstrap sequence successfully; switching is a deliberate, authenticated, post-bootstrap action, not a step in the bootstrap sequence itself. `WP-ERROR-013`'s own Section 4 already names "the active theme's `functions.php`" as a bootstrap-sequence component, and its own Section 10 already names "the active theme's `functions.php` raising a fatal error during theme setup" as one of its own causes — that condition, occurring on an *ongoing*, every-request basis once a theme is already active, remains fully `WP-ERROR-013`'s own territory and requires no correction to establish this boundary. The explicit exception: where diagnosis under this entry (Section 11) traces a *subsequent* request's bootstrap-sequence fatal error directly back to a specific, identified switch attempt whose own `after_switch_theme` callback failed (cause 3), that fatal error is a distinct, downstream symptom of this entry's own condition, diagnosed and resolved through this entry — not an independent `WP-ERROR-013` condition to chase separately once this entry's own root cause is identified, the same resolution model `WP-ERROR-032` Section 6 already establishes for an interrupted plugin update's own downstream bootstrap fatal error.

**Three internal causes this entry keeps deliberately separate, not blended into a single generic "switch failed" condition, since each has a different diagnostic starting point, a different recovery path, and — uniquely among this catalog's lifecycle-mechanism entries — a materially different severity profile:**

1. **Requirement-gate refusal** — WordPress's own pre-switch check (evaluating the target theme's declared `Requires PHP`, `Requires at least`, and, for themes registering it, `Requires Plugins` header values against the running environment) determines a requirement is unmet and refuses to attempt the switch at all. No code belonging to the target theme itself ever executes, and the previously active theme remains active and undisturbed.
2. **Broken-theme detection** — the requirement gate is passed (or the target theme declares no requirements), but WordPress's own theme-integrity check (`WP_Theme::errors()`) determines the target theme is unusable: its `style.css` is missing or unreadable, or, for a child theme specifically, its own `Template` header names a parent theme directory that is not actually installed. This check is based on reading the theme's own header metadata and confirming file/directory presence, not on executing any of the theme's own PHP, so — like cause 1 — no target-theme code runs and the previously active theme remains active and undisturbed. This is the entry point for a broken parent/child-theme relationship specifically as it blocks a *switch attempt*; it is not the same condition as an *already-active* theme's parent directory being removed after the fact, which is excluded (see Scope, below).
3. **`after_switch_theme` callback failure** — both prior checks passed, and WordPress proceeds to commit the switch by updating the `template`/`stylesheet` options, then fires the `after_switch_theme` action, which the target theme (commonly from its own `functions.php`) may have registered a callback against to perform its own setup (seeding default widget areas, setting default theme mods, creating a used-for-the-first-time notice, and similar). That callback either fails unexpectedly (throws, fatals, or leaves setup logic incomplete) or, less commonly, is simply absent while the theme's own broader setup code elsewhere in `functions.php` fails during this same request. Because the options were already updated before this point, the target theme is now the site's genuinely active theme regardless of this cause's own outcome — the defining characteristic that separates this cause from causes 1 and 2, and from every comparable cause in `WP-ERROR-031`.

It is distinct from:

- **`WP-ERROR-013` — WordPress Bootstrap PHP Fatal Error**: owns fatal errors occurring during the bootstrap sequence of an ordinary request, including an *already-active* theme's own `functions.php`, once that theme has already been switched to successfully or was already active before this entry's own condition could apply. See the boundary statement and its explicit downstream exception above.
- **`WP-ERROR-014` — Required PHP Extension Missing**: where diagnosis (Section 11) confirms cause 3 traces to a genuinely missing PHP extension the target theme's own code requires, that underlying condition is `WP-ERROR-014`'s own territory. This entry owns the observable fact that the switch failed and diagnoses which of the three mechanisms produced that failure; it hands off to `WP-ERROR-014` for the extension-availability root cause and its own recovery once confirmed, the same escalation pattern `WP-ERROR-031` Section 6 already establishes for the plugin-activation case.
- **`WP-ERROR-015` — Unsupported PHP Version**: where diagnosis confirms cause 1 is specifically a `Requires PHP` mismatch, or cause 3 traces to PHP-version-specific behavior the target theme's own code does not accommodate, the underlying PHP-version condition and its own recovery are `WP-ERROR-015`'s own territory. This entry owns the observable fact that the switch failed and identifies which requirement or mechanism was responsible; it hands off to `WP-ERROR-015` once a genuine version mismatch is confirmed as the specific cause.
- **Theme update failures**: a theme that is updated while inactive, or updated while active without a fresh switch event occurring, is a distinct lifecycle stage this taxonomy assigns to a separate entry (`WP-ERROR-040`), not this one. This entry presumes a switch was deliberately attempted; it does not cover any failure reachable without one.
- **A specific theme's own template-rendering or business-logic defect surfacing during ordinary, post-switch request handling** (Theme category, per `SF-TAXONOMY-008` Section 2): this entry owns only the switching transition itself, including its own `after_switch_theme` callback. A theme that switches successfully (including a callback that completes without error) and then renders incorrectly during normal use is outside this entry's own scope entirely.
- **An already-active theme becoming broken through changes made outside any switch attempt** (for example, an administrator deleting a child theme's parent directory via FTP while that child theme remains the site's active theme) — this entry's own broken-theme detection (cause 2) is evaluated specifically at the moment a switch *to* that theme is attempted; a theme that was already active when it became broken did not pass through this entry's own mechanism at all. This is a genuinely undecided gap this taxonomy disclosed rather than silently assigning here (`SF-TAXONOMY-008` Section 2's own final exclusion bullet); it is not this entry's condition.

---

# 7. Scope

**Covered:** A verified condition in which a deliberate attempt to switch the site's active theme to a specific target theme does not complete correctly, where the verified cause is WordPress's own requirement-gate refusal, WordPress's own broken-theme detection for the target theme specifically at the moment of the switch attempt, or a failure within the target theme's own `after_switch_theme` callback.

**Excluded:**

- PHP fatal errors occurring during the bootstrap sequence of an ordinary, non-switch request, once a theme is already active — including the downstream-symptom exception documented in Section 6 (`WP-ERROR-013`).
- A missing PHP extension, independent of the switching mechanism's own behavior in surfacing it (`WP-ERROR-014`).
- An unsupported or incompatible PHP runtime version, independent of the switching mechanism's own behavior in surfacing it (`WP-ERROR-015`).
- Theme update failures, and any failure occurring to an already-active theme during or after an update with no switch event involved (`WP-ERROR-040`).
- A specific theme's own template-rendering or business-logic defect during ordinary, post-switch request handling.
- An already-active theme becoming broken through changes made outside any switch attempt.
- A theme file that cannot be read at all due to a filesystem permission, existence, or corruption condition affecting a file other than `style.css`'s own presence check, as opposed to a failure produced by successfully reading and executing the theme's content.

---

# 8. WordPress Components

Listed as components commonly involved, not as a claim that every installation exercises every one of them:

- The Appearance → Themes admin screen and its own "Activate" action, the most common interactive entry point, reaching WordPress core's `switch_theme()` function.
- The Customizer's own "Activate & Publish" action, a second interactive entry point reaching the same underlying `switch_theme()` mechanism after an optional live-preview step.
- WP-CLI's `wp theme activate` command, which reaches the same underlying mechanism through a distinct, non-interactive entry point.
- Theme header fields (`Requires PHP`, `Requires at least`, and, since WordPress 6.5, `Requires Plugins`) that a theme's own `style.css` declares in its top-of-file comment block, which WordPress reads and evaluates before attempting a switch.
- `WP_Theme::errors()`, WordPress's own theme-integrity check, which detects a missing or unreadable `style.css`, or, for a child theme, a `Template` header naming a parent theme directory that does not exist.
- WordPress core's `switch_theme()` function, which updates the `template` and `stylesheet` options and then fires the `after_switch_theme` action — in that order, with no pre-flight sandboxed test of the target theme's own code preceding the option update.
- The `after_switch_theme` action hook, which a theme's own `functions.php` commonly registers a callback against (via `add_action()`) to perform first-activation setup such as default widget placement or default theme-mod values.

---

# 9. Typical Symptoms

- WordPress presenting a clear, WordPress-generated notice that the theme could not be activated because a PHP version, WordPress version, or required-plugin dependency is unmet, with the previously active theme remaining active.
- The "Activate" link for a specific theme appearing unavailable, or accompanied by "Broken Theme" or similar messaging, on the Appearance → Themes screen.
- A WP-CLI `wp theme activate` command exiting with a non-zero status and an error message, rather than the target theme appearing as the active theme in `wp theme list` afterward.
- A theme appearing to switch successfully (the Themes screen shows it as active, `wp theme list` confirms it), but the site immediately or shortly afterward produces a PHP fatal error on subsequent page loads — the observable signature of a cause-3 `after_switch_theme` failure whose own defect is severe enough to be fatal once running as the site's normal bootstrap-sequence code.
- A theme switching successfully with no fatal error, but leaving evidence of incomplete first-activation setup — a missing default widget placement, an absent expected notice, or default theme-mod values that were never applied.
- A child theme's "Activate" action refusing to proceed, or an installed child theme not appearing as expected, where its declared parent theme is not separately installed.

---

# 10. Common Causes

Causes are grouped by category. Inclusion in this list identifies a category as plausible; it does not assert that any specific one is present without diagnostic confirmation.

- The target theme declares a `Requires PHP` version higher than the environment's running PHP version.
- The target theme declares a `Requires at least` WordPress version higher than the site's installed WordPress version.
- The target theme declares a `Requires Plugins` dependency on a plugin that is not installed or not active.
- The target theme's own `style.css` file is missing, unreadable, or was never actually deployed to the expected directory.
- The target theme is a child theme whose `Template` header names a parent theme that was never installed, was removed, or is misspelled relative to the parent's actual directory name.
- The target theme's own `after_switch_theme` callback references a PHP extension, function, or class the running environment does not actually provide.
- The target theme's own `after_switch_theme` callback, or other unconditional top-level code in its `functions.php`, contains a genuine defect (a syntax error introduced by an incomplete deployment, a type error, an unhandled exception) unrelated to any environment-requirement gap.
- A partially or incorrectly deployed theme (an incomplete upload, a failed extraction, a version-control merge artifact) leaves the theme's own code syntactically or logically broken at the moment a switch to it is attempted.
- The `after_switch_theme` callback attempts an operation (writing a default option, registering a scheduled event) that fails partway for a reason belonging to another category once diagnosed.

---

# 11. Diagnosis

Verify the following, starting from the least invasive observation and narrowing only once the general shape of the failure is established:

1. **Confirm the switch actually failed to produce the intended, stable outcome.** Check the site's actual active theme directly (Appearance → Themes, or `wp theme list` via WP-CLI) rather than inferring failure from a symptom alone; a theme that is now active but rendering incorrectly is not this entry's condition (see Section 6). Note that, uniquely among this entry's three causes, cause 3 can present as "the theme shows as active" *and* a failure symptom simultaneously — confirming the current active theme does not, by itself, rule out cause 3.
2. **Capture the exact message WordPress itself presented** at the moment of the failed attempt — the specific wording distinguishes a requirement-mismatch notice (and which requirement it names), a broken-theme notice, and a WP-CLI error message from one another, and each points toward a different one of the three causes in Section 6.
3. **Determine which of the three points in Section 6 the failure occurred at.** Where the site's active theme is unchanged from before the attempt, the failure is cause 1 or cause 2 (no code executed, nothing was committed). Where the site's active theme *is* now the target theme despite an observed failure symptom, the failure is cause 3 — the switch itself succeeded mechanically; the target theme's own setup logic is where the failure occurred.
4. **Only once the general point of failure is established, investigate the specific mechanism responsible:**
   - Where a requirement-gate refusal (cause 1) is confirmed, identify which specific requirement — PHP version, WordPress version, or a named plugin dependency — is unmet, and evaluate against `WP-ERROR-015` (PHP version) or resolve the missing plugin dependency directly.
   - Where a broken-theme detection (cause 2) is confirmed, verify whether `style.css` is genuinely missing or unreadable (a deployment or filesystem-level gap) or, for a child theme, whether the declared parent is actually installed under the exact directory name the `Template` header names.
   - Where an `after_switch_theme` callback failure (cause 3) is confirmed, capture the exact PHP error class, message, file, and line number from the relevant log if a fatal error resulted, and evaluate whether the referenced symbol belongs to a PHP extension (`WP-ERROR-014`) or reflects a PHP-version-specific behavior change (`WP-ERROR-015`) before concluding the defect is in the theme's own code independent of the environment. Where the resulting condition is a bootstrap-sequence fatal error on a subsequent request, trace it back to this specific switch attempt before treating it as an independent `WP-ERROR-013` condition, per Section 6's own downstream exception.
5. Preserve the exact notice or error text, the theme's version, and the environment's current PHP/WordPress version before making any change.
6. Where the same theme switches successfully in one environment but not another, explicitly compare the two environments' PHP versions, WordPress versions, and active-plugin sets rather than assuming the theme's own code is universally at fault.
7. Where incomplete first-activation setup from a failed `after_switch_theme` callback is suspected, inventory what the theme's own documented setup was expected to perform and verify each independently, since a partially-completed switch can leave inconsistent state that a mere retry does not resolve on its own.

---

# 12. Recovery Procedure

Recovery shall target the specific, verified cause identified in Diagnosis (Section 11), not merely retry the switch without addressing it.

Permitted recovery categories, depending on the verified cause, include:

- Where a requirement-gate refusal is confirmed, satisfying the actual unmet requirement — updating the running PHP version (per `WP-ERROR-015`'s own recovery procedure), updating WordPress core, or installing and activating a required dependency plugin — rather than attempting to bypass WordPress's own requirement check.
- Where a broken-theme detection traces to a missing or misconfigured parent theme, installing the correctly-named parent theme, or correcting the child theme's own `Template` header to match the parent's actual directory name.
- Where a broken-theme detection traces to a missing or corrupted `style.css`, re-deploying the theme's own files from a known-good source.
- Where an `after_switch_theme` callback failure traces to a missing PHP extension, resolving that per `WP-ERROR-014`'s own recovery procedure.
- Where an `after_switch_theme` callback failure traces to a genuine defect in the theme's own code, correcting or re-deploying the theme's own file from a known-good source. Because the theme option is already committed at this point, correcting the code (rather than merely reverting the active theme) is required to fully resolve the condition, even where an immediate mitigation (switching back to a previously working theme) is applied first to restore normal service.
- Where an `after_switch_theme` callback failed partway through its own setup, either allowing the theme's own retry-safe setup logic to complete cleanly on a subsequent, corrected switch (where the theme's own code is idempotent), or manually completing the partial setup, depending on what the specific theme's own documented behavior supports.

Where cause 3 has produced an active, fatally-broken theme, the immediate mitigation of switching back to a previously working theme (via WP-CLI, where `wp-admin` itself is inaccessible due to the fatal error) is an acceptable first recovery step to restore service, but does not by itself constitute complete recovery: the underlying defect in the target theme's own code shall still be identified and corrected before that theme is switched to again.

Recovery shall not suppress the failure by disabling error display or logging, and shall not force a switch past a requirement-gate refusal or broken-theme detection by editing WordPress core or bypassing the check, since doing so removes a protection WordPress itself is deliberately providing.

---

# 13. Validation

Recovery is successful when:

- The intended theme switches without producing the original requirement-mismatch notice, broken-theme notice, or `after_switch_theme` failure.
- The target theme's own first-activation setup (default widget placement, default theme-mod values, or any other documented `after_switch_theme` behavior specific to that theme) is confirmed complete, not merely that the theme now shows as active.
- Where the cause was a requirement-gate refusal, the corrected requirement (PHP version, WordPress version, or dependency plugin) is confirmed independently, not merely inferred from the switch now succeeding.
- Where the cause was a broken-theme detection involving a parent/child relationship, both the parent and child themes are confirmed present and correctly matched by directory name.
- No subsequent request produces a bootstrap-sequence fatal error attributable to the newly active theme.
- No equivalent switch failure recurs on a fresh attempt.

---

# 14. Prevention

- Declare accurate `Requires PHP`, `Requires at least`, and `Requires Plugins` header values for any theme under direct site-owner control, so WordPress's own requirement gate can refuse a switch cleanly rather than allowing a defect to surface only after the option change has already committed.
- Test a theme switch in a staging environment that matches production's PHP version, WordPress version, and active-plugin set before switching in production.
- Prefer the Customizer's own "Activate & Publish" flow, which allows visually confirming the target theme renders correctly in a live preview before the switch is actually committed, over the plain Appearance → Themes "Activate" action, which commits immediately with no preview step.
- For a child theme, verify the declared parent theme is installed under the exact directory name the child's own `Template` header expects before attempting the switch, rather than discovering the mismatch only when the switch is refused.
- Where a theme's own `after_switch_theme` callback performs setup that is not naturally idempotent, verify it handles being invoked again safely (for a corrected, retried switch) before relying on it in production.

---

# 15. Security Considerations

- A broken-theme or requirement-mismatch notice may reveal installed theme names, versions, or partial file paths; this is ordinarily benign but should not be treated as a substitute for restricting `wp-admin` access to authorized administrators.
- Do not bypass WordPress's own requirement gate or broken-theme detection as a troubleshooting shortcut; both exist specifically to prevent an incompatible or broken theme from becoming the site's active theme.
- Where a switch is attempted via WP-CLI in an automated deployment pipeline, ensure switch failures cause the pipeline itself to fail visibly rather than being silently ignored, since a theme switch that fails at cause 3 can leave the site running a genuinely broken active theme — including, at its worst, a fatal error on the very next request — with no administrator having yet observed it.
- Verify the source and integrity of a theme's own files before switching to it where the theme was obtained from outside the official WordPress.org repository, since this entry's own diagnostic procedure does not itself establish that a theme's code is trustworthy, only that its switch mechanically succeeded or failed.

---

# 16. Related Errors

The following are cited as they exist in this repository, or as conceptual distinctions where noted.

1. [WP-ERROR-013 — WordPress Bootstrap PHP Fatal Error](WP-ERROR-013-WORDPRESS-BOOTSTRAP-PHP-FATAL-ERROR.md) — exists in this repository; see Section 6 (Distinction) above for why this entry's own condition, occurring only at a deliberate switch attempt, falls outside that entry's own scope, and for the explicit downstream-symptom exception this entry documents for a subsequent request's own bootstrap fatal error.
2. [WP-ERROR-014 — Required PHP Extension Missing](WP-ERROR-014-REQUIRED-PHP-EXTENSION-MISSING.md) — exists in this repository; see Section 6 (Distinction) above for the diagnose-then-hand-off relationship.
3. [WP-ERROR-015 — Unsupported PHP Version](WP-ERROR-015-UNSUPPORTED-PHP-VERSION.md) — exists in this repository; see Section 6 (Distinction) above for the diagnose-then-hand-off relationship.
4. [WP-ERROR-031 — WordPress Plugin Activation Failure](WP-ERROR-031-PLUGIN-ACTIVATION-FAILURE.md) — exists in this repository; the direct structural parallel this entry mirrors, with the important divergence documented in Section 5 and Section 6 that theme switching has no equivalent of the sandboxed pre-flight check that mechanism performs.
5. [WP-ERROR-032 — WordPress Plugin Update Failure](WP-ERROR-032-PLUGIN-UPDATE-FAILURE.md) — exists in this repository; see Section 6 (Distinction) above for the shared downstream-symptom resolution model this entry reuses for its own relationship with `WP-ERROR-013`.
6. WP-ERROR-040 — WordPress Theme Update Failure — conceptual reference; planned per `SF-TAXONOMY-008` Section 3, no corresponding document currently exists in this repository; no link is provided.

---

# 17. Notes

This entry documents the general, verified observable condition of a theme switch failing to complete correctly, distinguishing the three mechanically distinct points — requirement gate, broken-theme detection, and `after_switch_theme` callback failure — at which that failure can occur, and explicitly documenting that the third point carries a materially different, more severe risk profile than the first two because WordPress commits the option change before that specific hook fires. It is the first entry in the Theme category, drafted directly from `SF-TAXONOMY-008`'s own declared scope.

Consistent with the single-responsibility principle in **SF-SPEC-001** Section 4.3, this entry does not claim ownership of the underlying PHP-extension or PHP-version conditions its own causes may trace to (`WP-ERROR-014`/`015`), nor of a specific theme's own template-rendering or business-logic defect once a switch has already succeeded.

This entry's relationship to a `Production Ready` designation is established via `SF-REVIEW-115` (Class A author review) and `SF-REVIEW-116` (Class B independent review) per **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.
