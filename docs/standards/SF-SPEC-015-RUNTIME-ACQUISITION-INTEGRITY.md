# SF-SPEC-015 — Runtime Acquisition and Integrity Verification Specification

## Document Information

**Document ID:** SF-SPEC-015

**Title:** Runtime Acquisition and Integrity Verification Specification

**Classification:** Engineering Specification

**Status:** Production Ready

**Version:** 1.0

**Owner:** SquirrelForge

---

# 1. Purpose

## 1.1 Objective

This specification defines how SquirrelForge acquires, verifies, records, caches, instantiates, and disposes of WordPress Core packages used to create runtime-verification environments. Its purpose is to ensure that a runtime verification begins only from an official, integrity-verified, reproducible input rather than an unverified download or an unrelated existing site.

The acquired runtime is environment evidence. It establishes the trustworthiness of the execution environment but does not, by itself, prove any claim about the behavior being verified.

---

# 2. Scope

## 2.1 Applies To

This specification applies to:

* WordPress Core release archives acquired for disposable runtime verification.
* Official WordPress Git checkouts proposed as an archive alternative.
* Locally cached WordPress Core packages reused by later verifications.
* Acquisition metadata and acquisition-failure records.
* The integrity gate that precedes extraction, installation, and runtime evidence collection.

## 2.2 Exclusions

This specification does not define:

* Runtime evidence sufficiency or execution conclusions, owned by **SF-SPEC-002 — Runtime Evidence Specification**.
* Evidence retention generally, owned by **SF-SPEC-011 — Evidence Governance Specification**.
* Repository validation, owned by **SF-SPEC-006 — Repository Validation Specification**.
* The WordPress scenario or verification matrix executed after acquisition succeeds.
* Installation architecture, database-engine selection, or test-fixture behavior after the verified Core package has been instantiated.
* Automation implementation. A future acquisition tool shall implement this specification; it shall not define or silently change the policy.

---

# 3. Specification Boundaries

## 3.1 Owns

* Acceptable WordPress Core acquisition sources and their priority.
* Pre-extraction integrity verification.
* Runtime-acquisition provenance metadata.
* Verified-package cache admission and periodic re-verification.
* Acquisition stop conditions and acquisition-failure classification.
* Separation between runtime acquisition and runtime verification.

## 3.2 Depends On

* **SF-SPEC-002 — Runtime Evidence Specification**, for the runtime baseline and execution requirements that apply after acquisition succeeds.
* **SF-SPEC-004 — Documentation Specification**, for accurate, traceable acquisition records.
* **SF-SPEC-005 — Engineering Review Specification**, for review of this specification and future substantive revisions.
* **SF-SPEC-006 — Repository Validation Specification**, for repository validation after governed documentation changes.
* **SF-SPEC-008 — Versioning Specification**, for specification versioning.
* **SF-SPEC-011 — Evidence Governance Specification**, for retention and disposal of acquisition records as environment evidence.
* **SF-SPEC-012 — Engineering Review Independence Specification**, for reviewer classification and independence.

## 3.3 Does Not Define

* Whether a WordPress behavior claim is confirmed or contradicted.
* How a disposable WordPress site is configured after its Core input passes this specification.
* How verification results alter certified knowledge.
* General software supply-chain policy outside WordPress runtime acquisition.

---

# 4. Engineering Principles

## 4.1 Provenance Before Execution

No runtime behavior shall be collected until the WordPress Core input has passed its applicable provenance and integrity gates.

## 4.2 Official Source Preference

The highest available official source tier shall be preferred. Convenience, prior local availability, or final file similarity shall not substitute for documented provenance.

## 4.3 Verify Before Extraction

An archive shall be verified while still immutable as a single downloaded file. Extraction shall not precede the archive-integrity decision.

## 4.4 Fail Closed

An incomplete download, checksum mismatch, unverifiable Git tag, missing commit, or ambiguous provenance shall stop acquisition. It shall not produce a degraded or provisional runtime.

