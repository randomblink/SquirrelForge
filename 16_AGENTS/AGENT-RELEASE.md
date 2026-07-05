# SquirrelForge Agent Release

Version: 1.0.0
Status: Stable
Owner: Agent Maintainers
Depends On: `16_AGENTS/AGENT-DOCUMENTATION.md`, `16_AGENTS/AGENT-SECURITY.md`, `16_AGENTS/AGENT-PERFORMANCE.md`, `23_GOVERNANCE`, `src/Agent/Roles/ReleaseAgent.php`
Used By: Governance, Coordination, Reporting
Last Updated: 2026-07-05

## Purpose

The Agent Release is the terminal stage of the pipeline. It verifies that every required quality gate — review, security, performance, and documentation — actually passed, rather than assuming a prior stage's approval, and reports the release as `Ready` or `Hold`.

When all gates pass and release actions are explicitly enabled, it performs the real, externally consequential release steps: finalizing the changelog, bumping the version file, and committing, tagging, and pushing. This gate-check always runs and never touches disk or a process on its own; release actions are a separate, deliberate opt-in.

---

## Responsibilities

The Agent Release must:

- read the accumulated pipeline history for the review, security, performance, and documentation stages,
- independently verify each required gate rather than trusting a downstream claim of "approved",
- determine `Ready` or `Hold` from the gate results and report which gates are outstanding,
- confirm the release-actions opt-in policy before performing any externally consequential action,
- refuse to run release actions against an unclean working tree,
- when enabled, finalize the changelog, bump the version file, and commit, tag, and push in a fixed order, stopping at the first failed step,
- downgrade the reported status to `Hold` if any release action fails,
- and never perform release actions without an explicit release version supplied by the caller.

---

## Inputs

The Release stage should receive:

- prior stage history for review, security, performance, and documentation,
- the release-actions enablement policy,
- an explicit `release_version` when release actions are requested,
- file system and command runner access when release actions are requested,
- and current project and governance context.

Release actions must not run when the gate-check has not passed, when the actions policy is disabled, when required tooling was not supplied, or when no release version was given.

---

## Outputs

The Release stage should produce:

- the release status (`Ready` or `Hold`),
- the gate-by-gate pass/fail breakdown,
- the list of outstanding gates, if any,
- the release action step log, when release actions ran,
- and the resulting tag, changelog, and version state.

---

## Release Process

1. Read prior stage history for review, security, performance, and documentation.
2. Evaluate each required gate independently.
3. Determine `Ready` or `Hold` from the gate results.
4. If `Ready`, release actions are enabled, required tooling was supplied, and a release version was given, proceed to release actions; otherwise report the current status without touching disk or a process.
5. Verify the working tree is clean before making any change; abort release actions immediately if it is not.
6. Finalize the changelog's unreleased section under the release tag.
7. Bump the version file to the release version.
8. Run add, commit, tag, push, and push-tags in order, stopping at the first failed step.
9. If any step fails, downgrade the status to `Hold` and record the failure.
10. Record gates, outstanding items, and action results.

---

## Quality Gates

The current implementation (`src/Agent/Roles/ReleaseAgent.php`) checks these literal stage-history status values:

| Stage | Required Status |
|---|---|
| Review | `Approved` |
| Security | `Approved` or `Warning` |
| Performance | `Approved` or `Warning` |
| Documentation | `Complete` |

Any gate not in a passing state is added to the outstanding list and the release is held.

---

## Known Inconsistency

The Reviewer, Security, Performance, and Documentation role specifications were revised as part of this cleanup pass to use explicit outcome states (for example `APPROVED`, `APPROVED_WITH_LIMITATIONS`, `REMEDIATION_REQUIRED`, `OPTIMIZATION_RECOMMENDED`, `VALIDATION_REQUIRED`, `COMPLETE`, `COMPLETE_WITH_LIMITATIONS`).

`src/Agent/Roles/ReleaseAgent.php` has not been updated to recognize these values — it still checks only the literal strings in the Quality Gates table above. Until the implementation is updated, a stage reporting one of the newer outcome states (such as `APPROVED_WITH_LIMITATIONS` or `OPTIMIZATION_RECOMMENDED`) will be treated as a failed gate rather than a pass, even though the role specification now considers it an acceptable outcome. This should be resolved by updating the gate-check implementation, not by silently reverting the role specifications.

---

## Release Actions

| Step | Behavior |
|---|---|
| Working tree check | Aborts the entire sequence with no other command run if the tree is not clean. |
| Finalize changelog | Replaces the unreleased section header with the release tag and date. |
| Bump version file | Overwrites the version file with the bare release version. |
| Commit, tag, push | Runs in fixed order; any non-zero exit stops the sequence and reports `Failed`. |

Release actions require all of: a passed gate-check, injected file system and command runner access, the actions-enabled policy, and an explicit release version — the same level of trust already extended to the caller-supplied changelog content and tag name.

---

## Permission Boundary

The Release stage may read prior stage history, evaluate gates, and — only when explicitly enabled — perform the fixed changelog, version, and git sequence above.

It must not perform any other execution, must not bypass the actions-enabled policy, must not run release actions on an unclean working tree, and must not infer a release version when the caller did not supply one.

---

## Domain Rule

For WordPress plugin or theme releases, applicable `38_WORDPRESS` packaging and versioning conventions must be followed in addition to the gates above.

For non-WordPress releases, WordPress-specific packaging must not be assumed.

---

## Rule

> A release may only be marked `Ready` when every required quality gate has independently passed. Release actions may run only when explicitly enabled, fully equipped, and given a release version — and any failed step must downgrade the release to `Hold` rather than continue.
