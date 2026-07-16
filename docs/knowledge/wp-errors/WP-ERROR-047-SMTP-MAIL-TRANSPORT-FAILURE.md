# WP-ERROR-047 — WordPress SMTP Mail Transport Failure

---

# 1. Knowledge Entry

WordPress SMTP Mail Transport Failure

---

# 2. Metadata

* **Error ID:** `WP-ERROR-047`
* **Title:** WordPress SMTP Mail Transport Failure
* **Category:** Email
* **Severity:** Critical
* **Recovery Priority:** Immediate
* **Status:** Production Ready
* **Version:** 1.0

---

# 3. Summary

`wp_mail()`, configured via a plugin or filter to use PHPMailer's own direct SMTP client instead of PHP's `mail()` function, fails to establish a connection, negotiate TLS/SSL, authenticate, or complete the SMTP protocol exchange with the configured mail server. Unlike `WP-ERROR-046`'s own local-transport condition, this entry's own condition is, in most of its own causes, reliably reported to WordPress via the `wp_mail_failed` action, since PHPMailer's own SMTP client directly controls and can observe the entire protocol exchange rather than handing the message off to an opaque local mechanism.

---

# 4. Primary Failure Mode

A plugin or filter configures `wp_mail()` to use SMTP transport — a host, port, credential set, and encryption type — causing PHPMailer to use its own direct, socket-based SMTP client rather than PHP's `mail()` function. This entry's own condition occurs when that client fails at one of several distinguishable points: connection establishment to the configured host and port, TLS/SSL negotiation where encryption is configured, authentication against the configured credentials, or the SMTP protocol exchange itself once authenticated (for example, the server rejecting the message due to a sending-rate limit or a policy restriction).

---

# 5. Severity

This entry is classified **Critical**, for the same range-of-impact reasoning `WP-ERROR-046` Section 5 already establishes for this category — a missed email can range from inconsequential to genuinely severe depending on what it was — but with a materially different characteristic regarding visibility, worth reasoning explicitly rather than inheriting `WP-ERROR-046`'s own invisibility argument unchanged:

- Unlike `WP-ERROR-046`'s own local-transport condition, where PHP's `mail()` function provides no mechanism to learn what happened after it reports success, PHPMailer's own SMTP client directly controls the entire protocol exchange and can observe exactly where and why it failed. In the large majority of this entry's own causes, `wp_mail_failed` fires reliably with a specific, diagnosable `WP_Error`.
- This entry's own worst-case impact is nonetheless comparable to `WP-ERROR-046`'s own: SMTP transport is frequently adopted specifically *because* local transport proved unreliable, meaning a site experiencing this entry's own condition may have no functioning email capability at all, with no automatic fallback — PHPMailer does not revert to local `mail()` if a configured SMTP transport fails.
- This entry remains classified at the level of its most severe possible manifestation, consistent with the range-based Critical classification used elsewhere in this catalog, while its own materially better diagnostic visibility is reflected throughout Sections 9–11 rather than in a lower severity classification.

---

# 6. Distinction

This entry applies only when verified evidence establishes that `wp_mail()` is configured to use SMTP transport, and that the resulting condition traces to that transport's own connection, TLS, authentication, or protocol-exchange mechanism specifically.

It is distinct from:

- **`WP-ERROR-046` — WordPress Local Mail Transport Failure**: owns the condition where `wp_mail()` is using its own default, non-SMTP transport. The two transports are mutually exclusive by construction — a given `wp_mail()` call uses exactly one — and this entry presumes SMTP is configured; where it is not, `WP-ERROR-046`'s own mechanism applies instead.
- **`WP-ERROR-028`/`029` — WordPress Outbound HTTP Request Failure / TLS Negotiation Failure**: own connection-establishment and TLS-negotiation failures specifically for WordPress's own `WP_Http` API (`wp_remote_get()`/`wp_remote_post()`/`wp_remote_request()`). PHPMailer's own SMTP client does not use `WP_Http` at all — it implements the SMTP protocol directly via its own socket communication, an entirely separate mechanism, the central technical finding `SF-TAXONOMY-012` Section 2 already establishes. This entry's own connection- and TLS-failure causes are superficially similar in kind to `WP-ERROR-028`/`029`'s own conditions but occur in a genuinely distinct code path this catalog does not consider an alternate entry point into either.
- **The calling code's own trigger logic** — `SF-TAXONOMY-012` Section 2's own exclusion; this entry presumes `wp_mail()` was correctly and appropriately called, and owns only what happens once that call attempts delivery via the configured SMTP transport.
- **A message's own eventual deliverability outcome once the SMTP server has genuinely accepted it for further relay** — `SF-TAXONOMY-012` Section 2's own explicitly disclosed gap; a fully successful SMTP transaction does not guarantee the receiving mail server ultimately delivers to the recipient's inbox, and that outcome remains outside WordPress's own visibility, the same disclosed gap `WP-ERROR-046` Section 6 already applies to the local-transport case.
- **PHPMailer's own pre-transport message-composition validation** — `SF-TAXONOMY-012` Section 4's own explicitly disclosed, currently-unclaimed gap, occurring identically regardless of which transport is configured.

