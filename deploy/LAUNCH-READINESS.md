# Production Launch Readiness

This gate consolidates authoritative evidence into one machine-readable `READY` or `NOT_READY` decision. It does not replace protected-environment approval, operator judgment, or any owning control.

## Required evidence

Assemble one isolated evidence directory containing:

1. admission receipt for the exact candidate digest;
2. successful rollout receipt with the exact candidate, active, and rollback digests;
3. successful post-deployment observation receipt;
4. current production-health receipt;
5. current verified backup plus its manifest;
6. successful restore-drill receipt for that backup lineage; and
7. passing capacity receipt for the approved profile.

Copy `launch-evidence.example.json` into that directory, replace all references and SHA-256 values, and identify the release, approved change, protected-environment approval, and accountable release, on-call, recovery, and security owners. Evidence paths must be relative, must remain inside the evidence directory, and must not contain secrets.

Generate admission evidence by setting `SQUIRRELFORGE_ADMISSION_RECEIPT_PATH` when running `composer admission:verify`. Other receipts are emitted by their existing rollout, observation, health, backup/restore, and capacity commands.

## Decision

Set:

- `SQUIRRELFORGE_LAUNCH_MANIFEST_PATH`;
- `SQUIRRELFORGE_LAUNCH_RECEIPT_PATH`; and
- any approved evidence-age overrides.

Run `composer launch:evaluate`. The evaluator:

- verifies every manifest checksum;
- prevents absolute paths and parent-directory traversal;
- checks required receipt statuses and freshness;
- binds admission, rollout, observation, and health evidence to the same candidate digest;
- binds the rollout rollback digest to the declared rollback image;
- verifies the backup artifact against its manifest;
- binds the restore drill to the backup checksum;
- requires named ownership and approval references; and
- emits a checksum-bound launch decision receipt.

Configuration errors exit with code 2 and do not create a decision. Complete but unacceptable evidence emits `NOT_READY` with stable failure codes and exits with code 1. Only fully consistent evidence emits `READY` and exits successfully.

Default maximum ages are:

| Evidence | Maximum age |
| --- | --- |
| Admission, rollout, observation | 24 hours |
| Production health | 15 minutes |
| Verified backup | 24 hours |
| Restore drill | 90 days |
| Capacity result | 30 days |

Tighten these values to satisfy approved RPO, change, and operational policies. Do not loosen them merely to make stale evidence pass.

## Operator checklist

Before opening production traffic, confirm:

- protected-environment reviewers approved the exact change;
- the candidate and rollback images remain admitted by immutable digest;
- rollout and observation completed without an unresolved rollback;
- current health and error-budget signals are acceptable;
- backup export, off-site verification, and restore drill satisfy recovery objectives;
- the tested capacity limit exceeds forecast peak plus safety margin;
- dashboards, paging routes, and incident ownership are active;
- short-lived test identities and keys are revoked;
- release, on-call, recovery, and security owners acknowledge the launch window;
- rollback authority and stop conditions are explicit; and
- the `READY` receipt is stored with the change record.

Any unresolved exception, missing owner, stale evidence, image mismatch, failed checksum, or policy waiver without its own approved record is `NOT_READY`.

## Launch and stabilization

The launch decision is a point-in-time authorization, not permanent health. Continue the post-launch observation window defined by `deploy/OBSERVABILITY.md`. If a stop condition fires, freeze traffic growth and follow `deploy/ROLLOUT.md` or `deploy/BACKUP-RECOVERY.md`. Preserve the launch, rollout, rollback, health, and recovery receipts in the incident or change record.
