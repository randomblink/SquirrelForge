# Staged Release Rollout

`bin/rollout-release.sh` promotes one admitted image digest through a pre-created canary Deployment and then the stable Deployment.

## Preconditions

- The candidate is an immutable `repository@sha256:digest`.
- The stable Deployment already uses an immutable digest.
- `composer admission:verify` can authenticate to the registry and attestation services.
- The stable and canary Deployments use the same named container.
- The canary begins at zero replicas and has an isolated route used only by rollout probes.
- Stable and canary readiness endpoints return non-success status while unhealthy.
- Stable and canary error-rate endpoints return a plain numeric percentage.
- The operator has only the Kubernetes permissions required to read, update, scale, and watch these two Deployments.

The example canary manifest is structural guidance. Supply environment configuration, Secret, persistent volume, network policy, Service, and route resources through the deployment platform. Do not send production workflow traffic to a canary that uses an isolated SQLite database.

## Promotion sequence

1. Read the exact current stable image as the rollback candidate.
2. Run full admission verification on both candidate and rollback digests.
3. Set the canary image, scale it to the configured replica count, and wait for Kubernetes rollout readiness.
4. Sample provider readiness and application error rate for the configured soak window.
5. Halt and scale the canary to zero when either failure threshold is exceeded.
6. Set the stable image to the candidate digest and wait for Kubernetes rollout readiness.
7. Sample stable readiness and error rate using the same policy.
8. Scale the canary to zero and report success.

## Automatic rollback

If stable promotion begins but rollout status, readiness, or error-rate policy fails, the exit trap:

- scales the canary to zero,
- restores the previously admitted stable digest,
- waits for the rollback rollout,
- and exits unsuccessfully so higher-level deployment automation cannot report success.

Rollback is possible only because the prior stable image is required to be immutable and pass the same admission policy before promotion begins. A mutable or unverifiable current image blocks rollout before any change.

## Configuration and execution

Start with the variable names in `rollout-policy.env.example`. Choose a probe count and interval that form a meaningful soak window. `SQUIRRELFORGE_ROLLOUT_MAXIMUM_PROBE_FAILURES` applies to the combined readiness and error-rate samples; `SQUIRRELFORGE_ROLLOUT_MAXIMUM_ERROR_RATE_PERCENT` is the maximum accepted percentage for each sample.

After exporting the rollout and admission policy values, run:

```sh
composer rollout:release
```

The success receipt contains candidate and rollback digests only. Preserve deployment-system logs and Kubernetes events through their observability owners.

## Protected release job

Release tags continue from publication into the `protected-rollout` job. The job targets the GitHub `production` environment; configure that environment with required reviewers, deployment-branch or tag protections, and the minimum Kubernetes credential secret. Workflow concurrency permits only one production release and never cancels an active rollout. This job promotes only the main Engine API image (`needs: publish`); the credential-provider image is built, scanned, and published by its own independent `credential-provider-gate`/`publish-credential-provider` jobs but has no staged-rollout counterpart yet — see `deploy/CREDENTIAL-PROVIDER-CONTRACT.md`.

The production runner is deliberately self-hosted and labeled `squirrelforge-production`. Its governed image must already contain reviewed versions of PHP, Kubernetes CLI, Curl, Awk, Cosign, GitHub CLI with attestation support, and Trivy. The job does not download mutable deployment tools.

Environment variables and secrets provide cluster identity, stable/canary names, health endpoints, metric endpoints, soak policy, and error thresholds. The runner credential must not have cluster-admin access.

The protected job preserves:

- `admission-receipt.json`, binding the candidate digest to passed signature, provenance, SBOM, and vulnerability controls;
- `rollout-receipt.json`, containing promoted, halted, or rollback status plus candidate, rollback, and active image references;
- `observation-receipt.json`, containing post-deployment status, expected image, and completed sample count;
- `rollback-receipt.json`, when observation required a separately verified rollback.

Receipts are uploaded for 90 days even when a step fails. They contain references and decisions, never Kubernetes credentials, provider credentials, tokens, response bodies, or configuration secrets.

Post-deployment observation rechecks the exact active digest, readiness, and error rate for a second configured window. A failed observation invokes `bin/rollback-release.sh`, which reruns admission on the prior digest, restores it, waits for rollout completion, confirms the active digest, and emits a rollback receipt. The workflow then fails so the release cannot be marked successful even when rollback succeeds.
