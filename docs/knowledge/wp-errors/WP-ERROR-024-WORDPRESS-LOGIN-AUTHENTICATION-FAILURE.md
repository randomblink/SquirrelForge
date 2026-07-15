# WP-ERROR-024 — WordPress Login Authentication Failure

---

# 1. Knowledge Entry

WordPress Login Authentication Failure

---

# 2. Metadata

* **Error ID:** `WP-ERROR-024`
* **Title:** WordPress Login Authentication Failure
* **Category:** Authentication
* **Severity:** Critical
* **Recovery Priority:** Immediate
* **Status:** Production Ready
* **Version:** 1.0

---

# 3. Summary

A login attempt — submitted via `wp-login.php`, XML-RPC, or a programmatic `wp_signon()` call — is evaluated by WordPress's own core credential-verification pipeline (`wp_authenticate()` and its `authenticate` filter chain) and rejected because the supplied identity and credential do not correspond to a valid, matching user account. This entry ends the moment WordPress decides the user is not authenticated; it does not extend into anything that happens before that decision (a request blocked before reaching the authentication code) or after it (cookie issuance and persistence, capability checks, or request-origin verification).

---

# 4. Primary Failure Mode

A login attempt reaches WordPress's own `wp_authenticate()` pipeline, and that pipeline's default core hooks (`wp_authenticate_username_password`, `wp_authenticate_email_password`, and, on Multisite, `wp_authenticate_spam_check`) determine that no valid user account matches the supplied username or email, or that the supplied password does not match the matched account's stored password hash. `wp_authenticate()` returns a `WP_Error` (or a plugin-added `authenticate` filter callback returns one first in the chain), and the `wp_login_failed` action fires. No authenticated session is established as a result.

---

# 5. Severity

This entry is classified **Critical**, though its actual impact ranges depending on which account is affected:

- Where a single non-administrative user's credentials are affected, the impact is typically narrow — that one user cannot log in while every other account and all public-facing browsing continue to function normally.
- Where every administrative account is affected (for example, a credential-store corruption, a botched bulk password reset, or a hosting migration that silently altered the `wp_users` table), the impact is a full site-management outage: no one can authenticate to `wp-admin` at all, even though public-facing pages may continue to load normally for anonymous visitors.
- This entry remains classified at the level of its most severe possible manifestation, consistent with the range-based Critical classification used elsewhere in this catalog (for example, `WP-ERROR-004`, `005`, `006`, `019`, `020`, and `021`).
- The affected login attempt itself cannot complete at all while the condition persists for that specific credential pair; there is no partial degradation for that one attempt, and no application-level workaround exists short of correcting or resetting the underlying credential.

---

# 6. Distinction

This entry applies only when verified evidence establishes that WordPress's own core authentication pipeline was reached and itself rejected the supplied credentials as not matching a valid account — not that a request was blocked before reaching that pipeline, that credentials were valid but a *later* stage rejected the request, or that the rejection occurred for a reason unrelated to identity verification.

**The failure ends the moment WordPress decides "this user is not authenticated."** Nothing that happens before or after that specific decision belongs to this entry.

It is distinct from:

