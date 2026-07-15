# WP-ERROR-042 — WP-CLI Multisite Site Context Resolution Failure

---

# 1. Knowledge Entry

WP-CLI Multisite Site Context Resolution Failure

---

# 2. Metadata

* **Error ID:** `WP-ERROR-042`
* **Title:** WP-CLI Multisite Site Context Resolution Failure
* **Category:** CLI
* **Severity:** Critical
* **Recovery Priority:** Immediate
* **Status:** Production Ready
* **Version:** 1.0

---

# 3. Summary

WP-CLI, invoked against a WordPress Multisite installation, fails to correctly establish which specific site a command should target — because WP-CLI has no HTTP Host header from which to derive site context the way a real request does, and instead shall be told explicitly via `--url` (or an equivalent configured default). This failure takes one of two forms: WP-CLI errors outright when no site can be resolved at all, or, more dangerously, WP-CLI silently resolves to and executes the command against an unintended site — commonly the network's own primary site — with no error at all.

---

# 4. Primary Failure Mode

An operator or automated script invokes a `wp` subcommand against an already-correctly-located Multisite installation (`WP-ERROR-041`'s own condition does not apply) without supplying a `--url` value that unambiguously and correctly identifies the intended target site. WP-CLI's own site-resolution logic — the mechanism substituting for the Host-header-based resolution a genuine HTTP request receives automatically — either finds no matching site at all, in which case WP-CLI errors visibly and no command executes, or resolves to a specific site that is not the one the operator actually intended, most commonly the network's own primary site (WP-CLI's own default when no `--url` is supplied at all), in which case the invoked command executes completely normally, with no error, against the wrong site.

---

# 5. Severity

This entry is classified **Critical**, with impact ranging sharply depending on which of the two manifestations occurs:

- Where WP-CLI fails to resolve any site at all and errors outright, the impact is narrow and comparable to `WP-ERROR-041`'s own Low-severity profile: no command executes, no site is touched, and the operator receives a clear signal that something is wrong.
- Where WP-CLI instead **silently resolves to the wrong site**, the impact can be severe: the invoked command executes completely normally, with no error of any kind, against a live site the operator never intended to target. For a read-only or low-risk command this may go unnoticed with little consequence, but for a destructive or state-changing command — deleting content, deactivating a security-relevant plugin, updating a critical option, resetting user data — this manifestation can produce genuine, silent, hard-to-detect data loss or configuration corruption on an unintended, live, production site, with no error message at any point to prompt investigation until the consequence is separately discovered.
- This entry remains classified at the level of its most severe possible manifestation, consistent with the range-based Critical classification used elsewhere in this catalog (`WP-ERROR-021`, `024`–`030`, `032`–`033`, `035`, `038`–`040`), and is a deliberate contrast with its own sibling entry `WP-ERROR-041`: both concern WP-CLI's own inability to establish correct context, but only this entry's own silent-misresolution manifestation carries a plausible path to genuine site-facing harm, which `WP-ERROR-041`'s own condition — WP-CLI never launching WordPress at all — categorically cannot reach.

---

# 6. Distinction

This entry applies only when verified evidence establishes that WP-CLI, operating against an already-correctly-located Multisite installation, either failed to resolve a target site at all or resolved to a site other than the one genuinely intended.

**This entry presumes `WP-ERROR-041`'s own condition does not apply.** WP-CLI shall have already successfully located the WordPress installation itself; this entry's own condition is a distinct, later step — establishing *which site within* that installation — that is only reachable once installation discovery has already succeeded, and that has no meaning at all for a single-site (non-Multisite) installation, which has no comparable site-targeting step to fail.

**This entry is not WordPress Multisite's own site-resolution mechanism failing for a genuine HTTP request.** Where an actual visitor's request, carrying a real Host header, resolves to the wrong site or fails to resolve at all — due to a domain-mapping defect, a `sunrise.php` misconfiguration, or corrupted `wp_blogs`/`wp_site` table data — that is a defect in Multisite's own resolution mechanism itself, the future `Multisite` category's own territory once a taxonomy for it exists, per `SF-TAXONOMY-009` Section 2. This entry owns a narrower, WP-CLI-specific condition: WP-CLI's own failure to *supply* a valid site-targeting input in the first place, since it has no Host header to derive one from automatically. Where diagnosis (Section 11) traces a WP-CLI site-targeting failure back to genuinely corrupted Multisite data rather than a missing or incorrect `--url`, that underlying condition becomes the future `Multisite` category's own territory once it exists, not this entry's to resolve.

**This entry is not the invoked command's own failure, once the correct site was actually resolved.** A `wp plugin update` command that correctly targets the intended site but then fails for reasons unrelated to site targeting (a corrupt package, a permission constraint) is `WP-ERROR-040`'s own condition, not this entry's — this entry owns only the site-targeting step itself, not anything that happens after it succeeds or fails on its own terms.

