# SF-TAXONOMY-012 — Email Error Taxonomy

## Document Information

**Document ID:** SF-TAXONOMY-012

**Title:** Email Error Taxonomy

**Classification:** Engineering Taxonomy — a lightweight, informally-adopted planning artifact, governed by **SF-SPEC-013 — Knowledge Category Lifecycle Specification** for its role in the category lifecycle, but not itself templated by any `SF-TEMPLATE-XXX` document (none currently exists for this artifact type). It is not governed by **SF-SPEC-005**'s review process as a knowledge artifact and does not itself require an author/independent review pair, though an independent review of it is planned per this project's established practice (`SF-REVIEW-034`, `045`, `069`, `080`, `096`, `105`, `114`, `121`, `128`, `135`), not as a normative requirement this document imposes on itself.

**Status:** Frozen — the entry set in Section 3 is fixed until this document is deliberately revised (see Section 6). "Frozen" here is an informal, self-defined term describing this document's own adopted-plan state; it is not a claim of the `Version Frozen` **SF-SPEC-001** Section 18 lifecycle stage, nor of any **SF-SPEC-008** Section 6 Version Status value, nor of the category-level `Baseline Certified` designation **SF-SPEC-013** Section 5.5 defines. This document carries a `Version` and `Revision History` for traceability only; it does not present itself as a "versioned engineering artifact" within **SF-SPEC-008**'s own scope, the same disclaimer `SF-TAXONOMY-001`–`011` make.

**Version:** 1.2

**Owner:** SquirrelForge

---

## 1. Purpose

Declare the `Email` category's boundary and complete planned `WP-ERROR` entry set *before* any entry in it is authored, per **SF-SPEC-013** Section 5.1's Category Entry Criteria — the eighth category in this catalog to receive a dedicated taxonomy from a genuinely zero-entry starting point.

This document does not itself contain any `WP-ERROR` knowledge content and is not cited as a `WP-ERROR` entry.

---

## 2. Category Boundary

**Email** owns failures in WordPress's own outbound email-sending mechanism — `wp_mail()` and the bundled PHPMailer library it wraps — specifically the two mutually exclusive transport mechanisms WordPress can use to hand a composed message off for delivery. It does not own a message's own eventual delivery, spam-filtering, or bounce outcome once WordPress's own responsibility for the send attempt has concluded, nor a specific plugin's own business logic for deciding when or to whom to send an email.

**The central, load-bearing finding of this taxonomy's own ownership sweep:** WordPress's SMTP transport does **not** use `WP_Http` (the mechanism `WP-ERROR-028`/`029` already own) at all. PHPMailer implements its own direct, socket-level SMTP client (via `fsockopen()`/`stream_socket_client()`, not `wp_remote_*()`), entirely independent of WordPress's own HTTP API. This is a genuinely different protocol and mechanism, not an alternate entry point into one already owned elsewhere — the same class of distinction `WP-ERROR-028`'s own Section 6 already draws for the MySQL/MariaDB database connection against `WP-ERROR-007`/`008` ("The database connection is not made through `WP_Http` at all; it uses an entirely separate driver-level connection"). A full-text sweep confirmed zero existing entries mention `wp_mail`, `PHPMailer`, `SMTP`, or `sendmail` anywhere in this catalog — this category's own territory is entirely unclaimed.

**Explicitly not owned by Email:**

