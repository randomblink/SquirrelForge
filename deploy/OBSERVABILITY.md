# Production Observability and Incident Response

This contract covers the persistent single-node Flock Engine deployment. Monitoring must remain useful during failure, must not expose request content or credentials, and must not report success from an unverified proxy signal.

## Service-level indicators

The deployment platform must collect at least:

| Indicator | Source | Good event |
| --- | --- | --- |
| Provider readiness | `GET /v1/health/providers` | Successful response whose redacted body reports `ready: true`. |
| Request availability | Edge or application request metrics | Authorized Engine requests complete without a server error. |
| Request latency | Edge or application duration histogram | Request completes within the approved latency objective. |
| Validation outcome | Aggregated Engine decision metric | Decision is recorded; rejection classes remain separately observable. |
| Provider reliability | Aggregated `provider_telemetry` events | Success, retry, failure, and open-circuit rates remain within policy. |
| Release identity | Kubernetes Deployment image | Active image exactly matches the admitted digest. |
| Recovery readiness | Verified off-site backup metric | Newest independently verified backup age remains within RPO. |

Define service-level objectives and paging thresholds in the deployment platform. The repository does not invent business commitments. Error-budget windows must be long enough to prevent one transient sample from being treated as sustained unavailability.

## Continuous production check

Start with `observability-policy.env.example`, inject the currently admitted digest, and run `composer health:production` from a governed read-only monitoring identity. The Kubernetes credential requires only permission to read the named Deployment. Metric URLs must expose single numeric values for error-rate percentage and independently verified off-site backup age in seconds.

The command checks:

1. active image identity;
2. redacted provider readiness;
3. application error-rate threshold; and
4. verified-backup freshness.

It emits `HEALTHY` only when every signal passes. A JSON receipt optionally records bounded values and stable failure codes: `DEPLOYMENT_UNAVAILABLE`, `IMAGE_DRIFT`, `READINESS_FAILED`, `ERROR_RATE_UNAVAILABLE`, `ERROR_RATE_EXCEEDED`, `BACKUP_AGE_UNAVAILABLE`, and `BACKUP_STALE`. Response bodies, tokens, request payloads, database paths, and Kubernetes credentials are never included.

Run this check frequently enough to satisfy detection objectives. Store receipts with bounded retention in the observability system; a local receipt alone is not monitoring.

## Alert routing

Page the primary on-call operator for sustained readiness failure, deployment unavailability, image drift, or error-budget burn. Page the recovery owner before backup age reaches RPO. Route isolated validation rejections and provider retries to investigation queues unless their rate breaches an approved threshold.

Every page must include:

- environment and service identity;
- first-seen and latest-seen times;
- failure code and breached threshold;
- active and expected image digests when relevant;
- links to dashboards, deployment receipts, and the incident record; and
- the safe first diagnostic action.

Alerts must not include credentials, provider responses, request or model content, session material, raw database rows, or secrets.

## Incident procedure

1. Acknowledge the page and create one incident record with an owner, severity, and timestamps.
2. Confirm the signal from a second governed vantage point. Do not suppress a page solely because one dashboard is unavailable.
3. Freeze unrelated deployments and preserve rollout, observation, rollback, health, and backup receipts.
4. Compare the active image with the last admitted digest. If drift exists, treat it as a security and change-control incident.
5. Check provider readiness, error-budget burn, Kubernetes events, replica state, and dependency status without retrieving sensitive response bodies.
6. Mitigate using the smallest verified action. For release regressions, follow `deploy/ROLLOUT.md`; for data loss or corruption, follow `deploy/BACKUP-RECOVERY.md`.
7. After mitigation, require sustained readiness and error-rate recovery plus exact image verification before resolving.
8. Record detection time, acknowledgement time, mitigation time, recovery time, affected objectives, and every authoritative receipt.
9. Rotate exposed credentials immediately if evidence suggests disclosure. Do not copy secrets into the incident system.
10. Complete a blameless review with corrective owners and due dates when severity or policy requires it.

## Diagnostic boundaries

Prefer aggregate counts, rates, opaque references, and timestamps. Access to raw security events or execution records requires separate authorization and must be audited. Health endpoints must remain redacted and non-cacheable. Debug modes that serialize configuration, environment variables, headers, provider bodies, or database content are prohibited in production.

## Recovery confirmation

An incident is resolved only when the relevant service objectives are again satisfied, the production check returns `HEALTHY` across the approved stability window, and any rollback or restore has its own verified receipt. Alert silence by itself is not recovery evidence.