- **`WP-ERROR-025` — WordPress Authentication Cookie Invalid or Expired**: presumes a user *was previously* successfully authenticated by this entry's own pipeline, and the failure is in a *subsequent* request's own cookie/session validation — not in the original credential check. This entry does not discuss cookies at all, except as context for why a login attempt was necessary in the first place; a login attempt that fails here never reaches cookie issuance, because no cookie is ever set for a rejected credential.
- **`WP-ERROR-026` — WordPress Capability or Role Authorization Denied**: presumes authentication has already *succeeded* — the user's identity was verified — and the failure is a subsequent decision about what that already-identified user is *allowed to do*. Authentication answers "who are you?"; authorization answers "are you allowed to do this?" This entry owns only the former question, and only its negative answer.
- **`WP-ERROR-027` — WordPress Nonce Verification Failure (Non-REST)**: a nonce failure can occur even when the user is fully authenticated, holds a valid session, and possesses the correct capability — nonce verification is a request-origin/freshness check, not an identity check. A user can fail nonce verification without ever having an authentication problem, and this entry's own condition can never be resolved by anything nonce-related, since a login attempt neither carries nor requires a nonce for the credential check itself.
- **`WP-ERROR-022` — WordPress REST API Access Denied**: covers authentication and authorization failures *within a REST API request specifically*, including REST-specific mechanisms (Application Passwords, the `X-WP-Nonce` header) this entry does not cover. A REST request that fails because of invalid Application Password credentials belongs to `WP-ERROR-022`, not here, even though both entries ultimately concern "was this identity accepted."
- **Account lockouts imposed by a security or brute-force-protection plugin** (Security category, once a taxonomy exists for it, or Plugin category for a plugin-specific implementation defect): such a plugin may block a login attempt *before* it reaches `wp_authenticate()` at all (IP-based rate limiting at the HTTP/WAF layer, a CAPTCHA challenge), or it may register its own callback directly on the same `authenticate` filter chain this entry's own pipeline runs, short-circuiting it with a rate-limit or lockout decision before the supplied credential is ever compared. The boundary is therefore not drawn by which mechanism or layer intercepted the request — a lockout plugin can legitimately participate in the same filter chain this entry owns — but by verified cause: a rejection driven by a rate-limit or lockout state independent of the credential's own validity is excluded from this entry, while a rejection driven by the credential itself not matching a valid account is this entry's own condition. See Section 11 (Diagnosis), step 5, for how to determine which applies in a specific case.
- **`WP-ERROR-002` — WordPress Database Authentication Failure**: an unrelated concept sharing only the word "authentication." That entry concerns WordPress's own connection to its database server rejecting the credentials in `wp-config.php` — WordPress authenticating *as a client* to MySQL/MariaDB. This entry concerns a human or programmatic actor authenticating *to* WordPress. If the database itself cannot be queried at all, `wp_authenticate()` cannot complete its own lookup and the condition belongs to the Database category, not here — see Section 7 (Scope).

---

# 7. Scope

**Covered:** A verified condition in which WordPress's own `wp_authenticate()` pipeline is reached, executes its default (or plugin-extended, where the extension is part of the same identity-verification decision rather than a separate gate) credential-checking logic, and rejects the login attempt because the supplied username or email does not match a real account, or the supplied password does not match that account's stored hash. This entry covers every entry point that ultimately invokes this same core pipeline, including `wp-login.php`, XML-RPC (`wp_xmlrpc_server::login()`, and any XML-RPC method requiring authentication), and a plugin's or theme's own programmatic `wp_signon()` call.

**Excluded:**

- A request blocked before WordPress's own authentication code is ever reached (a web application firewall, a security plugin's rate-limiting or IP-blocking feature, a hosting-level rule).
- Cookie issuance, persistence, or subsequent validation after a successful authentication (`WP-ERROR-025`).
- Capability or role checks evaluated after authentication has already succeeded (`WP-ERROR-026`).
- Nonce or request-origin verification, which is independent of identity and can fail or succeed regardless of authentication state (`WP-ERROR-027`).
- Authentication or authorization occurring specifically within a REST API request, including Application Passwords and the `X-WP-Nonce` header (`WP-ERROR-022`).
- A database-layer failure preventing the user-lookup query itself from completing (Database category) — this entry presumes the database is reachable and queryable, and that the lookup itself completed and returned a definitive "no match" or "password mismatch" result.
- A missing PHP extension or unsupported PHP version preventing the authentication code (for example, a password-hashing function) from executing at all (PHP Runtime category) — this entry presumes the PHP runtime is fully capable and that the rejection is a business-logic decision, not a runtime-capability failure.
- Password-reset or lost-password recovery flow failures (`retrieve_password()`, reset-key generation or validation) — a related but distinct mechanism. The reset key's own generation and validation remain uncovered by any entry, per `SF-TAXONOMY-003` Section 5; the reset email's own delivery, once `wp_mail()` is actually called, is [WP-ERROR-046](WP-ERROR-046-LOCAL-MAIL-TRANSPORT-FAILURE.md)'s or [WP-ERROR-047](WP-ERROR-047-SMTP-MAIL-TRANSPORT-FAILURE.md)'s own territory, depending on the configured transport.
- A two-factor-authentication plugin's own additional verification step failing after primary credentials were already accepted (Plugin category).

---

# 8. WordPress Components

Listed as components commonly involved, not as a claim that every installation exercises every one of them:

- `wp_authenticate()` (`wp-includes/user.php`), the core dispatcher that runs the `authenticate` filter chain and returns either a `WP_User` object or a `WP_Error`.
- The default `authenticate` filter callbacks: `wp_authenticate_username_password` and `wp_authenticate_email_password` (both core, priority 20), which perform the actual username/email lookup and `wp_check_password()` hash comparison, and `wp_authenticate_spam_check` (Multisite only, priority 99), which rejects a login for an account flagged as spam.
- `wp_check_password()` and the underlying password-hashing mechanism (WordPress core has used phpass-based hashing historically and PHP's native `password_hash()`/`password_verify()` in more recent core versions), responsible for the actual credential comparison.
- `wp_signon()` (`wp-includes/user.php`), the wrapper `wp-login.php` and many programmatic callers use, which invokes `wp_authenticate()` and, only on success, proceeds to set the authentication cookie — a step this entry does not cover.
- `wp-login.php`, the default interactive login form and its own error-message rendering (`wp_login_failed` action, `login_errors` global).
- `wp_xmlrpc_server::login()` and the broader XML-RPC authentication path (`xmlrpc.php`), which calls the same `wp_authenticate()` pipeline as `wp-login.php` for any XML-RPC method requiring an authenticated user.
- The `wp_login_failed` action hook, fired on every rejected login attempt regardless of entry point, commonly used by logging and security plugins to record failed attempts (recording is in scope; the plugin's own subsequent blocking decision is not, per Section 6).

---

# 9. Typical Symptoms

- `wp-login.php` displays "**Error:** The username *[username]* is not registered on this site." (`WP_Error` code `invalid_username`).
- `wp-login.php` displays "**Error:** The password you entered for the username *[username]* is incorrect." (`WP_Error` code `incorrect_password`), with a "Lost your password?" link.
- When the site or a plugin permits login by email address, "**Error:** Unknown email address. Check again or try your username." (`WP_Error` code `invalid_email`).
- An XML-RPC client receives a fault response corresponding to invalid credentials rather than a successful session token.
- A programmatic `wp_signon()` call returns a `WP_Error` object rather than a `WP_User` object.
- The `wp_login_failed` action fires, and any logging or security plugin observing it records an entry — the presence of such a log entry is itself confirmatory evidence for this condition, distinct from a log entry showing a request never reached this point at all (which would instead indicate the plugin/WAF-blocking exclusion in Section 7).

---

# 10. Common Causes

Causes are grouped by category. Inclusion in this list identifies a category as plausible; it does not assert that any specific cause is present without diagnostic confirmation.

- The submitted username, email, or password contains a genuine typo, or was affected by autocomplete/password-manager entering a stale or wrong-site credential.
- The account genuinely does not exist on this specific site — it was never created, was deleted, or (on Multisite) the user is attempting to log in on the wrong site in the network.
- The user is attempting a password that was valid before a since-completed password reset or forced password change.
- A staging, development, or migrated copy of the site has a different `wp_users`/`wp_usermeta` state than the environment the user believes they are logging into (a common cause after a database migration that did not carry forward the expected user table, or that restored an older backup).
- A bulk user-import, migration, or manual database edit corrupted or mismatched the stored password hash for one or more accounts, causing every subsequent login attempt with the correct, known-good password to fail.
- A plugin's own `authenticate` filter callback — added to the *same* filter chain `wp_authenticate()` runs, as opposed to blocking the request before that chain runs at all — rejects the credential as part of the same identity-verification decision (for example, a plugin enforcing "this account has not verified its email address yet" as an authentication-stage business rule). Whether such a plugin-added condition belongs to this entry or to the Plugin category depends on whether it is genuinely part of the identity-verification decision itself or a separate gate layered in front of it; this entry covers the former, and the boundary should be confirmed against the specific plugin's own documented behavior during diagnosis.
- Case-sensitivity or leading/trailing whitespace handling differences between what the user believes they typed and what was actually submitted, particularly on mobile keyboards or when copy-pasting credentials.
- (Multisite) The account is flagged as spam or otherwise blocked by `wp_authenticate_spam_check`.

---

# 11. Diagnosis

Verify the following:

1. Confirm this is a genuine core-authentication rejection rather than a request that never reached `wp_authenticate()` at all — check for evidence of a WAF, security plugin, or hosting-level block (an HTTP-layer rejection, a CAPTCHA challenge, or a "too many attempts" message) that would indicate the exclusion in Section 7 (Scope) applies instead.
2. Capture the exact error message and `WP_Error` code returned (`invalid_username`, `incorrect_password`, `invalid_email`, or a plugin-specific code from an `authenticate` filter callback).
3. Confirm the account actually exists on the specific site and specific environment (production, staging, or a specific Multisite site) the user believes they are accessing — query `wp_users`/`wp_usermeta` directly if administrative database access is available, rather than relying on the user's own assumption about which environment they are using.
4. Where the error indicates an incorrect password rather than an unrecognized username, confirm whether the password was recently changed, whether a password manager may be supplying a stale credential, and whether the account's stored password hash shows evidence of unexpected modification (an unexpected `user_pass` change timestamp, if audit logging is available).
5. Identify every `authenticate` filter callback registered by active plugins (`grep`/code search for `add_filter( 'authenticate'` across the active plugin set, or use a debugging tool that lists registered filters for a given hook) to determine whether a plugin is participating in the same identity-verification decision this entry covers, or is instead blocking the request at an earlier stage.
6. Where XML-RPC is the entry point, confirm the failure occurs within `wp_xmlrpc_server::login()`'s own call to `wp_authenticate()`, not in a separate XML-RPC-specific access restriction (some security plugins disable XML-RPC entirely, which is a block-before-reaching-authentication condition, not this entry's own condition).
7. On Multisite, check whether the account is flagged as spam or deleted at the network level, which `wp_authenticate_spam_check` will reject independently of whether the credential itself is correct.
8. Preserve relevant evidence — the exact error message, the `wp_login_failed` log entry if one exists, and the timestamp of the attempt — before making any change to the user account or authentication configuration.
9. Where the affected account is the only administrator and no other means of administrative access exists, treat this as the most severe manifestation described in Section 5 and prioritize a database-level or WP-CLI-based credential reset over further remote diagnosis.

```text
# Example only — illustrates listing authenticate-filter callbacks via WP-CLI;
# exact syntax depends on the WP-CLI version and any debugging plugin in use.
wp eval 'var_dump( $GLOBALS["wp_filter"]["authenticate"] ?? null );'
```

---

# 12. Recovery Procedure

Recovery shall target the verified cause of the credential rejection, not merely suppress the error message.

Permitted recovery categories, depending on the verified cause, include:

- Guiding the user to the correct, current credential (confirming the correct username/email and initiating a legitimate password reset through `retrieve_password()` where the credential is genuinely forgotten or stale).
- Correcting a verified data-integrity problem in `wp_users`/`wp_usermeta` (a corrupted password hash, an incorrectly migrated user table) via direct database correction or a WP-CLI user command, performed by an administrator with appropriate access.
- Resetting a user's password directly via WP-CLI or direct database update, where the user cannot complete the standard reset flow and administrative access is available (`wp user update <id> --user_pass=<new-password>`, followed immediately by requiring the user to change it again through the standard flow).
- Removing or correcting a plugin-added `authenticate` filter callback that is incorrectly rejecting valid credentials as part of a misconfigured business rule.
- Correcting a Multisite spam flag on an account that should not have been flagged.
- Escalating to the hosting or platform administrator where database-level access is required and the engineer performing diagnosis does not have it.

This entry does not prescribe disabling password strength requirements, disabling XML-RPC as a blanket security measure unrelated to the specific verified cause, or granting administrative access through any means other than a verified, correct credential or an explicit, deliberate administrative reset.

Recovery shall not suppress or hide failed-login evidence (`wp_login_failed` logging) as a substitute for addressing the underlying cause, and shall not disable WordPress's own credential verification (for example, via a filter that unconditionally returns a valid user regardless of the supplied password) as a workaround for a forgotten credential — that is a distinct and separate security risk, not a legitimate recovery action for this entry's own condition.

---

# 13. Validation

Recovery is successful when:

- The specific user account can complete a login attempt with a known-correct credential through the normal `wp-login.php` (or relevant entry-point) flow, resulting in a `WP_User` object from `wp_authenticate()` rather than a `WP_Error`.
- The `wp_login_failed` action no longer fires for subsequent legitimate attempts using the corrected credential.
- Where a data-integrity correction was made (a repaired password hash, a corrected user record), the correction is confirmed present in `wp_users`/`wp_usermeta` directly, not merely inferred from a single successful login attempt.
- No unrelated account's ability to authenticate was disturbed by the correction (for example, a bulk database correction did not inadvertently alter other users' password hashes).
- Where a plugin-added `authenticate` filter callback was modified or removed, the plugin's own remaining, legitimate functionality (if any) continues to operate correctly.
- No diagnostic or recovery action left a temporary credential, a debugging bypass, or an overly permissive authentication filter in place after validation is complete.

