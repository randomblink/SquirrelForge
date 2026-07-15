# WP-ERROR-046 — WordPress Local Mail Transport Failure

---

# 1. Knowledge Entry

WordPress Local Mail Transport Failure

---

# 2. Metadata

* **Error ID:** `WP-ERROR-046`
* **Title:** WordPress Local Mail Transport Failure
* **Category:** Email
* **Severity:** Critical
* **Recovery Priority:** Immediate
* **Status:** Production Ready
* **Version:** 1.0

---

# 3. Summary

`wp_mail()`, using its own default transport (PHP's `mail()` function, since no SMTP has been explicitly configured), fails to result in an actually-delivered message — either because PHP's own `mail()` function itself is unavailable, or because `mail()` reports success, which only confirms the message was handed off to the local system's own Mail Transfer Agent (MTA), not that the MTA ever actually delivered it, while that local MTA silently fails, is unconfigured, or does not exist at all.

---

# 4. Primary Failure Mode

WordPress calls `wp_mail()` with no SMTP transport configured — its own unmodified default state — causing PHPMailer to send via PHP's native `mail()` function rather than its own SMTP client. This entry's own condition occurs at one of two distinguishable points: PHP's `mail()` function itself is unavailable in the running environment and the call fails immediately; or `mail()` executes and returns `true` — a signal that means only that the message was successfully handed off to the local system's own configured MTA, nothing more — while that local MTA is missing, misconfigured, or silently fails to deliver the message onward. The second manifestation is the more consequential of the two specifically because PHP's own `mail()` function provides no mechanism at all for WordPress to learn what happened after that hand-off.

---

# 5. Severity

This entry is classified **Critical**, reasoned from two factors:

- **The range of what WordPress and its plugins depend on email delivery for.** A missed comment-notification email is inconsequential; a missed password-reset email can leave an administrator with no functioning account-recovery path through any other channel, and a missed security-plugin alert email can leave a genuine compromise or critical condition unnoticed. This entry documents the general transport mechanism regardless of which specific message is affected, so its own classification reflects the most severe plausible manifestation, consistent with the range-based Critical classification used elsewhere in this catalog.
- **The specific, well-documented weakness of PHP's own `mail()` success signal.** Unlike most other conditions this catalog documents, where a caller has *some* mechanism to detect failure, PHP's `mail()` function returning `true` provides no assurance whatsoever that a message was ever actually delivered, or even that a local MTA genuinely accepted responsibility for it — WordPress, and by extension a site owner, can remain completely unaware this entry's own condition is present until a recipient separately reports never receiving an expected email.

---

# 6. Distinction

This entry applies only when verified evidence establishes that `wp_mail()` is using its own default, non-SMTP transport, and that the resulting condition traces to that transport's own local `mail()`-function-and-MTA mechanism specifically.

It is distinct from:

- **`WP-ERROR-047` — WordPress SMTP Mail Transport Failure**: owns the condition where `wp_mail()` has been explicitly configured, via a plugin or filter, to use PHPMailer's own direct SMTP client instead. The two transports are mutually exclusive by construction — a given `wp_mail()` call uses exactly one — and this entry presumes SMTP is not configured; where it is, `WP-ERROR-047`'s own mechanism applies instead.
- **The calling code's own trigger logic** (`retrieve_password()`, `wp_new_user_notification()`, a plugin's own decision to send a notification) — `SF-TAXONOMY-012` Section 2's own exclusion; this entry presumes `wp_mail()` was correctly and appropriately called, and owns only what happens once that call attempts delivery via the local transport.
- **A message's own eventual deliverability outcome once a local MTA has genuinely accepted it for further relay** (spam-filtering, a bounce, a reputation-based rejection at the receiving end) — `SF-TAXONOMY-012` Section 2's own explicitly disclosed gap, outside WordPress's own visibility entirely once the local MTA has taken responsibility for the message.
- **PHPMailer's own pre-transport message-composition validation** (rejecting a malformed recipient address before any transport is attempted) — `SF-TAXONOMY-012` Section 4's own explicitly disclosed, currently-unclaimed gap, since such a failure occurs identically regardless of which transport would otherwise have been used.

---

# 7. Scope

**Covered:** A verified condition in which `wp_mail()`, using its own default (non-SMTP) transport, fails to result in an actually-delivered message because PHP's own `mail()` function is unavailable, or because `mail()` reports success while the underlying local MTA fails to deliver the message, is unconfigured, or does not exist.

