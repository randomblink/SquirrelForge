# Production Deployment

The reference container runs the persistent single-node Engine API. Clustered deployment requires replacing SQLite runtime, session, authorization, and telemetry storage with governed shared-state implementations.

## Required configuration

Use `production.env.example` as a names-only template. Inject `SQUIRRELFORGE_CREDENTIAL_PROVIDER_TOKEN` from the deployment platform's secret store at runtime; do not place it in an image layer, source file, environment template value, command argument, log, or health response.

Production startup requires:

- `SQUIRRELFORGE_ENVIRONMENT=production`
- a writable `SQUIRRELFORGE_ENGINE_DB` path on a persistent volume
- `SQUIRRELFORGE_CREDENTIAL_PROVIDER=http-json`
- an HTTPS provider URL
- a provider token containing at least 32 characters
- a healthy external provider response

Bootstrap identity, key, and permission variables are ignored in production.

## Startup sequence

The container entrypoint runs `bin/runtime-preflight.php` before accepting traffic. Preflight validates configuration and provider readiness, creates or migrates the SQLite schemas through their owning runtime components, and fails the process if any prerequisite is unavailable.

The container health check calls `GET /v1/health/providers`. The response is intentionally redacted and non-cacheable.

## Smoke test

Run the smoke test from a trusted operator environment after provisioning a dedicated, short-lived service identity. Its permission must contain only `engine.submit` and `engine.result`; because the execution reference does not exist before submission, the reference SQLite grant uses the wildcard resource scope in an isolated smoke-test environment. Revoke the key and permission immediately after the test. A production authorization provider may instead support a policy that relates the created execution to the submitted project.

Set these values through the operator's secret-injection mechanism:

- `SQUIRRELFORGE_SMOKE_BASE_URL`
- `SQUIRRELFORGE_SMOKE_IDENTITY_REF`
- `SQUIRRELFORGE_SMOKE_PERMISSION_REF`
- `SQUIRRELFORGE_SMOKE_API_KEY`

Then run `php bin/production-smoke-test.php`. It verifies provider readiness, API-key authentication, authorization, submission, validation, and result retrieval. Output contains references and decisions only; credentials and tokens are never printed.

## CI deployment gate

`.github/workflows/flock-deployment-gate.yml` blocks the main branch and pull requests unless the production topology passes. It:

1. Builds the runtime and mock-provider images.
2. Generates a one-day TLS certificate with the mock provider's network identity as its subject alternative name.
3. Creates a fresh persistent-runtime directory.
4. Runs the explicitly gated smoke-identity provisioner once, before startup.
5. Starts the TLS mock provider and production-mode SquirrelForge container on an isolated Docker network.
6. Waits for the redacted provider-readiness endpoint.
7. Runs the production smoke test inside the network.
8. Publishes container logs on failure and removes containers and the network.

The fixture provisioner requires `SQUIRRELFORGE_ALLOW_SMOKE_PROVISIONING=1`, creates only a service identity and an `engine.submit`/`engine.result` permission, and never provisions a credential. It must not be invoked by the production entrypoint. The mock API key and provider token are ephemeral CI-only values, and the workflow workspace is discarded after the job.

Image publication or deployment jobs must depend on the successful `deployment-gate` job rather than rebuilding an unverified source revision independently.

## Supply-chain publication

Version tags matching `v*` activate the `publish` job only after `deployment-gate` succeeds. The gate:

- generates a CycloneDX JSON SBOM from the tested image,
- blocks unresolved critical or high operating-system or library vulnerabilities,
- saves the exact tested image as a deterministic gzip archive,
- records the image ID and SHA-256 checksums for the archive and SBOM,
- and uploads that evidence with bounded retention.

The publication job downloads the evidence, verifies both checksums, loads the image, and confirms its image ID matches the tested image before registry authentication. It then pushes a version tag to GitHub Container Registry and captures the immutable registry digest.

Cosign signs `image@digest` with the job's short-lived GitHub OIDC identity. GitHub artifact attestations attach build provenance and the CycloneDX SBOM to the same digest and push those attestations to the registry. Publication permissions exist only on this tag-only job.

Security-sensitive third-party actions are pinned to full commit identifiers. Updates require reviewing the upstream release and commit before changing the pin. Mutable third-party action tags or branches must not be introduced into the publication job.

Consumers must deploy by digest and verify the expected repository identity, Cosign signature, provenance attestation, and SBOM attestation before admission. A mutable version tag is a discovery aid, not deployment identity.

The executable policy and operator procedure are defined in `deploy/ADMISSION.md`. `composer admission:verify` must pass before rollout, and the orchestrator must receive the exact verified digest rather than the release tag.

Staged promotion and verified-digest rollback are defined in `deploy/ROLLOUT.md`. `composer rollout:release` verifies candidate and rollback admission, soaks a canary against readiness and error-rate thresholds, promotes stable only after success, and automatically restores the prior digest if stable degrades.

Release-tag publication continues into an approval-gated GitHub `production` environment on a governed deployment runner. Configure required reviewers in repository environment settings; workflow YAML selects the protected environment but cannot define its reviewers. A release is successful only after promotion and the post-deployment observation window pass without rollback.

## Backup and recovery

The reference SQLite deployment must not be considered production-ready until its backup destination, encryption, retention, monitoring, and restore-drill schedule satisfy `deploy/BACKUP-RECOVERY.md`. `composer backup:engine` creates a consistent snapshot and checksum manifest while the Engine remains online. `composer verify:backup` verifies the artifact independently, and `composer restore:engine` restores only into an empty target after checksum and SQLite integrity validation.

A backup stored only on the runtime persistent volume is not disaster recovery. Export the backup and manifest together to encrypted, access-controlled storage in a separate failure domain, then delete the local staging copies according to policy.

## Observability and incidents

Production service-level indicators, continuous image/readiness/error-rate/backup-freshness checks, safe alert contents, and incident response are defined in `deploy/OBSERVABILITY.md`. Configure `observability-policy.env.example` through the monitoring platform and run `composer health:production` with a read-only deployment identity. A quiet alert channel is not recovery evidence; resolution requires sustained healthy signals and authoritative rollback or restore receipts when those actions occurred.

## Capacity and resilience

The measured operating-limit procedure, authenticated load profile, saturation signals, failure-injection matrix, stop rules, and single-node scaling boundary are defined in `deploy/CAPACITY-RESILIENCE.md`. Run capacity tests in an isolated environment by default. The reference PHP server and SQLite stores are not a horizontally scalable production topology.

## Launch decision

The final production decision procedure is defined in `deploy/LAUNCH-READINESS.md`. `composer launch:evaluate` verifies checksum-bound admission, rollout, observation, health, backup, restore, and capacity evidence plus approval and ownership references. Only a `READY` receipt may authorize opening production traffic; missing, stale, inconsistent, or failed evidence is `NOT_READY`.
