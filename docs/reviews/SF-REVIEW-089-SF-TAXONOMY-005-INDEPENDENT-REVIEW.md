# SF-REVIEW-089 — SF-TAXONOMY-005 Independent Review

# 1. Review Information

**Review ID:** SF-REVIEW-089

**Review Date:** 2026-07-14

**Reviewer:** Class B — Independent Review, per **SF-SPEC-012** Section 6.2, conducted per this project's established practice of independently reviewing a category's taxonomy before entry authoring begins (`SF-REVIEW-034`, `045`, `069`, `080`), not as a normative requirement `SF-TAXONOMY-005` itself imposes.

**Status:** Complete

This is the first taxonomy in this catalog drafted to account for an *existing*, already-Production-Ready entry (`WP-ERROR-017`) rather than declared against an empty category — the situation Database and PHP Runtime were originally in, but without ever receiving a dedicated taxonomy document of their own. This review accordingly gives particular scrutiny to whether `SF-TAXONOMY-005` accurately represents `WP-ERROR-017`'s own existing boundary rather than redefining it.

---

# 2. Artifact Reviewed

`SF-TAXONOMY-005` — Plugin Lifecycle Error Taxonomy, Version 1.0, at `docs/standards/SF-TAXONOMY-005-PLUGIN-LIFECYCLE-ERROR-TAXONOMY.md`.

---

# 3. Governing Specifications

- **SF-SPEC-013 — Knowledge Category Lifecycle Specification** (Section 5.1, Category Entry Criteria)
- **SF-SPEC-001 — Error Knowledge Specification** (Section 7, confirming `Plugin` is an approved category value)
- **SF-SPEC-004 — Documentation Specification** (internal consistency, cross-references)

---

# 4. Review Scope

This review independently determines whether `SF-TAXONOMY-005` satisfies **SF-SPEC-013** Section 5.1: a declared category boundary with explicit exclusions, an enumerated planned-entry set with one-line ownership statements, and documented rejected/deferred candidates with specific reasoning. Because this is the first taxonomy drafted against a pre-existing entry, this review independently re-verifies `WP-ERROR-017`'s own actual text against every claim `SF-TAXONOMY-005` makes about it, and separately re-verifies the taxonomy's central claim — that numerous "Plugin category" citations scattered across other entries describe an attribution convention rather than a catalog obligation — against each of those citing entries' own actual text, rather than accepting the taxonomy's own characterization.

---

# 5. Evidence Examined

- Full contents of `SF-TAXONOMY-005`, read in full.
- `SF-SPEC-001` Section 7, independently re-read to confirm `Plugin` is an approved category value (present in the original approved list, predating the six-category batch addition `SF-REVIEW-067`/`068` performed).
- `WP-ERROR-017`, independently re-read in full to verify every claim `SF-TAXONOMY-005` makes about it: the absence of an activation/deactivation toggle, the absence of built-in fatal-error protection, and the Section 17 single-responsibility disclaimer permitting future cause-specific entries.
- `WP-ERROR-013` (Bootstrap), independently re-read in full to verify the taxonomy's claim that Section 10 already names "a duplicate function or class declaration, commonly caused by a duplicated plugin installation or a duplicated file" as its own territory, and that Section 17 already anticipates cause-specific splits (including the must-use case `WP-ERROR-017` already executed).
- `WP-ERROR-016` (Core Files), independently re-read in full to verify the taxonomy's claim that Section 6/7 already excludes "plugin, theme, must-use plugin, and drop-in files" and explicitly anticipates a separate `WP-ERROR` entry for a corrupted plugin.
- `SF-TAXONOMY-001` Section 2, independently re-read to verify the "Plugin-specific assets — Plugin category" exclusion the taxonomy cites.
- Every other generic "Plugin category" citation the taxonomy's own Section 2 lists (`WP-ERROR-022`, `023`, `024`, `026`, `027`), individually re-read at the cited location to independently confirm none of them names a specific `WP-ERROR` ID for the territory being excluded — the load-bearing claim behind the taxonomy's decision not to declare a fourth, "generic plugin defect" entry.
- `ls docs/knowledge/wp-errors/ | grep -E "WP-ERROR-031|WP-ERROR-032"`, confirming neither planned ID currently exists.
- `grep -n '\bmust\b'` (excluding `must-use`/`Must-Use`) and a drafting-language sweep against the full document.
- Independent technical assessment of the two version-specific WordPress-core claims the taxonomy originally made (an activation-time fatal-error guard attributed to "WordPress 5.2," and an automatic-update rollback mechanism attributed to "WordPress 5.9") — this surfaced Finding IF-1 below.

---

# 6. Findings