## 4.5 Reproducible Cache

A cache is a reuse mechanism for a previously verified official package, not a new source of authority. Cache trust shall remain traceable to the original official source and shall be re-established before reuse.

## 4.6 Separation of Outcomes

“Runtime acquisition failed” and “runtime verification failed” are different outcomes. An acquisition failure means verification did not start and produces no runtime conclusion about the target knowledge.

---

# 5. Normative Requirements

The following requirements are mandatory for WordPress runtime acquisition within the SquirrelForge Engineering Framework.

## 5.1 Source Tiers

An acquired WordPress Core input shall originate from one of these tiers:

1. **Tier 1 — WordPress.org release archive:** the exact-version ZIP or tar.gz linked by the official WordPress.org Release Archive, together with the checksum WordPress.org publishes for that exact archive. Where WordPress.org offers both SHA-1 and MD5, SHA-1 shall be used as the official comparison value and MD5 may be recorded only as a secondary compatibility value.
2. **Tier 2 — Official WordPress Git repository:** an exact release tag from the official WordPress repository, accepted only when the tag resolves to a recorded commit, repository object integrity passes, and the tag's authenticity can be verified by an official signature mechanism available for that tag. An empty checkout, unresolved tag, unsigned tag presented as signed, or signature failure shall be rejected.
3. **Tier 3 — Locally cached official package:** a byte-for-byte cached Tier 1 archive whose original official URL, published checksum, verified checksum result, locally calculated SHA-256 fingerprint, acquisition date, and metadata record are preserved. Tier 3 does not permit a locally sourced package to acquire authority merely because a local hash was calculated for it.

If no tier satisfies its complete gate, acquisition shall stop.

## 5.2 Rejected Sources

The following shall not be used as WordPress Core inputs for governed runtime verification:

* Third-party mirrors or forks.
* Unversioned or random archives.
* GitHub-generated source snapshots whose release tag and authenticity were not verified.
* Partial Git repositories containing no requested commit.
* Hospital, Thematic, or any other pre-existing WordPress site.
* A package whose final files resemble an official release but whose acquisition provenance is unknown.
* A checksum calculated only after download with no independent official or previously verified value against which to compare it.

## 5.3 Archive Integrity Gate

Before extracting a Tier 1 or Tier 3 archive, the acquisition process shall:

1. Download or copy the archive into a staging location separate from the eventual runtime.
2. Confirm the transfer completed successfully and the archive exists as a non-empty regular file.
3. Obtain the exact archive's published checksum from the official WordPress.org Release Archive for Tier 1, or from the preserved verified metadata for Tier 3.
4. Calculate the same checksum algorithm over the staged archive and compare the complete normalized values exactly.
5. Calculate SHA-256 over the staged archive as SquirrelForge's local immutable cache fingerprint.
6. Test the archive container for structural readability without extracting it into the runtime destination.
7. Record the result before extraction.

An official checksum mismatch, unreadable container, incomplete transfer, missing expected checksum, or disagreement with the cached SHA-256 shall fail the gate. The staged input shall be deleted, and no extraction or runtime verification shall occur.

The locally calculated SHA-256 strengthens cache-integrity detection but shall not be described as an official WordPress.org checksum unless WordPress.org itself published that value for the exact archive.

## 5.4 Git Integrity Gate

Before a Tier 2 checkout may become a runtime input, the acquisition process shall:

1. Confirm the remote is the official WordPress repository.
2. Fetch the exact requested release tag without substituting a branch, moving default, or similarly named reference.
3. Confirm the tag resolves to a non-zero recorded commit object.
4. Verify the tag's official signature using the signing identity and mechanism applicable to that release.
5. Run Git object-integrity validation over the acquired repository.
6. Record the tag, resolved commit identifier, remote URL, signature result, and integrity result.
7. Create the disposable working tree from the verified commit in detached state.

Failure of any step shall reject the checkout. A transport-created `.git` directory with no commit is an acquisition failure, not a partial success.

