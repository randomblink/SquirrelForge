# SF-REVIEW-169 — Repository Hygiene Phase 2

## Status

Draft — evidence collection in progress.

## Purpose

This review records the second repository-hygiene pass after the structural cleanup merged in PR #4. It separates evidence collection from structural changes and does not authorize moving, renaming, merging, or deleting repository content without a specific finding.

## Scope

The review examines:

- root-level entry points and metadata;
- ignored and tracked generated files;
- obsolete or contradictory repository guidance;
- stale roadmap or release-readiness statements;
- unused scripts and duplicate authorities;
- documentation index completeness;
- remaining automation opportunities.

## Constraints

- Certified `WP-ERROR` knowledge is out of scope unless a direct repository-hygiene defect is proven.
- Historical review evidence shall not be rewritten merely for consistency.
- Files shall not be removed because they appear old; obsolescence requires an authoritative replacement or proof that no live dependency remains.
- Structural changes require separate, focused commits.

## Baseline

- Source branch: `main`
- Preceding hygiene work: PR #4, "Improve repository hygiene and documentation navigation"
- Repository validator, PHPUnit, PHP syntax, and whitespace checks were reported clean at the conclusion of PR #4.

## Initial Findings

### RH2-001 — Public repository has no operative license

The root `README.md` states that `LICENSE` exists but is empty. The repository is public, so readers can inspect the code but do not receive an explicit grant to copy, modify, or redistribute it.

Classification: **Decision required**

Recommended resolution: choose and add the intended open-source or commercial license. This review does not choose a license on the owner's behalf.

### RH2-002 — Dependency lock files are globally ignored

The root `.gitignore` ignores both `composer.lock` and `package-lock.json`. Whether that is correct depends on whether SquirrelForge is maintained as a reusable library, a deployable application, or both. The current README describes a working runtime and future production deployment support, so ignoring reproducible dependency locks may be inconsistent with the application side of the repository.

Classification: **Architecture decision required**

Recommended resolution: document the dependency-management policy, then retain or remove these ignore rules accordingly. Do not change them without that decision.

## Actions Taken

- Created this evidence record.
- Made no structural repository changes.
- Made no certified knowledge changes.

## Next Evidence Pass

1. Inspect root entry points and manifests for contradictions.
2. Search for tracked generated files and missing ignore rules.
3. Identify scripts with no live callers.
4. Compare documentation indexes against current artifacts.
5. Classify every finding before applying any cleanup.
