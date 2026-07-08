Status: Stable

---
# SquirrelForge WordPress Cron Knowledge

## Purpose

Defines knowledge for scheduled work, cron events, recurring tasks, retries, batching, locking, and cleanup.

## Review Areas

Review event registration, duplicate prevention, activation scheduling, deactivation cleanup, recurrence, workload size, batching, overlap prevention, failure handling, retries, logging, and performance impact.

## Rule

WordPress cron work must be bounded, recoverable, non-duplicative, and safe to run repeatedly.