---

# 7. Scope

**Covered:** A verified condition in which `wp_mail()`, configured to use SMTP transport, fails to establish a connection, negotiate TLS/SSL, authenticate, or complete the SMTP protocol exchange with the configured mail server.

**Excluded:**

- Any condition where `wp_mail()` is using its own default, non-SMTP transport (`WP-ERROR-046`).
- Connection-establishment or TLS-negotiation failure for WordPress's own `WP_Http`-based outbound requests (`WP-ERROR-028`/`029`), a genuinely separate mechanism.
- The calling code's own decision to send a specific email in the first place.
- A message's own deliverability outcome once the SMTP server has genuinely accepted it for further relay.
- PHPMailer's own pre-transport message-composition validation, occurring before any transport is attempted.

---

# 8. WordPress Components

Listed as components commonly involved, not as a claim that every installation exercises every one of them:

- `wp_mail()` (`wp-includes/pluggable.php`) and PHPMailer's own `SMTP` transport mode, activated once SMTP host/credential configuration is present, commonly via a dedicated SMTP-configuring plugin or a `phpmailer_init`/`wp_mail_smtp` filter.
- PHPMailer's own `SMTP` class, which implements the SMTP protocol directly via PHP socket functions, independent of `WP_Http`.
- The configured SMTP host, port (commonly `587` with STARTTLS, `465` with implicit SSL/TLS, or `25`, increasingly blocked by hosting providers), and authentication credentials.
- The `wp_mail_failed` action, which fires with a specific, diagnosable `WP_Error` for the large majority of this entry's own causes, since PHPMailer's own SMTP client directly observes and can report on the entire protocol exchange.
- A dedicated SMTP-configuring plugin's own test-email feature, commonly the most direct diagnostic entry point for isolating exactly where in the connection/TLS/authentication/exchange sequence a failure occurs.

---

# 9. Typical Symptoms

- `wp_mail_failed` firing reliably with a specific `WP_Error` describing the connection, TLS, authentication, or protocol-level failure — the distinguishing symptom separating this entry's own condition from `WP-ERROR-046`'s own largely silent one.
- An SMTP-configuring plugin's own test-email feature failing with a specific, visible error message rather than a generic failure.
- Email failing consistently and immediately, characteristic of a connection or authentication failure, as opposed to intermittent failures more characteristic of server-side rate-limiting.
- A previously-working SMTP configuration failing suddenly, commonly following a credential rotation at the mail-provider side that was not reflected in WordPress's own stored configuration.

---

# 10. Common Causes

Causes are grouped by category. Inclusion in this list identifies a category as plausible; it does not assert that any specific cause is present without diagnostic confirmation.

- Incorrect SMTP host, port, or encryption-type configuration relative to the mail provider's own current documented requirements.
- Invalid or expired SMTP credentials, commonly following a password change or credential rotation at the mail-provider side not reflected in WordPress's own stored configuration.
- The SMTP server enforcing a sending-rate limit the site's own outbound volume exceeds.
- A firewall or hosting-level restriction blocking outbound connections on the configured SMTP port — many hosting providers block port `25` specifically, requiring `587` or `465` instead.
- The SMTP provider requiring a specific authentication method (for example, OAuth2 or an app-specific password) the configured plugin does not support or was never set up to use.
- A TLS/SSL protocol-version or certificate mismatch between PHPMailer's own TLS client and the SMTP server's own current requirements.

---

# 11. Diagnosis

Verify the following, starting from the least invasive observation and narrowing only once the general shape of the failure is established:

1. **Confirm SMTP is indeed configured** — if `wp_mail()` is in fact using its own default transport, this is `WP-ERROR-046`'s own condition, not this entry's.
2. **Capture the specific `WP_Error` `wp_mail_failed` reports**, which, unlike `WP-ERROR-046`'s own condition, is available for the large majority of this entry's own causes and directly narrows the investigation to connection, TLS, authentication, or protocol-exchange failure.
3. **Use the configured SMTP-plugin's own test-email feature** to isolate the specific failure point directly, rather than relying solely on production email traffic to reproduce the condition.
4. **Verify the configured host, port, and encryption-type combination against the mail provider's own current documented requirements**, since a provider's own recommended configuration can change over time.
5. **Verify outbound connectivity to the configured port specifically is not blocked** by a firewall or hosting-level restriction, particularly for port `25`.
6. **Verify credentials are current and the authentication method is genuinely supported** by both the configured plugin and the mail provider's own current requirements.
7. **Check for a rate-limiting or quota-related message in the SMTP server's own response**, where visible in the captured `WP_Error` or plugin-level diagnostic output.

