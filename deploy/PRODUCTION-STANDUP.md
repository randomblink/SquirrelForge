# Production Standup Runbook

Neither the GitHub `production` environment nor any Kubernetes resource for
either image (the main Engine API image or the credential-provider image)
exists yet — this is the one-time bootstrap an operator with real cluster
admin and repository admin access runs before the first `protected-rollout`
or `protected-rollout-credential-provider` job can do anything. Nothing in
this file is applied automatically by CI; every command below is meant to
be reviewed and run by a human against the real cluster and repository.

Do this once per image, in order: GitHub environment first (§1), then the
image's own Kubernetes resources (§2 for the main image, §3 for the
credential-provider image). Both `protected-rollout` jobs target the same
GitHub `production` environment but each image's Kubernetes resources are
fully independent — different namespace conventions are fine as long as
the `*_ROLLOUT_NAMESPACE` variable for that image matches.

## 1. GitHub `production` environment

Create it and require review before either rollout job can run unattended:

```sh
gh api --method PUT repos/randomblink/SquirrelForge/environments/production \
  --input - <<'JSON'
{
  "deployment_branch_policy": { "protected_branches": false, "custom_branch_policies": true }
}
JSON

gh api --method PUT repos/randomblink/SquirrelForge/environments/production/deployment-branch-policies \
  -f name='v*'
```

Add required reviewers through the repository Settings UI
(`Settings → Environments → production → Required reviewers`) — this isn't
exposed as a plain `gh` one-liner and is worth setting deliberately, not
scripting.

### Non-secret variables

Review these values before running — the ones below match the naming
already used in `deploy/rollout-policy.env.example` and
`deploy/credential-provider-rollout-policy.env.example`, but the URLs are
placeholders. Substitute your real namespace, deployment names, and
observability endpoints.

```sh
# Main Engine API image
gh variable set SQUIRRELFORGE_ROLLOUT_NAMESPACE --env production --body squirrelforge
gh variable set SQUIRRELFORGE_ROLLOUT_STABLE_DEPLOYMENT --env production --body squirrelforge
gh variable set SQUIRRELFORGE_ROLLOUT_CANARY_DEPLOYMENT --env production --body squirrelforge-canary
gh variable set SQUIRRELFORGE_ROLLOUT_CONTAINER --env production --body squirrelforge
gh variable set SQUIRRELFORGE_ROLLOUT_MAXIMUM_ERROR_RATE_PERCENT --env production --body 1
gh variable set SQUIRRELFORGE_OBSERVE_SAMPLE_COUNT --env production --body 12
gh variable set SQUIRRELFORGE_OBSERVE_INTERVAL_SECONDS --env production --body 10

# Credential-provider image (distinct names -- see deploy/ROLLOUT.md)
gh variable set SQUIRRELFORGE_CREDENTIAL_PROVIDER_ROLLOUT_NAMESPACE --env production --body squirrelforge
gh variable set SQUIRRELFORGE_CREDENTIAL_PROVIDER_ROLLOUT_STABLE_DEPLOYMENT --env production --body squirrelforge-credential-provider
gh variable set SQUIRRELFORGE_CREDENTIAL_PROVIDER_ROLLOUT_CANARY_DEPLOYMENT --env production --body squirrelforge-credential-provider-canary
gh variable set SQUIRRELFORGE_CREDENTIAL_PROVIDER_ROLLOUT_CONTAINER --env production --body squirrelforge-credential-provider
gh variable set SQUIRRELFORGE_CREDENTIAL_PROVIDER_ROLLOUT_MAXIMUM_ERROR_RATE_PERCENT --env production --body 1
gh variable set SQUIRRELFORGE_CREDENTIAL_PROVIDER_OBSERVE_SAMPLE_COUNT --env production --body 12
gh variable set SQUIRRELFORGE_CREDENTIAL_PROVIDER_OBSERVE_INTERVAL_SECONDS --env production --body 10
```

### Secrets — set these yourself, never through an assistant

Each of these is credential or endpoint material. Set the real value with
`gh secret set NAME --env production` (it will prompt on stdin, or read
`--body-file`) or through the Settings UI. Nothing in this repository or
this session should ever hold the real value in plaintext.