**The two manifestations (Section 5) are diagnostically distinct and shall not be conflated:** a visible resolution failure is a clear, immediate signal requiring only a corrected `--url`; a silent wrong-site resolution requires first *discovering* that the wrong site was targeted at all — commonly only after observing an unexpected effect — before any correction, including possible reversal of an already-executed command's own effect, can begin.

---

# 7. Scope

**Covered:** A verified condition in which WP-CLI, operating against an already-correctly-located Multisite installation, fails to resolve a target site at all, or resolves to a site other than the one the operator or script genuinely intended, due to a missing, incorrect, or ambiguous `--url` (or equivalent configured default).

**Excluded:**

- WP-CLI's own failure to locate the WordPress installation itself (`WP-ERROR-041`).
- WordPress Multisite's own site-resolution mechanism failing for a genuine HTTP request, independent of any WP-CLI invocation.
- The invoked command's own failure for reasons unrelated to site targeting, once the correct site was actually resolved (that command's own owning entry).
- Any condition on a single-site (non-Multisite) WordPress installation, which has no comparable site-targeting step at all.
- Genuinely corrupted Multisite site-registration data (`wp_blogs`/`wp_site`) once confirmed as the underlying cause rather than a WP-CLI-supplied input problem — the future `Multisite` category's own territory.

---

# 8. WordPress Components

Listed as components commonly involved, not as a claim that every installation exercises every one of them:

- The `--url` global WP-CLI flag, the primary mechanism for supplying the site-targeting input a real HTTP request's Host header would otherwise provide.
- `.wp-cli.yml` (or `wp-cli.local.yml`), which can declare a default `url` value applied when `--url` is not explicitly passed on the command line.
- WordPress Multisite's own underlying site-resolution logic, which WP-CLI's own resolved `--url` value is ultimately fed into to establish site context, the same logic a real HTTP request's Host header drives.
- The `MULTISITE` constant and related Multisite bootstrap logic, which WP-CLI itself checks to determine whether site-targeting even applies to the installation in question.
- `wp site list`, WP-CLI's own diagnostic command for enumerating every registered site and its exact, canonical URL.

---

# 9. Typical Symptoms

- WP-CLI printing an explicit error (for example, "Error: Site 'example.com' not found.") when a supplied `--url` matches no registered site, and no command executing.
- A command completing with no error, but its effects later observed on the network's own primary site rather than the intended subsite — content, options, or plugin state changed somewhere the operator did not intend.
- A bulk or apparently network-wide-sounding operation only affecting a single, unintended site, or an operation intended for a single site instead affecting the network's primary site.
- Confusion following a script or loop intended to iterate across multiple sites, where some or all iterations silently operated against the same (incorrect) site rather than the distinct intended targets.
- No WordPress-level error or log entry corresponding to the mistargeting itself, since from WordPress's own perspective a completely ordinary, successful request was served — just to the wrong site.

---

# 10. Common Causes

Causes are grouped by category. Inclusion in this list identifies a category as plausible; it does not assert that any specific cause is present without diagnostic confirmation.

- No `--url` supplied at all on a Multisite installation, causing WP-CLI to default to the network's own primary site rather than the operator's actual intended target.
- A supplied `--url` value that does not precisely match any registered site's canonical URL — a trailing-slash mismatch, an `http` versus `https` scheme mismatch, or a domain simply not present in the network's own site registration.
- A stale or incorrect `.wp-cli.yml` default `url` value, commonly left over from having been copied from a different environment (staging, a different site within the same network) without updating it for the current context.
- Confusing subdomain-based and subdirectory-based Multisite addressing conventions when constructing a `--url` value by hand.
- An automation script or loop intended to iterate across multiple sites, where a bug in the loop's own variable handling causes the same (incorrect) `--url` to be reused across iterations that were each meant to target a distinct site.

---

# 11. Diagnosis

Verify the following, starting from the least invasive observation and narrowing only once the general shape of the failure is established:

1. **Confirm the installation is genuinely Multisite** (the `MULTISITE` constant, or `wp site list` returning more than one entry); this entry's own condition does not apply to a single-site installation.
2. **Confirm the exact `--url` value (if any) actually supplied for the specific invocation in question**, including any inherited default from a `.wp-cli.yml`.
3. **Run `wp site list` to enumerate every registered site and its exact, canonical URL**, and compare the supplied (or absent) `--url` against that list precisely — a scheme or trailing-slash mismatch that looks trivial to a human can cause a genuine resolution failure.
4. **Where a command appeared to complete successfully but its effects seem wrong, verify after the fact which site was actually targeted** — for example, via `wp option get siteurl --url=<the value that was used>` — rather than assuming the intended site was the one actually affected.
5. **Where an automation script or loop is implicated, audit its own `--url` variable handling directly**, checking specifically for a stale value being reused across iterations meant to target distinct sites.
6. Preserve the exact command invoked, the `--url` value (or its absence) at the time, and the specific site actually affected before making any corrective change, particularly where a destructive command is suspected to have executed against the wrong site.

