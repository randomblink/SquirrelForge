# SF-TEMPLATE-004 — WP-ERROR Knowledge Template

## Document Information

**Document ID:** SF-TEMPLATE-004

**Title:** WP-ERROR Knowledge Template

**Classification:** Engineering Template

**Status:** Draft

**Version:** 1.0

**Owner:** SquirrelForge

---

## Instructions for Use

This document is a template governed by **SF-SPEC-001 — Error Knowledge Specification**. It is not itself a `WP-ERROR-XXX` knowledge entry and shall not be cited as one.

Copy the section structure below — beginning at "1. Knowledge Entry" — into a new document to author an actual `WP-ERROR-XXX` entry. Do not copy this template's own Document Information block (above) into that new document; a `WP-ERROR-XXX` entry uses the Metadata structure defined in Section 2 below, not this template's own identity.

Do not insert a specific `WP-ERROR` number into any example or placeholder in this template. No error identifier shown anywhere below refers to an artifact that actually exists.

The section order below follows SF-SPEC-001 Section 5 (Required Document Structure) exactly.

---

# 1. Knowledge Entry

`<Title of the error knowledge entry.>`

---

# 2. Metadata

* **Error ID:** `<WP-ERROR-XXX>`
* **Title:** `<Title>`
* **Category:** `<One approved category from SF-SPEC-001 Section 7, Category Standard>`
* **Severity:** `<Critical / High / Medium / Low, per SF-SPEC-001 Section 8>`
* **Recovery Priority:** `<Priority>`
* **Status:** `<Draft>`
* **Version:** `<1.0>`

---

# 3. Summary

`<A brief, technical summary of the failure this entry documents.>`

---

# 4. Primary Failure Mode

`<Describe the specific, observable failure mode this entry addresses.>`

---

# 5. Severity

`<Justify the severity assigned in Section 2 using objective criteria, per SF-SPEC-001 Section 8.>`

---

# 6. Distinction

`<State how this failure is distinguished from other, related failures, to prevent overlap per SF-SPEC-001 Section 9.>`

---

# 7. Scope

`<State what this entry covers and what it explicitly does not cover.>`

---

# 8. WordPress Components

`<List the WordPress components directly relevant to this failure, in a consistent order, per SF-SPEC-001 Section 11.>`

---

# 9. Typical Symptoms

`<List observable symptoms.>`

---

# 10. Common Causes

`<List common causes.>`

---

# 11. Diagnosis

Verify the following:

1. `<Numbered diagnostic step, least invasive first, per SF-SPEC-001 Section 12.>`

---

# 12. Recovery Procedure

`<Describe the recovery procedure. It shall correct the root cause, avoid workarounds unless identified as temporary, and preserve data whenever possible, per SF-SPEC-001 Section 13.>`

---

# 13. Validation

Recovery is successful when:

* `<Observable outcome, not a recovery action, per SF-SPEC-001 Section 14.>`

---

# 14. Prevention

`<Describe operational practices that reduce recurrence.>`

---

# 15. Security Considerations

`<Identify sensitive information, logging risks, secret handling, credential rotation requirements, and diagnostic safety considerations, per SF-SPEC-001 Section 16.>`

---

# 16. Related Errors

`<List directly related errors, ordered numerically, avoiding speculative references, per SF-SPEC-001 Section 17.>`

---

# 17. Notes

`<Any additional notes not covered by the sections above.>`