**Excluded:**

- Any condition where `wp_mail()` is configured to use SMTP transport instead (`WP-ERROR-047`).
- The calling code's own decision to send a specific email in the first place.
- A message's own deliverability outcome once a local MTA has genuinely accepted it for further relay.
- PHPMailer's own pre-transport message-composition validation, occurring before any transport is attempted.

---

# 8. WordPress Components

Listed as components commonly involved, not as a claim that every installation exercises every one of them:

- `wp_mail()` (`wp-includes/pluggable.php`), WordPress's own public email-sending function, which composes a message via the bundled PHPMailer library and delegates to whichever transport is currently configured.
- PHPMailer's own `Mail` transport mode (`isMail()`), its own default when no SMTP configuration is present, which itself calls PHP's native `mail()` function.
- PHP's own `mail()` function and the `sendmail_path` `php.ini` directive governing which local binary it invokes.
- The local Mail Transfer Agent (MTA) — commonly `sendmail`, Postfix, or Exim, or a hosting-provided equivalent — `mail()` hands the message off to, and which is genuinely responsible for any further delivery attempt.
- The `wp_mail_failed` action, which fires with a `WP_Error` when PHPMailer itself detects and reports a failure — notably, this does **not** fire for the specific, common case where `mail()` itself reports success but the local MTA subsequently and silently fails, since PHPMailer has no visibility into anything that happens after `mail()` returns `true`.

---

# 9. Typical Symptoms