---

# 12. Recovery Procedure

Recovery shall target the specific, verified cause identified in Diagnosis (Section 11), and shall prioritize assessing and, where necessary, reversing any effect already produced against an unintended site before retrying against the correct one.

Permitted recovery categories, depending on the verified cause, include:

- Re-invoking the intended command with the correct, exact `--url` matching a registered site, confirmed via `wp site list`.
- Correcting a stale or incorrect `.wp-cli.yml` default `url` value.
- Correcting an automation script's own `--url` variable handling where a loop bug caused a value to be reused incorrectly across iterations.
- **Where a command has already executed against an unintended site**, first fully assessing the actual effect produced there — what specifically changed, and whether it is reversible — before determining whether restoration from backup, a targeted manual correction, or another recovery path is appropriate; a silently-wrong-site-targeted destructive command may not have a clean, mechanical undo, and recovery here can genuinely require the same investigation and restoration discipline this catalog applies to any other unintended data-loss condition.

Recovery shall not proceed to re-run the originally intended command against the correct site before first confirming whether the unintended site's own state requires separate correction, since doing so risks leaving an unaddressed side effect in place indefinitely.

---

# 13. Validation

Recovery is successful when:

- The intended command, re-run with the corrected `--url`, is independently confirmed (for example, via `wp option get siteurl`) to have targeted the correct, intended site.
- The unintended site's own state, where a prior invocation affected it, is confirmed either to require no correction or to have been fully and correctly restored.
- For an automation script or loop, the corrected `--url` handling is verified to resolve correctly across every intended target site, not only the specific instance originally investigated.
- No further command in the same session or pipeline continues to rely on an unconfirmed or default site-targeting assumption.

---

# 14. Prevention

- Always supply an explicit, exact `--url` for any WP-CLI command run against a Multisite installation; never rely on WP-CLI's own default (the network's primary site) as an implicit target, even when that happens to be the intended site, since an implicit default provides no protection against a future invocation where it is not.
- Maintain a canonical, current reference of registered site URLs (via `wp site list`) when writing or reviewing any script that targets specific sites.
- Test a destructive or state-changing command against a non-production or staging Multisite network first, particularly one invoked via a new or recently modified automation script.
- For an automation script or loop iterating across multiple sites, explicitly log the resolved `--url` for each iteration as it executes, so a misresolution is visible in the script's own output rather than silent.
- Avoid copying a `.wp-cli.yml` file between environments or sites without explicitly reviewing and updating any default `url` value it contains.

---

# 15. Security Considerations

- A silently-wrong-site-targeted destructive command — deleting content, deactivating a security-relevant plugin, altering credentials or capabilities — on a live, unintended site is itself a genuine availability and security concern, not merely an operational inconvenience; treat discovery of this entry's own silent-misresolution manifestation with the same urgency as any other unintended data-modification incident.
- In an audit-sensitive Multisite environment, preserve logs of the exact `--url` value used for every WP-CLI operation, since this entry's own condition can otherwise leave no other trace distinguishing an intended operation from a mistargeted one.
- Do not respond to a resolution failure by loosening Multisite's own site-registration validation as a troubleshooting shortcut; doing so increases, rather than reduces, the risk of a future silent mistargeting.

---

# 16. Related Errors

The following are cited as they exist in this repository, or as conceptual distinctions where noted.

1. [WP-ERROR-041 — WP-CLI Cannot Locate a WordPress Installation](WP-ERROR-041-WP-CLI-CANNOT-LOCATE-WORDPRESS-INSTALLATION.md) — exists in this repository; see Section 6 (Distinction) above for how the two lifecycle stages remain independent, and for the sharp severity contrast between them.

---

# 17. Notes

This entry documents the general, verified observable condition of WP-CLI failing to correctly establish site context on a Multisite installation, distinguishing its two structurally different manifestations — a visible resolution failure, comparable in impact to `WP-ERROR-041`'s own condition, and a silent wrong-site resolution, which carries this catalog's usual range-based Critical classification's full weight. It is the second and final planned entry in the CLI category, per `SF-TAXONOMY-009` Section 3.

Consistent with the single-responsibility principle in **SF-SPEC-001** Section 4.3, this entry does not claim ownership of WordPress Multisite's own site-resolution mechanism as it would apply to a genuine HTTP request, reserving that territory for the future `Multisite` category once a taxonomy for it exists.

This entry's relationship to a `Production Ready` designation is established via `SF-REVIEW-124` (Class A author review) and `SF-REVIEW-125` (Class B independent review) per **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.