---

# 12. Recovery Procedure

Recovery shall target the specific, verified cause identified in Diagnosis (Section 11).

Permitted recovery categories, depending on the verified cause, include:

- Correcting the configured SMTP host, port, or encryption-type setting to match the mail provider's own current documented requirements.
- Updating stored SMTP credentials to match the provider's own current, valid values.
- Switching to a port not blocked by the hosting environment's own outbound firewall configuration.
- Adjusting the site's own outbound sending rate or batch size where diagnosis confirms provider-side rate-limiting.
- Reconfiguring the SMTP-configuring plugin to use an authentication method the provider actually supports, where the previously-configured method has been deprecated or was never correctly supported.

---

# 13. Validation

Recovery is successful when:

- A test email, sent via the SMTP-configuring plugin's own test feature or a genuine `wp_mail()` call, is confirmed both by the absence of a further `wp_mail_failed` event and by actual receipt at the destination inbox.
- The confirmation holds across more than one attempt, since an intermittent cause (rate-limiting, a transient connectivity issue) may not be fully ruled out by a single successful send.
- Where the correction involved credential rotation, the previous, invalid credentials are confirmed no longer in use anywhere in the site's own configuration.

---

# 14. Prevention

- Use a well-documented, reputable SMTP provider with clear, current setup instructions, rather than an ad hoc or undocumented configuration.
- Actively monitor `wp_mail_failed` events — given this entry's own condition is, unlike `WP-ERROR-046`'s, reliably signaled, a monitoring gap here is a missed opportunity this category's own local-transport entry does not have.
- Proactively rotate and update SMTP credentials before they expire, where the provider supports advance notice or a defined rotation schedule, rather than waiting for a failure to discover an expired credential.
- Verify the configured SMTP port remains permitted by the hosting environment's own outbound firewall after any hosting migration or infrastructure change.

---

# 15. Security Considerations

- SMTP credentials stored in WordPress's own database, via a configuring plugin's own options, are a sensitive credential and warrant the same protection as any other stored secret — restrict database and `wp-admin` access accordingly.
- Verify TLS/SSL encryption is genuinely in effect for the configured connection, rather than assuming it from the plugin's own configuration screen alone, to avoid credential and message-content exposure in transit if a silent fallback to an unencrypted connection occurs.
- Where a credential is confirmed compromised or exposed, rotate it immediately at the provider side, not only within WordPress's own stored configuration, since the provider-side credential remains valid and usable by anyone who obtained it until revoked there.

---

# 16. Related Errors

The following are cited as they exist in this repository, or as conceptual distinctions where noted.

1. [WP-ERROR-046 — WordPress Local Mail Transport Failure](WP-ERROR-046-LOCAL-MAIL-TRANSPORT-FAILURE.md) — exists in this repository; see Section 6 (Distinction) above for how the two transport mechanisms remain mutually exclusive, and for the deliberate severity/visibility contrast between them.
2. [WP-ERROR-028 — WordPress Outbound HTTP Request Failure](WP-ERROR-028-WORDPRESS-OUTBOUND-HTTP-REQUEST-FAILURE.md) — exists in this repository; see Section 6 (Distinction) above for why PHPMailer's own SMTP client, despite superficial similarity, is not an alternate entry point into this entry's own condition.
3. [WP-ERROR-029 — WordPress Outbound TLS Negotiation Failure](WP-ERROR-029-OUTBOUND-TLS-NEGOTIATION-FAILURE.md) — exists in this repository; see Section 6 (Distinction) above for the same reasoning as `WP-ERROR-028`.

---

# 17. Notes

This entry documents the general, verified observable condition of WordPress's own SMTP mail transport failing to connect, negotiate encryption, authenticate, or complete the protocol exchange with a configured mail server, explicitly reasoning through why this entry's own diagnostic visibility differs materially from its sibling `WP-ERROR-046`'s own largely silent condition despite sharing the same range-based Critical severity. It is the second and final planned entry in the Email category, per `SF-TAXONOMY-012` Section 3.

Consistent with the single-responsibility principle in **SF-SPEC-001** Section 4.3, this entry does not claim ownership of `WP-ERROR-028`/`029`'s own `WP_Http`-based territory despite the superficial similarity of "an outbound network connection failing," nor of a message's own deliverability outcome once the SMTP server has genuinely accepted it for further relay.

This entry's relationship to a `Production Ready` designation is established via `SF-REVIEW-143` (Class A author review) and `SF-REVIEW-144` (Class B independent review) per **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.