| Finding ID | Severity | Requirement or Criterion | Observation | Evidence | Required Action | Resolution Status |
|---|---|---|---|---|---|---|
| — | Conforming | SF-SPEC-013 §5.1, bullet 1 (boundary) | Section 2 declares a clear positive boundary (WordPress core's own generic plugin-lifecycle mechanisms: loading, activation, requirement enforcement, update) and explicitly distinguishes it from the far broader, unbounded territory a naive reading of "Plugin category" might suggest (arbitrary third-party business-logic defects). | Section 2. | None. |
| — | Conforming | Accurate representation of WP-ERROR-017 | Independently re-read `WP-ERROR-017` in full: confirmed it owns must-use plugin fatal errors specifically, confirmed its own Section 5/6 state must-use plugins have no activation/deactivation toggle and no built-in fatal-error guard, and confirmed Section 17's single-responsibility disclaimer permits — but does not require — future cause-specific splits. `SF-TAXONOMY-005`'s own Section 3 row for `WP-ERROR-017` accurately restates this without redefining any boundary the existing entry's own text establishes. | `WP-ERROR-017`, full text, Section 5 above. | None. |
| — | Conforming | Accurate representation of WP-ERROR-013 | Independently re-read `WP-ERROR-013` in full: Section 10 confirmed to name "a duplicate function or class declaration, commonly caused by a duplicated plugin installation or a duplicated file" as an already-owned cause; Section 17 confirmed to explicitly anticipate cause-specific splits including the must-use case. `SF-TAXONOMY-005`'s Section 2/5 claims about this entry are independently confirmed accurate. | `WP-ERROR-013`, full text, Section 5 above. | None. |
| — | Conforming | Accurate representation of WP-ERROR-016 | Independently re-read `WP-ERROR-016` Section 6/7 in full: confirmed "plugin, theme, must-use plugin, and drop-in files" are explicitly excluded from that entry's own scope and explicitly described as "a related but distinct condition that may be documented by a separate `WP-ERROR` entry." `SF-TAXONOMY-005`'s citation of this is accurate. | `WP-ERROR-016`, full text, Section 5 above. | None. |
| — | Conforming | Accurate representation of SF-TAXONOMY-001 | Independently re-read `SF-TAXONOMY-001` Section 2: "Plugin-specific assets — Plugin category" confirmed present, as a single, unelaborated bullet. `SF-TAXONOMY-005`'s own treatment (mechanism-triggered file operations claimed during a lifecycle transition; at-rest plugin-file integrity disclosed as an unowned gap, not silently claimed) is a reasonable, disclosed interpretation of an otherwise unelaborated exclusion, consistent with how this catalog has resolved similarly terse cross-taxonomy pointers before. | `SF-TAXONOMY-001` Section 2. | None. |
| — | Conforming | Central claim: generic "Plugin category" citations are an attribution convention, not a catalog obligation | Independently re-read every generic citation the taxonomy's Section 2 lists (`WP-ERROR-022` Section 6/11, `023` Section 6/10, `024` Section 6/17, `026` Section 6/17, `027` Section 6/17) at its actual cited location: none names a specific `WP-ERROR` ID for the "Plugin category" territory being excluded; every one attributes to "a specific plugin's own [defect/implementation/business rule]" in the abstract. This independently confirms the taxonomy's own conclusion that these citations describe a residual-attribution pattern rather than a specific, unfulfilled forward-reference promise (unlike, for example, `WP-ERROR-021`'s own specific, ID-bearing CORS forward-reference that `WP-ERROR-030` later resolved). The decision not to declare a fourth "generic plugin defect" entry is independently verified as sound. | Section 5 above. | None. |
| IF-1 | Minor | Technical accuracy (Principle: Evidence Over Assertion) | Section 3's original text attributed the plugin-activation fatal-error guard specifically to "WordPress 5.2" and the automatic-update rollback mechanism specifically to "WordPress 5.9." Independent assessment found insufficient confidence to support either specific version attribution: the activation-time guard (aborting activation and leaving a plugin inactive after detecting a fatal error during its activation-time include) is plausibly older than, and mechanically distinct from, the general Recovery Mode / fatal-error-protection initiative WordPress 5.2 is well-established for, and conflating the two would misattribute a long-standing, narrower mechanism to a newer, broader one. The specific "5.9" attribution for automatic-update rollback carries the same unverified-specificity risk. Asserting a specific core version neither claim could be independently confirmed against violates this catalog's own evidence-over-assertion discipline, the same standard `SF-REVIEW-080` applied to `SF-TAXONOMY-004`'s own citation claims. | `SF-TAXONOMY-005` Section 3 (pre-correction). | Remove both specific version-number attributions; describe each mechanism functionally (what it does, and — for the rollback case — that it applies to automatic updates only, not a manually-triggered update) without asserting the WordPress core version that introduced it. | Resolved (already corrected during drafting, prior to this review being opened — see Section 7) |
| — | Conforming | SF-SPEC-013 §5.1, bullet 2 (planned entries, ownership) | Three entries (one existing, two planned), each with a one-line ownership statement in Section 3's table. | Section 3. | None. |
| — | Conforming | Ownership Model (Section 4) internal consistency | Independently re-derived: the three entries divide by lifecycle stage (load / activate / update), each reachable independently of the others rather than through a shared precondition chain — mechanically distinct from `SF-TAXONOMY-004`'s own two-axis model, and the taxonomy correctly does not force a false analogy to that structure. No logical gap or overlap found among the three stages as declared. | Section 4. | None. |
| — | Conforming | SF-SPEC-013 §5.1, bullet 3 (rejected/deferred candidates, reasoning) | Five candidates addressed (deactivation failure, uninstall failure, a dedicated conflict entry, a separate dependency-chain entry, a separate rollback-failure entry), each with specific reasoning distinguishing rejection/folding from deferral. | Section 5. | None. |
| — | Conforming | `SF-SPEC-001` Section 7 conformance | `Plugin` independently confirmed present in the approved category-value list. | `SF-SPEC-001` Section 7. | None. |
| — | Conforming | ID availability | `WP-ERROR-031` and `032` independently confirmed to not currently exist in the repository. | `ls` sweep, Section 5 above. | None. |
| — | Conforming | Structural sweep | Zero bare `must` outside `must-use`/legitimate use; zero drafting-language matches. | Section 5 above. | None. |

No Major or Critical findings.

---

# 7. Note on IF-1's Resolution Timing

IF-1 was identified during this review's own independent technical assessment of the two version-specific claims, but the correction had already been applied to `SF-TAXONOMY-005` during its own drafting pass, before this review formally began, once the same confidence gap was noticed while re-checking the claims prior to publication. This review independently re-confirms the correction is accurate and complete (Section 6 above) rather than treating it as newly discovered; it is recorded as a finding here so the correction has a review record documenting it, consistent with this catalog's evidence-governance expectations, rather than being an undocumented change — the same disclosure pattern `SF-REVIEW-080` Section 7 established for `SF-TAXONOMY-004`'s own IF-1.

---

# 8. Outcome

**Approved.**

**Basis:** `SF-TAXONOMY-005` satisfies every element of **SF-SPEC-013** Section 5.1. Its representation of the pre-existing `WP-ERROR-017` was independently verified as accurate rather than a redefinition, its central claim about generic "Plugin category" citations across the repository was independently re-verified against every one of those citations' own actual text, and its disambiguation from Filesystem, Bootstrap, and Core Files was independently confirmed accurate. The one technical-accuracy issue found (IF-1, two unverifiable specific version-number attributions) was already corrected in the artifact as reviewed.

---

# 9. Gate Decision

Per **SF-SPEC-013** Section 5.1, entry authoring for the Plugin category (`WP-ERROR-031` and `032`) may now begin — this taxonomy exists, declares the category's boundary with independently-verified accuracy (including accurate treatment of the pre-existing `WP-ERROR-017`), enumerates every planned entry, and documents rejected/deferred candidates, and has been independently reviewed per this project's established practice.

---

# 10. Remaining Risks

- This review was conducted by the same class of agent (Claude Code) as the authoring pass.
- Section 2's central claim — that generic "Plugin category" citations describe an attribution convention rather than a catalog obligation — is a novel interpretive judgment for this project (no prior taxonomy has had to make this determination, since every other category's forward-references were either absent or ID-specific). If drafting `WP-ERROR-031`/`032`, or a future category's own consistency review, surfaces a case where a generic citation genuinely did anticipate a specific, missing entry this taxonomy failed to plan for, that should surface as a finding rather than being silently absorbed.
- The Section 5 "Candidates Considered and Rejected/Deferred" list folds two sub-conditions (requirement-gating, automatic-update rollback) into the two planned entries rather than giving them separate IDs; if either sub-condition proves to have a materially different diagnostic or recovery path once actually drafted, that granularity decision should be revisited during that entry's own author review rather than forced to fit.
- IF-1's underlying uncertainty (the exact WordPress core version that introduced the activation-time fatal-error guard) remains genuinely unresolved, not merely deferred — this taxonomy avoids asserting it rather than resolving it; any future entry documenting this mechanism should independently verify the actual originating version before citing one, rather than treating this taxonomy's own omission as evidence no such citation is possible.

---

# 11. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-14 | Initial independent review of SF-TAXONOMY-005. One Minor finding (IF-1: two unverifiable specific WordPress-core version-number attributions, corrected during drafting and independently re-confirmed here) recorded for documentation completeness. Approved. Entry authoring for WP-ERROR-031 and 032 may begin. | Approved |