| Secret | Used by | Notes |
|---|---|---|
| `SQUIRRELFORGE_KUBECONFIG_BASE64` | both rollout jobs | `base64` of a kubeconfig scoped to only the namespace(s)/Deployments below — never cluster-admin. |
| `SQUIRRELFORGE_ROLLOUT_CANARY_READINESS_URL` | main image | Must return non-2xx while unhealthy. |
| `SQUIRRELFORGE_ROLLOUT_STABLE_READINESS_URL` | main image | Same. |
| `SQUIRRELFORGE_ROLLOUT_CANARY_ERROR_RATE_URL` | main image | Must return a plain numeric percentage. |
| `SQUIRRELFORGE_ROLLOUT_STABLE_ERROR_RATE_URL` | main image | Same. |
| `SQUIRRELFORGE_CREDENTIAL_PROVIDER_ROLLOUT_CANARY_READINESS_URL` | credential-provider image | Must itself present the provider's Bearer token — see the canary manifest's probe comment. |
| `SQUIRRELFORGE_CREDENTIAL_PROVIDER_ROLLOUT_STABLE_READINESS_URL` | credential-provider image | Same. |
| `SQUIRRELFORGE_CREDENTIAL_PROVIDER_ROLLOUT_CANARY_ERROR_RATE_URL` | credential-provider image | Plain numeric percentage. |
| `SQUIRRELFORGE_CREDENTIAL_PROVIDER_ROLLOUT_STABLE_ERROR_RATE_URL` | credential-provider image | Same. |

If no metrics platform exposes a plain-percentage error-rate endpoint yet,
`bin/rollout-release.sh`/`bin/observe-release.sh` treat an unreachable or
non-numeric response as a probe failure — build that endpoint (even a
thin adapter in front of your real metrics store) before the first real
rollout, not after.

## 2. Kubernetes resources — main Engine API image

Run against the real cluster with an operator identity that has only the
RBAC to manage this one namespace (never cluster-admin — `deploy/ROLLOUT.md`
requires this).

```sh
kubectl create namespace squirrelforge

# Runtime configuration and the provider token -- see
# deploy/production.env.example for the full name list. Fill in real
# values yourself; do not paste secret material into an assistant session.
kubectl --namespace squirrelforge create secret generic squirrelforge-runtime \
  --from-literal=SQUIRRELFORGE_ENVIRONMENT=production \
  --from-literal=SQUIRRELFORGE_ENGINE_DB=/app/var/engine.sqlite \
  --from-literal=SQUIRRELFORGE_CREDENTIAL_PROVIDER=http-json \
  --from-literal=SQUIRRELFORGE_CREDENTIAL_PROVIDER_URL=<https-url-of-your-credential-provider> \
  --from-literal=SQUIRRELFORGE_CREDENTIAL_PROVIDER_TOKEN=<32+ char token>

kubectl --namespace squirrelforge create persistentvolumeclaim squirrelforge-runtime \
  --dry-run=client -o yaml \
  | kubectl apply --namespace squirrelforge -f -
# (or your platform's PVC/StorageClass equivalent -- SQLite needs a real
# persistent volume, not emptyDir, per deploy/BACKUP-RECOVERY.md)

kubectl --namespace squirrelforge create persistentvolumeclaim squirrelforge-canary-runtime \
  --dry-run=client -o yaml \
  | kubectl apply --namespace squirrelforge -f -
```

`bin/rollout-release.sh` requires the **stable** Deployment to already
exist and already be pinned to an immutable `@sha256:digest` before the
first rollout runs (it reads the current image as the rollback candidate
and refuses to proceed otherwise — see its Preconditions in
`deploy/ROLLOUT.md`). Bootstrap it manually once, from a digest you've
already run through `bin/verify-release-image.sh` yourself:

```sh
kubectl --namespace squirrelforge create deployment squirrelforge \
  --image=ghcr.io/randomblink/squirrelforge@sha256:<first_verified_digest> \
  --dry-run=client -o yaml > /tmp/squirrelforge-stable.yaml
# Edit /tmp/squirrelforge-stable.yaml: container name "squirrelforge",
# containerPort 8080, readinessProbe GET /v1/health/providers, envFrom
# secretRef squirrelforge-runtime, volumeMounts /app/var from the
# squirrelforge-runtime PVC -- same shape as
# deploy/kubernetes/canary-deployment.yaml, replicas: 1 or more.
kubectl apply --namespace squirrelforge -f /tmp/squirrelforge-stable.yaml

kubectl apply --namespace squirrelforge -f deploy/kubernetes/canary-deployment.yaml

kubectl --namespace squirrelforge expose deployment squirrelforge \
  --name=squirrelforge --port=80 --target-port=8080
kubectl --namespace squirrelforge expose deployment squirrelforge-canary \
  --name=squirrelforge-canary --port=80 --target-port=8080
```

