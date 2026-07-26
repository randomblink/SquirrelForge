#!/bin/sh
set -eu

for command_name in kubectl curl awk; do
  if ! command -v "$command_name" >/dev/null 2>&1; then
    echo "Rollout control requires $command_name." >&2
    exit 1
  fi
done

candidate_image="${SQUIRRELFORGE_ROLLOUT_IMAGE:-}"
namespace="${SQUIRRELFORGE_ROLLOUT_NAMESPACE:-default}"
stable_deployment="${SQUIRRELFORGE_ROLLOUT_STABLE_DEPLOYMENT:-squirrelforge}"
canary_deployment="${SQUIRRELFORGE_ROLLOUT_CANARY_DEPLOYMENT:-squirrelforge-canary}"
container_name="${SQUIRRELFORGE_ROLLOUT_CONTAINER:-squirrelforge}"
canary_replicas="${SQUIRRELFORGE_ROLLOUT_CANARY_REPLICAS:-1}"
canary_readiness_url="${SQUIRRELFORGE_ROLLOUT_CANARY_READINESS_URL:-}"
stable_readiness_url="${SQUIRRELFORGE_ROLLOUT_STABLE_READINESS_URL:-}"
canary_error_rate_url="${SQUIRRELFORGE_ROLLOUT_CANARY_ERROR_RATE_URL:-}"
stable_error_rate_url="${SQUIRRELFORGE_ROLLOUT_STABLE_ERROR_RATE_URL:-}"
maximum_error_rate="${SQUIRRELFORGE_ROLLOUT_MAXIMUM_ERROR_RATE_PERCENT:-1}"
probe_count="${SQUIRRELFORGE_ROLLOUT_PROBE_COUNT:-6}"
probe_interval="${SQUIRRELFORGE_ROLLOUT_PROBE_INTERVAL_SECONDS:-10}"
maximum_failures="${SQUIRRELFORGE_ROLLOUT_MAXIMUM_PROBE_FAILURES:-0}"
rollout_timeout="${SQUIRRELFORGE_ROLLOUT_TIMEOUT:-180s}"
admission_command="${SQUIRRELFORGE_ROLLOUT_ADMISSION_COMMAND:-$(dirname "$0")/verify-release-image.sh}"
receipt_path="${SQUIRRELFORGE_ROLLOUT_RECEIPT_PATH:-}"

case "$namespace:$stable_deployment:$canary_deployment:$container_name" in
  *[!a-zA-Z0-9._:-]*)
    echo "Rollout Kubernetes identifiers contain unsupported characters." >&2
    exit 1
    ;;
esac

case "$candidate_image" in
  *@sha256:*) ;;
  *)
    echo "Rollout candidate must use an immutable SHA-256 digest." >&2
    exit 1
    ;;
esac

for value in "$canary_replicas" "$probe_count" "$probe_interval" "$maximum_failures"; do
  case "$value" in
    ''|*[!0-9]*)
      echo "Rollout counts and intervals must be non-negative integers." >&2
      exit 1
      ;;
  esac
done

if [ "$canary_replicas" -lt 1 ] || [ "$probe_count" -lt 1 ]; then
  echo "Canary replicas and probe count must be positive." >&2
  exit 1
fi

if [ -z "$canary_readiness_url" ] || [ -z "$stable_readiness_url" ] \
  || [ -z "$canary_error_rate_url" ] || [ -z "$stable_error_rate_url" ]; then
  echo "Canary and stable readiness and error-rate URLs are required." >&2
  exit 1
fi

if ! awk -v value="$maximum_error_rate" \
  'BEGIN { exit !(value ~ /^[0-9]+([.][0-9]+)?$/ && value >= 0 && value <= 100) }'; then
  echo "Maximum error rate must be a percentage between 0 and 100." >&2
  exit 1
fi

if [ ! -x "$admission_command" ]; then
  echo "The rollout admission command is not executable." >&2
  exit 1
fi

image_path="{.spec.template.spec.containers[?(@.name==\"$container_name\")].image}"
previous_image="$(kubectl --namespace "$namespace" get deployment "$stable_deployment" -o "jsonpath=$image_path")"

case "$previous_image" in
  *@sha256:*) ;;
  *)
    echo "Current stable deployment is not pinned to a rollback digest." >&2
    exit 1
    ;;
