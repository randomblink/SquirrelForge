# SF-SPEC-001 — Error Knowledge Specification

## Document Information

**Document ID:** SF-SPEC-001

**Title:** Error Knowledge Specification

**Classification:** Engineering Specification

**Status:** Draft

**Version:** 1.2

**Owner:** SquirrelForge

---

# 1. Purpose

## 1.1 Objective

Define the engineering requirements for every `WP-ERROR-XXX` knowledge entry maintained by SquirrelForge.

This specification ensures every error document is:

- Technically accurate.
- Consistent.
- Deterministic.
- Searchable.
- Maintainable.
- Suitable for engineering reference.
- Suitable for AI-assisted retrieval.

---

# 2. Scope

This specification applies to:

- Every WP-ERROR document.
- Every revision.
- Every contributor.
- Every engineering review.

This specification does not describe WordPress behavior.

It defines how SquirrelForge documents WordPress behavior.

---

# 3. Specification Boundaries

## 3.1 Owns

- WP-ERROR knowledge-entry requirements.

## 3.2 Depends On

- None. This specification does not depend on other SquirrelForge specifications for its normative content.

## 3.3 Does Not Define

- WordPress runtime behavior itself. This specification defines how that behavior is documented, not the behavior.

---

# 4. Engineering Principles

## 4.1 Accuracy

Every technical statement shall be factually correct.

---

## 4.2 Determinism

Diagnosis, recovery, and validation shall rely on observable system behavior.

---

## 4.3 Single Responsibility

Each document shall describe one error class.

---

## 4.4 Scope Boundaries

Each document shall define:

- What it covers.
- What it does not cover.

---

## 4.5 Operational Value

Every document shall enable an engineer to:

- Identify
- Diagnose
- Recover
- Validate
- Prevent

the documented failure.

---

## 4.6 Security

No document shall encourage exposing credentials or secrets.

---

## 4.7 Consistency

Every document shall comply with this specification.

---

# 5. Required Document Structure

Every WP-ERROR document shall contain these sections in exactly this order.

1. Knowledge Entry
2. Metadata
3. Summary
4. Primary Failure Mode
5. Severity
6. Distinction
7. Scope
8. WordPress Components
9. Typical Symptoms
10. Common Causes
11. Diagnosis
12. Recovery Procedure
13. Validation
14. Prevention
15. Security Considerations
16. Related Errors
17. Notes

---

# 6. Metadata Standard

Every entry shall include:

- Error ID
- Title
- Category
- Severity
- Recovery Priority
- Status
- Version

Additional metadata shall not be introduced except through revision of this specification.

---

# 7. Category Standard

Defines every approved category.