## 5.5 Provenance Record

Every successful acquisition shall create a machine-readable provenance record named `runtime.json` before runtime installation begins. It shall contain, at minimum:

* Schema version for the record.
* WordPress version and locale.
* Source tier and source type.
* Exact source URL or Git remote.
* Archive filename or Git tag and resolved commit.
* Official checksum algorithm, expected value, calculated value, and comparison result where applicable.
* Locally calculated SHA-256 fingerprint for archives.
* Signature identity and verification result for Git acquisition where applicable.
* Acquisition timestamp in UTC.
* Verification tool names and versions.
* Cache path or disposable staging path.
* Overall acquisition status.

Fields that do not apply to the chosen tier shall be represented explicitly as not applicable rather than omitted ambiguously.

The provenance record is environment evidence. A `verified: true` field records that this specification's acquisition gate passed; it does not assert that a later runtime verification succeeded.

## 5.6 Cache Admission

Only a Tier 1 archive that passed Section 5.3 may be admitted to the persistent runtime cache. Its archive, official checksum value, locally calculated SHA-256, and `runtime.json` shall be stored together under a version-and-locale-specific cache entry.

The cache shall not contain extracted mutable WordPress installations as authoritative inputs. Every disposable runtime shall be created from the verified immutable archive or verified Git commit.

The persistent cache shall be outside the SquirrelForge Git repository and outside any disposable runtime directory. Its location shall be recorded, configurable, and free of production-site content.

## 5.7 Cache Re-verification

Before every cache reuse, the acquisition process shall recalculate the archive's SHA-256 and compare it to the admitted fingerprint. It shall also confirm that the preserved official checksum and source metadata remain present.

At a documented periodic review, and whenever tampering, corruption, or metadata loss is suspected, the official checksum shall be independently reacquired from WordPress.org and compared again. A failed comparison or incomplete provenance record shall revoke cache trust and delete or quarantine the cache entry before any runtime is created.

## 5.8 Disposable Runtime Instantiation

After acquisition succeeds, the verified input may be copied into a disposable staging directory and extracted or checked out there. The process shall then:

* Confirm the installed Core reports the requested WordPress version.
* Apply WordPress file-level checksum verification where available as a post-extraction negative check.
* Record PHP, WP-CLI, database, operating-system, locale, and supporting-component versions used by the runtime.
* Keep the runtime isolated from SquirrelForge, Hospital, Thematic, and every other existing site.
* Establish a healthy control before fault injection.

Post-extraction Core file checks supplement but do not replace archive or Git provenance verification.

## 5.9 Supporting Components

Every separately acquired supporting component used to construct the disposable environment, including WP-CLI or a database-integration plugin, shall have its official source, exact version, acquisition result, and available integrity mechanism recorded. A component shall not be represented as cryptographically verified when its publisher provides no independent signature or checksum for the acquired artifact.

If a supporting component lacks a sufficient integrity mechanism for the verification's required assurance level, runtime acquisition shall stop or the component shall be replaced by an environment dependency whose provenance can be established without weakening the WordPress Core gate.

## 5.10 Acquisition Failure Record

Every failed acquisition attempt that was intended to start a governed runtime verification shall be recorded in the repository's runtime-acquisition log with:

* Date and target verification.
* Requested WordPress version and source tier.
* Source URL or Git remote and tag.
* Failed gate step.
* Direct observed failure, such as transfer truncation, signature failure, checksum mismatch, missing commit, or archive unreadability.
* Cleanup result.
* Explicit statement that runtime verification did not start and no runtime conclusion was produced.

Failed archives, empty checkouts, credentials, and disposable staging directories shall be removed after the failure is recorded. Failure records shall not preserve the invalid package itself unless a security investigation explicitly requires quarantined retention under **SF-SPEC-011**.

## 5.11 Stop Conditions

Runtime acquisition shall stop before extraction or execution when any of the following occurs:

* No acceptable official source is reachable.
* Transfer completion cannot be established.
* Expected official checksum or signature evidence is unavailable for the selected tier.
* A checksum or signature does not match.
* The requested Git tag does not resolve to a complete verified commit.
* Archive structure is unreadable.
* Cache metadata is absent, ambiguous, or inconsistent.
* The proposed source is a rejected source under Section 5.2.

No exception may be inferred from schedule pressure or from the availability of an existing WordPress site.

## 5.12 Verification Start Gate

A `WP-VERIFICATION-XXX` runtime shall be considered started only after:

* Acquisition status is recorded as successful.
* The applicable archive or Git gate passed.
* `runtime.json` exists and is complete.
* The disposable runtime reports the requested WordPress version.
* A healthy control succeeds.

Events before that point belong to runtime acquisition. They shall not be included as target-behavior observations or described as a failed `WP-VERIFICATION-XXX` execution.

## 5.13 Cleanup and Retention

At acquisition failure, the invalid or partial staged input and disposable directory shall be removed. After runtime verification, the extracted runtime, temporary database, credentials, fixtures, and copied archive shall be removed according to the verification's cleanup procedure.

The verified persistent cache entry, its official checksum metadata, local SHA-256, and successful `runtime.json` may be retained for reuse. Acquisition logs and provenance records retained as environment evidence are governed by **SF-SPEC-011**.

---

# 6. Quality Criteria

Runtime acquisition under this specification shall be:

* Official-source based.
* Integrity verified.
* Reproducible.
* Traceable.
* Fail-closed.
* Isolated.
* Disposable at execution time.

---

# 7. Production Ready Definition

A runtime-acquisition process shall not be designated **Production Ready** until:

* Every applicable Section 5 requirement has been satisfied.
* At least one successful acquisition has passed the selected source tier's full gate.
* At least one acquisition failure has demonstrated fail-closed cleanup without starting runtime verification.
* Provenance and failure records are complete and reviewable.
* Cache admission and reuse, if the process supports caching, have been verified without modifying the cached archive.
* Engineering review has completed under **SF-SPEC-005** and **SF-SPEC-012**.

This specification itself may be Production Ready before a process implementation is designated Production Ready. No acquisition script or cache is designated by this document's status.

---

# 8. Engineering Review Checklist

Every runtime-acquisition process shall satisfy the following checklist before it may be designated Production Ready.

* ☐ Official source tier identified
* ☐ Exact version and locale fixed
* ☐ Integrity evidence independently sourced
* ☐ Verification completed before extraction
* ☐ Local SHA-256 cache fingerprint recorded
* ☐ Provenance record complete
* ☐ Stop conditions tested
* ☐ Failure cleanup verified
* ☐ Cache admission and reuse verified where applicable
* ☐ Disposable runtime isolated from existing sites
* ☐ Healthy control completed before verification start
* ☐ Engineering review completed

---

# 9. Change Control

This specification shall not be modified to accommodate an individual download failure, WordPress version, or verification record.

The specification shall be revised only when an engineering improvement benefits the SquirrelForge runtime-acquisition process as a whole or when an official source's real integrity mechanism changes.

All revisions shall:

* Be versioned.
* Be reviewed.
* Be documented.
* Preserve backward compatibility where practical.
* Identify affected acquisition records, cache entries, and verification prerequisites.

---

# 10. Reference Implementations

No Reference Implementation is currently designated. The failed acquisition attempts preceding `WP-VERIFICATION-009` demonstrate the need for this specification and its fail-closed principle, but they are not a successful implementation. A future process may be designated only after it has been verified against every applicable requirement in Sections 5–8.

---

# 11. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-17 | Initial specification. Formalizes official-source tiers, pre-extraction integrity gates, provenance records, cache admission and re-verification, acquisition-failure records, stop conditions, disposable instantiation, and the boundary between acquisition failure and runtime verification. | Production Ready — reviewed via SF-REVIEW-200/201 |
