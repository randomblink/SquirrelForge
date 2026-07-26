# Production Capacity and Resilience

This contract establishes measured operating limits for the reference single-node Flock Engine. It does not certify clustered operation.

## Architectural boundary

The reference container uses PHP's single-process development server and one SQLite database for Engine state, authentication, authorization, security events, and provider telemetry. SQLite serializes writers, and the reference deployment has no distributed lock, shared session store, or multi-node coordination contract.

Therefore:

- do not horizontally scale the reference container;
- do not route concurrent writers to separate SQLite copies;
- treat sustained database lock time, queue growth, latency growth, CPU saturation, memory pressure, storage latency, and provider circuit opening as saturation signals; and
- replace the server and SQLite-backed components with governed production implementations before claiming multi-node capacity.

## Approved load profiles

Start with `capacity-policy.env.example`. Its values are examples, not production commitments. Define and approve at least:

1. **Baseline:** expected ordinary traffic for regression comparison.
2. **Target:** forecast peak traffic plus the approved safety margin.
3. **Stress:** incremental concurrency beyond target to identify the first breached objective.
4. **Soak:** target concurrency for long enough to expose memory, storage, session, telemetry, or provider degradation.

Record virtual users, duration, request mix, dataset state, image digest, infrastructure allocation, provider behavior, and acceptance limits for every profile. Change one material variable at a time when comparing runs.

## Executable workflow profile

Run only from an isolated, governed load generator with k6 installed. Provision a dedicated short-lived service identity with only `engine.submit` and `engine.result`. Inject its API key through the runner's secret mechanism.

`composer capacity:run` exercises the real sequence:

1. provider readiness;
2. API-key session creation;
3. authorized Engine submission; and
4. validated result retrieval.

The script never prints the API key or access token. Use an isolated capacity environment by default. Production load tests require an approved change window, incident owner, abort authority, and confirmed backup.

After k6 writes `capacity-summary.json`, run `composer capacity:evaluate`. The evaluator independently enforces maximum request-failure rate, p95 latency, minimum workflow-check rate, and minimum completed iterations. It emits a checksum-bound `capacity-receipt.json` with `PASSED` or stable failure codes.

## Acceptance and stop rules

A profile passes only when its receipt is `PASSED` and the following remain healthy for the entire observation window:

- readiness and provider circuit state;
- error budget and p95 latency;
- CPU, memory, process availability, and storage latency;
- SQLite busy/locked failures and persistent-volume capacity;
- authentication throttling and security-event delivery;
- backup freshness; and
- recovery after load removal.

Abort immediately for data-integrity errors, unexpected authorization results, image drift, credential exposure, sustained readiness loss, storage exhaustion, uncontrolled queue growth, or an incident declaration. A stress test finding the breaking point is successful evidence only when the environment recovers without data loss; it is not a production capacity approval.

## Failure-injection matrix

Perform failure injection only in an isolated environment unless separately authorized:

| Failure | Expected behavior | Evidence |
| --- | --- | --- |
| Credential provider latency | Bounded retries, rising latency, then controlled failure. | Provider telemetry aggregates and capacity receipt. |
| Credential provider outage | Circuit opens; readiness and requests fail closed without secret leakage. | Health receipt, circuit events, and redacted logs. |
| Engine process termination | Orchestrator restarts one instance; persistent state remains valid. | Kubernetes events, readiness recovery, smoke result. |
| Persistent-volume latency | Latency objective breaches before corruption; stop rule activates. | Storage metrics, SQLite errors, failed capacity receipt. |
| Persistent-volume exhaustion | Pre-alert and abort occur before writes consume reserve. | Capacity alert, abort timestamp, integrity check. |
| Backup metric loss | Production health fails with `BACKUP_AGE_UNAVAILABLE`. | Health receipt and routed alert. |
| Release regression | Observation fails and verified rollback restores the admitted digest. | Rollout, observation, and rollback receipts. |

Never inject database corruption, destructive storage loss, credential disclosure, or uncontrolled network partitions into production. Data-loss recovery belongs to `deploy/BACKUP-RECOVERY.md`.

## Capacity decision

The approved production limit is the highest tested target that passes all objectives with its safety margin. Preserve raw summary, capacity receipt, image digest, policy values, dashboards, and operator notes together. Expire the approval after material code, schema, infrastructure, provider, traffic-shape, or objective changes.

Capacity is not inferred from one successful smoke test, one quiet dashboard, or average latency. It is established by repeatable profiles, bounded failure behavior, and verified recovery.