Approved category values (this list is exhaustive, not illustrative — see this section's closing sentence; the prior "Examples:" heading was corrected in Version 1.2 to avoid that ambiguity):

- Bootstrap
- Configuration
- PHP Runtime
- Database
- Filesystem
- Plugin
- Theme
- REST API
- Authentication
- Security
- Performance
- Deployment
- CLI
- Networking
- Media
- Cron
- Email
- Multisite
- Site Health

No new category may be introduced without updating this specification.

---

# 8. Severity Standard

Severity levels shall be defined using objective criteria.

---

# 9. Writing Standard

Every document shall comply with the following writing requirements:

- Use technical English.
- Avoid marketing language.
- Avoid speculation.
- Use observable behavior.
- Use deterministic wording.
- Avoid unsupported assumptions.

---

# 10. Scope Standard

Every document shall explicitly define:

- Covered failures.
- Excluded failures.

This prevents overlap.

---

# 11. WordPress Components Standard

Components shall:

- Be directly relevant.
- Be listed in a consistent order.
- Avoid unnecessary implementation details.

---

# 12. Diagnosis Standard

Diagnosis shall:

- Begin with "Verify the following:"
- Use numbered steps.
- Progress from least invasive to most invasive.

---

# 13. Recovery Procedure Standard

Recovery shall:

- Correct the root cause.
- Avoid workarounds unless identified as temporary.
- Preserve data whenever possible.

---

# 14. Validation Standard

Validation shall begin:

> Recovery is successful when:

Validation shall describe observable outcomes rather than recovery actions.

---

# 15. Prevention Standard

The Prevention section shall describe operational practices that reduce recurrence.

---

# 16. Security Standard

Every document shall identify:

- Sensitive information.
- Logging risks.
- Secret handling.
- Credential rotation requirements.
- Diagnostic safety considerations.

---

# 17. Related Errors Standard

Related errors shall:

- Be directly related.
- Be ordered numerically.
- Avoid speculative references.

---

# 18. Versioning

Every error knowledge entry shall follow the SquirrelForge lifecycle defined by the applicable lifecycle specification.

The version history shall accurately record every approved revision to the knowledge entry.

Typical lifecycle progression includes:

Draft

↓

Engineering Review

↓

Production Ready

↓

Version Frozen

↓

Revision

---

# 19. Production Ready Definition

A Production Ready error entry shall:

- Meet every requirement in this specification.
- Be technically accurate.
- Have deterministic diagnosis.
- Have deterministic recovery.
- Have objective validation.
- Have complete cross-references.
- Pass engineering review.

---

# 20. Engineering Review Checklist

☐ Metadata complete

☐ Structure compliant

☐ Scope bounded

☐ Diagnosis complete

☐ Recovery complete

☐ Validation complete

☐ Security reviewed

☐ Related Errors verified

☐ Production Ready

---

# 21. Change Control

This specification shall not be modified to accommodate an individual error entry.

The specification shall be revised only when a change benefits the knowledge base as a whole.

Changes shall be versioned and documented.

---

# 22. Reference Implementations

## 22.1 Purpose

Reference Implementations identify one or more `WP-ERROR-XXX` knowledge entries that fully conform to this specification. They serve as practical engineering examples for authors and reviewers while remaining separate from this specification.

Reference Implementations are informative and do not replace the normative requirements defined by this specification.

---

## 22.2 Requirements

A Reference Implementation shall:

- Fully comply with every requirement defined by this specification.
- Be designated as **Production Ready**.
- Have successfully completed engineering review.
- Remain internally consistent.
- Represent current SquirrelForge engineering practices.
- Be maintained as a standalone knowledge entry.

The authoritative version of a Reference Implementation shall always be the individual `WP-ERROR-XXX` document. This specification shall not duplicate or embed the contents of any error knowledge entry.

---

## 22.3 Current Reference Implementations

No Reference Implementation is currently designated. `WP-ERROR-014` and `WP-ERROR-015` were previously named here, but no document with either identifier exists in the repository; the citations were unverifiable and have been removed rather than left in place unverified.

A Reference Implementation may be designated once a `WP-ERROR-XXX` document exists that satisfies every requirement of Section 22.2, including explicit **Production Ready** designation and completed engineering review.

---

## 22.4 Engineering Guidance

Once a Reference Implementation exists, authors should compare new `WP-ERROR-XXX` documents against it during development and engineering review to ensure compliance with this specification. No Reference Implementation is currently designated (see Section 22.3); this guidance applies once one has been.

Reference Implementations are intended to demonstrate correct application of this specification. They do not supersede, modify, or extend the normative requirements defined by this document.

---

# 23. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-13 | Initial specification, established as part of the framework's initial authoring pass (commit `7f29178`), then reviewed and corrected by `SF-REVIEW-002` (Specification Library Review) as part of a unified pass across `SF-SPEC-001` through `011` (Phases 1–6, 2026-07-12): Entry 3 (Phase 4) removed unverified `WP-ERROR-014`/`015` Reference Implementation citations from §22.3; Entry 5 (Phase 6, Finding F-1) corrected §22.4's present-tense contradiction with §22.3's corrected (empty) state. `SF-REVIEW-002` predates, and does not use, the Class A/Class B/Class C reviewer-classification system **SF-SPEC-012** later introduced (first applied in `SF-REVIEW-005`). | Draft — reviewed via `SF-REVIEW-002` (predates Class A/B system) |
| 1.1 | 2026-07-14 | Added this Revision History section, required by **SF-SPEC-004** Section 5.9 but absent until now. Migrated as part of a repository-wide pass verified by `SF-REVIEW-060` (Specification Library Revision-History Migration Review). No normative content changed. | Draft — migration verified, see `SF-REVIEW-060` |
| 1.2 | 2026-07-14 | Section 7 (Category Standard) amended, post-`SF-BASELINE-001`: added six approved category values (`HTTP / Networking`, `Media`, `Cron / Scheduled Tasks`, `Email`, `Multisite`, `Site Health`) per the Knowledge Production Plan's roadmap, batch-approved by explicit project-owner direction rather than one at a time. Also corrected the section's own "Examples:" heading to state plainly that the list is exhaustive, matching the section's pre-existing closing sentence ("no new category may be introduced without updating this specification") rather than leaving the two in tension. This is ordinary category-list maintenance the section's own closing sentence anticipates as its normal mechanism, not a framework reopening driven by a governance deficiency — distinct in kind from the structural work `SF-REVIEW-054` through `066` performed before `SF-BASELINE-001`. | Draft — author-reviewed, see `SF-REVIEW-067` |
| 1.2 | 2026-07-14 | Class B independent review (`SF-REVIEW-068`) found and corrected one Minor finding (IF-1): two of the six new values (`HTTP / Networking`, `Cron / Scheduled Tasks`) used a slash-delimited format inconsistent with every pre-existing value; renamed to `Networking` and `Cron`. This revision reviewed and Approved with Minor Revisions; the specification as a whole remains `Draft` overall, unchanged by this narrowly-scoped revision. | Draft overall — Version 1.2 revision Approved with Minor Revisions |