---

# 14. Prevention

- Maintain accurate, environment-specific documentation of which credentials apply to which environment (production, staging, development) to reduce environment-confusion-driven failures.
- Use a password manager configured with correct per-environment/per-site entries to reduce credential-entry errors.
- Audit registered `authenticate` filter callbacks after installing or updating any authentication-related, membership, or access-control plugin, to understand what business rules participate in the core identity-verification decision.
- Maintain at least one administrator account whose credentials are independently verified and recoverable via a channel outside WordPress itself (for example, a documented, securely-stored WP-CLI/database recovery procedure), so a single account's authentication failure never becomes an unrecoverable full-site-management outage.
- Include user-table integrity checks (unexpected password-hash changes, unexpected account creation or deletion) as part of routine site monitoring, particularly after migrations or bulk imports.
- Test authentication explicitly, for at least one representative account, after any user-table migration, restore, or bulk import, rather than assuming success from the migration tool's own completion status.

---

# 15. Security Considerations

- Do not log submitted plaintext passwords, even temporarily, while diagnosing a failed login — capture only the `WP_Error` code, username/email attempted, and timestamp.
- Do not disable WordPress's own password verification, even temporarily, as a diagnostic or recovery shortcut; use a direct, deliberate WP-CLI or database-level password reset instead, which does not weaken the verification logic itself for any other login attempt.
- Repeated failed-login evidence for a specific account or from a specific source, while not itself this entry's own condition to resolve, is a signal worth escalating to whatever brute-force-protection mechanism the site has in place (Security category), since this entry's own diagnostic process may be the first place such a pattern becomes visible.
- Where a password-hash corruption is found to be the verified cause, confirm whether the corruption resulted from an unauthorized modification (a compromise indicator) rather than an ordinary migration or import defect, before concluding the recovery is complete.
- A direct database or WP-CLI password reset shall use a strong, unique temporary credential and require the affected user to change it again through the standard, audited flow, rather than leaving the administrator-set temporary credential in place indefinitely.

