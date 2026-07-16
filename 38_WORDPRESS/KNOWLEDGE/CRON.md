Status: Stable

---
# SquirrelForge WordPress Cron Knowledge

## Purpose

Defines knowledge for scheduled work, cron events, recurring tasks, retries, batching, locking, and cleanup.

## Review Areas

Review event registration, duplicate prevention, activation scheduling, deactivation cleanup, recurrence, workload size, batching, overlap prevention, failure handling, retries, logging, and performance impact.

## Output

This Knowledge file must support:

- cron implementation guidance;
- scheduled-event review;
- workload and batching assessment;
- failure-handling notes;
- and cleanup requirements.

## Validation Requirements

Cron guidance is valid only when:

- event registration avoids duplicates;
- activation and deactivation behavior is defined;
- recurrence is appropriate and documented;
- workload is bounded and batchable when needed;
- overlap prevention or locking is considered;
- failures and retries are safe;
- cleanup removes obsolete scheduled events;
- observability/logging expectations are defined;
- and performance impact is reviewed.

## Handoff Rules

- Cron implementation issues route to the relevant PHP implementation role.
- Performance-sensitive scheduled workloads route to `38_WORDPRESS/ROLES/PERFORMANCE-ENGINEER.md`.
- Security-sensitive scheduled actions route to `38_WORDPRESS/ROLES/SECURITY-ENGINEER.md`.
- Documentation changes route to `38_WORDPRESS/ROLES/DOCUMENTATION-ENGINEER.md`.

## Completion Criteria

This Knowledge file is complete when scheduled work can be reviewed for registration, recurrence, bounded execution, cleanup, failure handling, observability, and performance impact.

## Rule

WordPress cron work must be bounded, recoverable, non-duplicative, and safe to run repeatedly.