The canary Service must be reachable only by rollout probes, never by
real workflow traffic — route it accordingly at your ingress/mesh layer.

## 3. Kubernetes resources — credential-provider image

Same shape, independent resources. `bin/credential-provider-preflight.php`
requires `SQUIRRELFORGE_CREDENTIAL_PROVIDER_TOKEN` (32+ chars),
`SQUIRRELFORGE_CREDENTIAL_PROVIDER_MFA_MASTER_KEY` (base64-encoded 32-byte
key), and `SQUIRRELFORGE_CREDENTIAL_PROVIDER_DB` — see
`deploy/CREDENTIAL-PROVIDER-CONTRACT.md`.

```sh
# Same namespace is fine if you're keeping both images together; a
# separate namespace works too as long as the *_ROLLOUT_NAMESPACE
# variable for this image matches whatever you pick.
kubectl --namespace squirrelforge create secret generic squirrelforge-credential-provider-runtime \
  --from-literal=SQUIRRELFORGE_CREDENTIAL_PROVIDER_TOKEN=<32+ char token, different from the client-side one above> \
  --from-literal=SQUIRRELFORGE_CREDENTIAL_PROVIDER_MFA_MASTER_KEY=<base64 32-byte key> \
  --from-literal=SQUIRRELFORGE_CREDENTIAL_PROVIDER_DB=/app/var/credential-provider.sqlite

kubectl --namespace squirrelforge create persistentvolumeclaim squirrelforge-credential-provider-runtime \
  --dry-run=client -o yaml \
  | kubectl apply --namespace squirrelforge -f -
kubectl --namespace squirrelforge create persistentvolumeclaim squirrelforge-credential-provider-canary-runtime \
  --dry-run=client -o yaml \
  | kubectl apply --namespace squirrelforge -f -

kubectl --namespace squirrelforge create deployment squirrelforge-credential-provider \
  --image=ghcr.io/randomblink/squirrelforge/credential-provider@sha256:<first_verified_digest> \
  --dry-run=client -o yaml > /tmp/squirrelforge-credential-provider-stable.yaml
# Edit: container name "squirrelforge-credential-provider", containerPort
# 8080, readinessProbe GET /v1/provider/health with the same Authorization
# Bearer header the canary manifest documents, envFrom secretRef
# squirrelforge-credential-provider-runtime, volumeMounts /app/var from
# the squirrelforge-credential-provider-runtime PVC.
kubectl apply --namespace squirrelforge -f /tmp/squirrelforge-credential-provider-stable.yaml

kubectl apply --namespace squirrelforge -f deploy/kubernetes/credential-provider-canary-deployment.yaml

kubectl --namespace squirrelforge expose deployment squirrelforge-credential-provider \
  --name=squirrelforge-credential-provider --port=80 --target-port=8080
kubectl --namespace squirrelforge expose deployment squirrelforge-credential-provider-canary \
  --name=squirrelforge-credential-provider-canary --port=80 --target-port=8080
```

The two services must never share a Bearer token — `SQUIRRELFORGE_CREDENTIAL_PROVIDER_TOKEN` in
`squirrelforge-runtime` is the *client* token the Engine API presents when
calling out; `SQUIRRELFORGE_CREDENTIAL_PROVIDER_TOKEN` in
`squirrelforge-credential-provider-runtime` is the *server*'s own token it
checks incoming requests against. Same variable name, two different
Secrets, two different real values.

## 4. Before the first real rollout

Confirm every `bin/rollout-release.sh` precondition in `deploy/ROLLOUT.md`
against the resources you just created:

- both stable Deployments are pinned to an `@sha256:digest` (never a
  mutable tag) — `kubectl get deployment <name> -o jsonpath='{.spec.template.spec.containers[0].image}'`
- `composer admission:verify` (`bin/verify-release-image.sh`) succeeds
  against that same digest, using the runner's own `cosign`/`gh`/`trivy`
- readiness and error-rate URLs for both stable and canary, both images,
  actually resolve and return the expected shape (non-2xx while
  unhealthy; a bare numeric percentage)
- the `SQUIRRELFORGE_KUBECONFIG_BASE64` identity can read/update/scale/
  watch exactly these Deployments and nothing else

Only once all of that is true does triggering the workflow (tagging a
`v*` release) mean anything real.
