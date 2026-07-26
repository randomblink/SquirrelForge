# Release Image Admission

`bin/verify-release-image.sh` is the mandatory pre-rollout admission command for the published SquirrelForge image.

## Policy

Admission succeeds only when all conditions pass:

1. The image reference exactly matches `SQUIRRELFORGE_ADMISSION_REPOSITORY`.
2. The image uses an explicit, syntactically valid `@sha256:` digest rather than a mutable tag.
3. Cosign verifies the image signature against the configured GitHub Actions certificate identity and OIDC issuer.
4. GitHub verifies SLSA provenance for `SQUIRRELFORGE_ADMISSION_SOURCE_REPOSITORY`.
5. GitHub verifies a CycloneDX SBOM attestation for the same digest.
6. Trivy finds no vulnerability at a blocked severity.

Every condition is fail-closed. Missing commands, configuration, registry access, signature material, attestations, vulnerability data, or network access reject admission.

## Required tools

- Cosign
- GitHub CLI with `attestation` support
- Trivy

Install these tools through the deployment platform's governed, integrity-verified toolchain. Do not download mutable binaries during the admission operation.

## Configuration

Copy only the names and expected formats from `admission-policy.env.example`. Set:

- the fully qualified image including its immutable digest,
- the exact expected GHCR repository,
- the GitHub source repository that owns the attestations,
- the certificate identity expression restricted to the publication workflow and release tags,
- GitHub's Actions OIDC issuer,
- and blocked vulnerability severities.

Do not weaken the repository, workflow identity, issuer, or digest match to recover a failed rollout. Investigate and publish a new verified digest.

## Execution

Authenticate the operator environment to GHCR and GitHub's attestation API, export the policy values, then run:

```sh
composer admission:verify
```

On success, the command prints only the repository, immutable digest, and verification decisions. Set `SQUIRRELFORGE_ADMISSION_RECEIPT_PATH` to create a timestamped JSON receipt binding the passed signature, provenance, SBOM, and vulnerability decisions to that exact image. Deployment tooling must pass the same digest to the orchestrator. It must not resolve or substitute a tag after admission.

For Kubernetes, run this verifier in the promotion controller or a protected pre-deployment job and configure the workload image as `repository@sha256:digest`. Cluster admission policy should separately reject image fields without digests so no alternate deployment path can bypass this command.