---

# 16. Related Errors

The following are cited as they exist in this repository, or as conceptual distinctions where noted.

1. [WP-ERROR-025 — WordPress Authentication Cookie Invalid or Expired](WP-ERROR-025-AUTHENTICATION-COOKIE-INVALID-OR-EXPIRED.md) — exists in this repository (currently `Draft`); see Section 6 (Distinction) above for how the session-layer condition differs from this entry's own identity-verification condition.
2. [WP-ERROR-026 — WordPress Capability or Role Authorization Denied](WP-ERROR-026-CAPABILITY-OR-ROLE-AUTHORIZATION-DENIED.md) — exists in this repository (currently `Draft`); see Section 6 (Distinction) above for the authentication-versus-authorization distinction.
3. [WP-ERROR-027 — WordPress Nonce Verification Failure, Non-REST](WP-ERROR-027-NONCE-VERIFICATION-FAILURE-NON-REST.md) — exists in this repository (currently `Draft`); see Section 6 (Distinction) above for why a nonce failure is independent of authentication state.
4. [WP-ERROR-022 — WordPress REST API Access Denied](WP-ERROR-022-REST-API-ACCESS-DENIED.md) — exists in this repository; see Section 6 (Distinction) above for how REST-specific authentication mechanisms (Application Passwords, `X-WP-Nonce`) differ from this entry's own scope.
5. [WP-ERROR-002 — WordPress Database Authentication Failure](WP-ERROR-002-WORDPRESS-DATABASE-AUTHENTICATION-FAILURE.md) — exists in this repository; see Section 6 (Distinction) above for why the shared word "authentication" does not imply a shared condition.

---

# 17. Notes

This entry documents the general, verified observable condition of WordPress's own core authentication pipeline rejecting a login attempt's credentials. It does not claim that every plugin-added `authenticate` filter callback belongs to this entry's own scope; whether a specific plugin's business rule is part of the identity-verification decision this entry owns, or a separate access-control gate belonging to the Plugin category, is a judgment to be confirmed during diagnosis against that plugin's own documented behavior, consistent with the single-responsibility principle in **SF-SPEC-001** Section 4.3.

This entry is the first of four entries `SF-TAXONOMY-003` plans for the Authentication category, and establishes the baseline "identity established" distinction the other three (`WP-ERROR-025`, `026`, `027`) are each expected to reference explicitly rather than re-deriving independently.

This entry underwent the review sequence required by **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**: an author (Class A) review at `docs/reviews/SF-REVIEW-070-WP-ERROR-024-AUTHOR-REVIEW.md`, which found and corrected one Minor finding, followed by an independent (Class B) review at `docs/reviews/SF-REVIEW-071-WP-ERROR-024-INDEPENDENT-REVIEW.md`, which reached outcome **Approved with Minor Revisions**, corrected an internal-consistency gap between Section 6 and Section 11, and satisfied the Production Ready gate per SF-SPEC-012 Section 12. Its Status was changed to Production Ready on that basis. This document does not itself constitute either review record; see the cited files for full findings, corrections, and gate decisions.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.