* **A message's own eventual delivery outcome once WordPress's own send attempt has concluded successfully** (a soft or hard bounce, spam-filtering by the recipient's own mail provider, or a reputation-based rejection) — outside WordPress's own visibility and control entirely once the local MTA or the SMTP server has accepted the message for further relay. Considered and explicitly disclosed as a gap rather than silently absorbed; see Section 5.
* **A specific plugin's own decision logic for when, to whom, or with what content to send an email** (a notification plugin's own business rules, a WooCommerce order-confirmation trigger) — a specific plugin's own business-logic defect, not a WordPress-mechanism failure, the same class of exclusion this catalog has applied throughout (`SF-TAXONOMY-005` Section 2's own first exclusion, and every subsequent lifecycle taxonomy's own equivalent).
* **A password-reset or account-notification email's own *trigger* logic** (`retrieve_password()`, `wp_new_user_notification()`, and similar core functions that decide *when* to call `wp_mail()`, and, for a password reset specifically, the reset key's own generation and later validation) — not this category's territory; this category owns only what happens once `wp_mail()` itself is actually invoked and attempts to hand a message off for delivery, regardless of which core or plugin code initiated that call. This exclusion directly, partially resolves `SF-TAXONOMY-003` Section 5's own deferred "Password-reset / lost-password recovery flow" candidate, which explicitly anticipated needing "Email category boundaries... to disambiguate the delivery-failure portion": that delivery-failure portion is now `WP-ERROR-046`/`047`'s own territory, once `wp_mail()` is actually called for a reset email. The reset key's own generation and validation mechanics remain genuinely unclaimed by any taxonomy in this catalog — still `WP-ERROR-024`'s/Authentication category's own future decision, not resolved by this taxonomy.
* **An outbound HTTP-based email-service-provider API call** (a transactional-email service reached via its own REST API rather than SMTP, commonly used by an email-sending plugin instead of PHPMailer's own SMTP client) — already `WP-ERROR-028`'s own territory, since such a call genuinely does use `WP_Http`/`wp_remote_post()`. This category owns only PHPMailer's own two native transport mechanisms (Section 3), not every possible means by which a plugin might choose to send an email outside WordPress's own default mechanism entirely.

---

## 3. Planned Entries

| Entry | Title | Owns | Status |
|---|---|---|---|
| `WP-ERROR-046` | WordPress Local Mail Transport Failure | A verified condition in which `wp_mail()`, configured to use its own default transport (PHP's `mail()` function, with no SMTP explicitly configured), fails to result in a delivered message — either because PHP's own `mail()` function itself is disabled or unavailable in the running environment, or because `mail()` reports success (which only confirms the message was handed off to the local system's own configured MTA, not that the MTA ever actually delivered it) while the underlying local mail transport agent silently fails, is unconfigured, or is entirely absent. | Existing, Production Ready |
| `WP-ERROR-047` | WordPress SMTP Mail Transport Failure | A verified condition in which `wp_mail()`, configured (via a plugin or filter) to use PHPMailer's own direct SMTP client instead of PHP's `mail()` function, fails to establish a connection, authenticate, or complete the SMTP protocol exchange with the configured mail server — a distinct mechanism from `WP-ERROR-028`/`029`, since PHPMailer's own SMTP client does not use `WP_Http` at all. | Existing, Production Ready |

Nothing else is currently planned for this category. Any future addition to this table is a revision to this document, not an ad hoc decision made while authoring an unrelated entry, per **SF-SPEC-013** Section 5.7.

---

## 4. Ownership Model

The two entries divide by **transport mechanism**, mutually exclusive by construction: a given WordPress installation's `wp_mail()` calls use exactly one of the two at any time (local `mail()` by default, or SMTP once explicitly configured), never both simultaneously for the same message — the same independent-mechanisms model `SF-TAXONOMY-005` established for Plugin, not a sequential pipeline.

**A pre-transport condition considered and deliberately left unclaimed by either entry:** PHPMailer performs its own message-composition validation (for example, rejecting a malformed recipient address) *before* attempting either transport, meaning a validation failure occurs identically regardless of which transport is configured. This taxonomy considered folding this into one of the two entries but concluded neither is the more natural home for a condition that, by definition, occurs before transport selection even happens — assigning it to either would misrepresent it as transport-specific. Disclosed as a genuine, undecided gap rather than silently absorbed or arbitrarily assigned; see Section 5.

---

## 5. Candidates Considered and Rejected

* **A dedicated "Mail Message Validation/Composition Failure" entry**, covering PHPMailer's own pre-transport rejection of a malformed message (an invalid recipient address, a missing required field): considered and genuinely deferred rather than rejected outright or force-assigned to one of the two transport entries. This taxonomy found the condition real but did not find evidence it occurs commonly or severely enough, relative to the two transport-failure entries, to justify a third entry at this taxonomy's current level of granularity — disclosed as an open gap a future revision could still address per **SF-SPEC-013** Section 5.6 if evidence emerges.
* **A dedicated "Mail Delivery/Deliverability Failure" entry**, covering a message accepted by WordPress's own transport but never actually reaching the recipient's inbox (spam-filtering, a bounce, a reputation-based rejection): considered and rejected outright, not merely deferred. This is categorically outside WordPress's own observable mechanism once a transport has accepted a message for further relay — diagnosing it requires visibility into infrastructure (the recipient's own mail server, spam-filtering heuristics, sender-reputation databases) this catalog's own methodology cannot document as a reproducible WordPress mechanism, the same class of reasoning `WP-ERROR-028` Section 7 already applied to disclose, rather than claim, an HTTP-level error status returned by a remote host.
- **A dedicated "Email Notification Business-Logic" entry**, covering a specific plugin's own decision of when or to whom to send a notification: rejected outright, per Section 2's own exclusion — a specific plugin's own business-logic defect, not a WordPress-mechanism condition.

---

## 6. Revision History

| Version | Date | Summary of Changes | Status |
|---|---|---|---|
| 1.0 | 2026-07-15 | Initial taxonomy, drafted from zero entries. The proactive cross-category ownership sweep (standard since `SF-TAXONOMY-006`) found this category's own territory entirely unclaimed — zero existing entries mention `wp_mail`, `PHPMailer`, `SMTP`, or `sendmail` anywhere in the catalog — and confirmed the central technical distinction that WordPress's SMTP transport does not use `WP_Http` at all, so it does not overlap `WP-ERROR-028`/`029`'s own territory despite superficial "outbound network communication" similarity. Also found and resolved a genuine cross-taxonomy dependency: `SF-TAXONOMY-003` Section 5's own deferred "Password-reset / lost-password recovery flow" candidate explicitly anticipated needing "Email category boundaries... to disambiguate the delivery-failure portion" — this taxonomy's own Section 2 now resolves that portion (`WP-ERROR-046`/`047`'s own territory once `wp_mail()` is called for a reset email), while confirming the reset key's own generation/validation mechanics remain genuinely unclaimed, still Authentication's own future decision. The seventh consecutive taxonomy to pass the sweep during drafting with no boundary correction required. Plans two entries — `WP-ERROR-046` (Local Mail Transport Failure) and `WP-ERROR-047` (SMTP Mail Transport Failure) — dividing by transport mechanism, mutually exclusive by construction. A dedicated message-validation entry considered and genuinely deferred as an open gap; a mail-deliverability entry and an email-notification-business-logic entry each considered and rejected outright, per Section 5. | Frozen |
| 1.1 | 2026-07-15 | WP-ERROR-046 reached Production Ready (SF-REVIEW-141 author review, no findings; SF-REVIEW-142 independent review, no findings — a clean pass, explicitly confirmed as a valid, complete outcome rather than a manufactured one). Status column updated from Planned to Existing, Production Ready. No boundary content changed; this entry was drafted directly from this taxonomy's own Version 1.0 declaration and required no revision to it. `WP-ERROR-024`'s own stale cross-reference to a "not currently covered" password-reset delivery condition is deliberately left uncorrected until `WP-ERROR-047` also exists, tracked explicitly in `SF-REVIEW-142`'s own Remaining Risks rather than silently deferred. | Frozen |
| 1.2 | 2026-07-15 | WP-ERROR-047 reached Production Ready (SF-REVIEW-143 author review, no findings; SF-REVIEW-144 independent review, which resolved the deferral SF-REVIEW-142 explicitly tracked — WP-ERROR-024's own stale password-reset-delivery citation, now updated to cite both WP-ERROR-046 and WP-ERROR-047 together — rather than finding a new defect in this entry or this taxonomy). Status column updated from Planned to Existing, Production Ready. No further boundary content changed; this entry required no revision to this taxonomy beyond what Version 1.1 already applied. All planned Email entries are now Existing, Production Ready. | Frozen |
