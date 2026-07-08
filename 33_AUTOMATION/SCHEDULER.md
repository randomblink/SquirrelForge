# SquirrelForge Scheduler

Version: 1.0.0
Status: Stable
Owner: Automation Maintainers
Depends On: approved schedule definitions, 28_RUNTIME-CONFIG, TRIGGER-MANAGER.md
Used By: AUTOMATION-MANAGER.md, TRIGGER-MANAGER.md
Last Updated: 2026-07-08

## Purpose

The Scheduler owns Automation-domain schedule records, recurrence calculation, due-time evaluation, and scheduled trigger requests.

## Responsibilities

- Register approved schedule definitions and stable schedule identifiers.
- Calculate one-time, recurring, interval, cron-style, delayed, calendar, maintenance-window, and blackout timing.
- Determine when a schedule becomes due.
- Prevent duplicate due-time emission within the schedule contract.
- Produce scheduled trigger requests and schedule-status references.
- Consume configured time zone, maintenance-window, and runtime configuration references.

## Boundary

The Scheduler does not execute workflows or tasks, make approval or authorization decisions, define retry/recovery policy, execute retries, cancel production work, perform recovery, own authoritative execution state, enforce governance policy, collect telemetry, or own audit/storage infrastructure.

Timeout and retry schedules may be represented when supplied by authoritative owners, but the Scheduler does not own the underlying timeout, retry, or recovery decision.

## Rule

A due schedule produces a trigger request; it does not itself authorize or execute the automation.