esac

SQUIRRELFORGE_ADMISSION_IMAGE="$candidate_image" "$admission_command" >/dev/null
SQUIRRELFORGE_ADMISSION_IMAGE="$previous_image" "$admission_command" >/dev/null

stable_changed=0
completed=0

write_receipt() {
  receipt_status=$1
  active_image=$2

  if [ -n "$receipt_path" ]; then
    umask 077
    printf '{\n  "schema_version": 1,\n  "status": "%s",\n  "recorded_at": "%s",\n  "candidate_image": "%s",\n  "rollback_image": "%s",\n  "active_image": "%s"\n}\n' \
      "$receipt_status" "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" \
      "$candidate_image" "$previous_image" "$active_image" > "$receipt_path"
  fi
}

cleanup() {
  exit_code=$?

  if [ "$completed" -ne 1 ]; then
    final_status="HALTED"
    active_image="$previous_image"
    echo "Rollout halted; removing canary traffic." >&2
    kubectl --namespace "$namespace" scale deployment "$canary_deployment" --replicas=0 >/dev/null 2>&1 || true

    if [ "$stable_changed" -eq 1 ]; then
      echo "Stable rollout degraded; restoring verified digest $previous_image." >&2
      if kubectl --namespace "$namespace" set image \
        "deployment/$stable_deployment" \
        "$container_name=$previous_image" >/dev/null 2>&1 \
        && kubectl --namespace "$namespace" rollout status \
        "deployment/$stable_deployment" \
        --timeout="$rollout_timeout" >/dev/null 2>&1; then
        final_status="ROLLED_BACK"
      else
        final_status="ROLLBACK_FAILED"
        active_image="UNKNOWN"
      fi
    fi

    write_receipt "$final_status" "$active_image"
  fi

  exit "$exit_code"
}
trap cleanup EXIT HUP INT TERM

probe() {
  url=$1
  label=$2
  error_rate_url=$3
  failures=0
  attempt=1

  while [ "$attempt" -le "$probe_count" ]; do
    if ! curl --fail --silent --show-error --max-time 10 "$url" >/dev/null; then
      failures=$((failures + 1))
      echo "$label readiness probe failed ($failures/$probe_count)." >&2
    fi

    error_rate="$(curl --fail --silent --show-error --max-time 10 "$error_rate_url" 2>/dev/null || true)"

    if ! awk -v value="$error_rate" -v maximum="$maximum_error_rate" \
      'BEGIN { exit !(value ~ /^[0-9]+([.][0-9]+)?$/ && value <= maximum) }'; then
      failures=$((failures + 1))
      echo "$label error rate exceeded $maximum_error_rate percent or was unavailable." >&2
    fi

    if [ "$failures" -gt "$maximum_failures" ]; then
      echo "$label exceeded the readiness failure threshold." >&2
      return 1
    fi

    if [ "$attempt" -lt "$probe_count" ] && [ "$probe_interval" -gt 0 ]; then
      sleep "$probe_interval"
    fi

    attempt=$((attempt + 1))
  done
}

kubectl --namespace "$namespace" set image \
  "deployment/$canary_deployment" \
  "$container_name=$candidate_image"
kubectl --namespace "$namespace" scale \
  "deployment/$canary_deployment" \
  --replicas="$canary_replicas"
kubectl --namespace "$namespace" rollout status \
  "deployment/$canary_deployment" \
  --timeout="$rollout_timeout"
probe "$canary_readiness_url" "Canary" "$canary_error_rate_url"

stable_changed=1
kubectl --namespace "$namespace" set image \
  "deployment/$stable_deployment" \
  "$container_name=$candidate_image"
kubectl --namespace "$namespace" rollout status \
  "deployment/$stable_deployment" \
  --timeout="$rollout_timeout"
probe "$stable_readiness_url" "Stable" "$stable_error_rate_url"

kubectl --namespace "$namespace" scale deployment "$canary_deployment" --replicas=0
completed=1
write_receipt "PROMOTED" "$candidate_image"

printf '%s\n' \
  "SquirrelForge rollout PASSED." \
  "candidate_digest=${candidate_image##*@}" \
  "rollback_digest=${previous_image##*@}" \
  "canary=PASSED" \
  "stable=PASSED"