- A user or administrator reporting they never received an expected email (a password reset, a notification), with no corresponding error visible anywhere in WordPress itself.
- A PHP warning or notice indicating `mail()` is undefined or disabled, where `display_errors` is enabled and PHP's own `mail()` function is genuinely unavailable in the running environment.
- `wp_mail_failed` firing, where hooked, with a `WP_Error` — specifically for the subset of failures PHPMailer itself is actually able to detect (for example, a malformed message PHP's `mail()` function itself rejects outright), not for the more common "reported success but the MTA silently failed" case.
- Server-level mail logs, where accessible, showing the message was never actually queued or attempted by the local MTA at all, despite PHP's own `mail()` call having reported success.

---

# 10. Common Causes

Causes are grouped by category. Inclusion in this list identifies a category as plausible; it does not assert that any specific cause is present without diagnostic confirmation.

- Many managed or shared hosting environments disable PHP's own `mail()` function entirely, as a common anti-spam or security-hardening policy.
- No local MTA is installed or configured on the server at all — common on modern containerized or cloud-native deployments that do not include a traditional `sendmail`-style MTA by default, unlike a conventional shared-hosting environment.
- A misconfigured `sendmail_path` `php.ini` directive pointing to a nonexistent or broken binary.
- The local MTA rejecting or silently dropping the message due to the server's own missing reverse-DNS (PTR) record, an unauthenticated or unrecognized sending domain (no `SPF`/`DKIM` configured), or spam-filtering at the destination mail server — none of which PHP's own `mail()` function or WordPress itself has any visibility into.
- The local MTA queueing the message indefinitely due to its own misconfiguration or a downstream connectivity issue, never actually attempting delivery.

---

# 11. Diagnosis

Verify the following, starting from the least invasive observation and narrowing only once the general shape of the failure is established:

1. **Confirm SMTP is not configured.** Check for an active SMTP-configuring plugin or any `wp_mail`-related filter that would redirect delivery to PHPMailer's own SMTP client — if SMTP is in fact configured, this is `WP-ERROR-047`'s own condition, not this entry's.
2. **Confirm PHP's own `mail()` function is actually available**, not disabled via `disable_functions` and genuinely present in the current SAPI, rather than assuming it is available because the environment is otherwise functioning normally.
3. **Attempt a direct, minimal PHP `mail()` call outside WordPress entirely** to isolate whether the underlying problem is in PHP's own `mail()` function or MTA hand-off, independent of any WordPress-specific code.
4. **Where server-level access is available, check the local MTA's own logs directly** for the specific message, by timestamp and recipient, to determine whether it was ever actually queued at all, and if so, what happened to it afterward.
5. **Hook `wp_mail_failed` to capture any `WP_Error` PHPMailer itself reports**, while accounting for the fact that this hook will not fire for a scenario where `mail()` itself reported success but the MTA subsequently failed silently.
6. **Confirm the server's own reverse-DNS/PTR record and whether the sending domain has `SPF`/`DKIM` records configured**, since their absence is a common, genuine reason a receiving mail server silently rejects or spam-filters a message even after the local MTA has successfully attempted delivery.

---

# 12. Recovery Procedure

Recovery shall target the specific, verified cause identified in Diagnosis (Section 11).

Permitted recovery categories, depending on the verified cause, include:

- Where PHP's own `mail()` function is disabled at the hosting-platform level and cannot be re-enabled, switching to SMTP transport entirely — the only viable recovery in this specific case, handing off to `WP-ERROR-047`'s own domain once that configuration change is made.
- Where a local MTA is missing or misconfigured and server-level access is available, installing or correctly configuring one, or correcting the `sendmail_path` directive.
- Where the underlying cause is a deliverability issue at the receiving end (missing `SPF`/`DKIM`, no reverse-DNS record), correcting the server's own DNS and mail-authentication configuration.
- Where local-MTA deliverability cannot be reliably corrected (a common outcome on cloud/containerized hosting lacking dedicated mail infrastructure or a stable sending IP reputation), migrating to SMTP through a reputable, authenticated third-party mail provider is frequently the more durable practical resolution, even though it lies outside this entry's own diagnostic scope once that migration is made.

---

# 13. Validation

Recovery is successful when:

- A test email is confirmed not merely as "sent" by WordPress's own reported result, but as actually received at the destination inbox, verified directly rather than inferred from WordPress's own success indication alone.
- The confirmation holds across more than one attempt and, where feasible, more than one destination mail provider, since a single successful delivery does not rule out an intermittent or provider-specific deliverability issue.
- Where the correction involved DNS or mail-authentication changes, those changes are confirmed propagated and effective, not merely applied.

---

# 14. Prevention

- Proactively verify PHP's own `mail()` function is available and a local MTA is properly configured immediately after provisioning any new hosting environment or completing a migration, rather than discovering the gap only when an expected email fails to arrive.
- For any environment where local-transport reliability cannot be confirmed with confidence — particularly cloud-native or containerized hosting without dedicated mail infrastructure — prefer explicit SMTP configuration (`WP-ERROR-047`'s own territory) over relying on this entry's own local-transport default at all.
- Configure `SPF` and `DKIM` records for the sending domain as a standard part of initial site setup, not only after a deliverability problem is first reported.
- Periodically send and independently verify receipt of a test email, rather than assuming ongoing reliability from an initial successful test.

---

# 15. Security Considerations

- An unauthenticated local-MTA hand-off (no `SPF`/`DKIM`, no reverse-DNS record) makes WordPress's own legitimate outbound email indistinguishable from spam to a strict receiving mail server, and the same weak configuration also makes the server a more attractive target for unauthorized mail relay if the site is otherwise compromised — treat mail-authentication configuration as a security-relevant concern, not a purely functional one.
- Do not disable spam- or security-hardening restrictions on PHP's own `mail()` function as a troubleshooting shortcut without understanding why a hosting provider imposed them; migrating to SMTP is almost always the more appropriate response to a restricted local `mail()` function than attempting to circumvent the restriction.

---

# 16. Related Errors

The following are cited as they exist in this repository, or as conceptual distinctions where noted.

1. WP-ERROR-047 — WordPress SMTP Mail Transport Failure — conceptual reference; planned per `SF-TAXONOMY-012` Section 3, no corresponding document currently exists in this repository; no link is provided.

---

# 17. Notes

This entry documents the general, verified observable condition of WordPress's own default, local mail transport failing to result in an actually-delivered message, with particular emphasis on the specific, well-documented weakness of PHP's own `mail()` success signal as the reason this condition can persist entirely unnoticed. It is the first entry in the Email category, drafted directly from `SF-TAXONOMY-012`'s own declared scope.

Consistent with the single-responsibility principle in **SF-SPEC-001** Section 4.3, this entry does not claim ownership of SMTP-based delivery (`WP-ERROR-047`), the calling code's own decision to send a specific email, or a message's own deliverability outcome once a local MTA has genuinely accepted responsibility for it.

This entry's relationship to a `Production Ready` designation is established via `SF-REVIEW-141` (Class A author review) and `SF-REVIEW-142` (Class B independent review) per **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.
