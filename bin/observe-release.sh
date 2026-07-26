#!/bin/sh
set -eu

for command_name in kubectl curl awk; do
  command -v "$command_name" >/dev/null 2>&1 || {
    echo "Release observation requires $command_name." >&2
    exit 1
  }
done

expected_image="${SQUIRRELFORGE_OBSERVE_IMAGE:-}"
namespace="${SQUIRRELFORGE_ROLLOUT_NAMESPACE:-default}"
deployment="${SQUIRRELFORGE_ROLLOUT_STABLE_DEPLOYMENT:-squirrelforge}"
container="${SQUIRRELFORGE_ROLLOUT_CONTAINER:-squirrelforge}"
readiness_url="${SQUIRRELFORGE_ROLLOUT_STABLE_READINESS_URL:-}"
error_rate_url="${SQUIRRELFORGE_ROLLOUT_STABLE_ERROR_RATE_URL:-}"
maximum_error_rate="${SQUIRRELFORGE_ROLLOUT_MAXIMUM_ERROR_RATE_PERCENT:-1}"
samples="${SQUIRRELFORGE_OBSERVE_SAMPLE_COUNT:-12}"
interval="${SQUIRRELFORGE_OBSERVE_INTERVAL_SECONDS:-10}"
receipt_path="${SQUIRRELFORGE_OBSERVE_RECEIPT_PATH:-}"

case "$expected_image" in *@sha256:*) ;; *) echo "Observed image must use an immutable digest." >&2; exit 1 ;; esac
case "$samples:$interval" in *[!0-9:]*|'') echo "Observation counts must be integers." >&2; exit 1 ;; esac

if [ "$samples" -lt 1 ] || [ -z "$readiness_url" ] || [ -z "$error_rate_url" ]; then
  echo "Observation samples, readiness URL, and error-rate URL are required." >&2
  exit 1
fi

write_receipt() {
  status=$1
  if [ -n "$receipt_path" ]; then
    umask 077
    printf '{\n  "schema_version": 1,\n  "status": "%s",\n  "observed_at": "%s",\n  "image": "%s",\n  "samples": %s\n}\n' \
      "$status" "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" "$expected_image" "$samples" > "$receipt_path"
  fi
}

image_path="{.spec.template.spec.containers[?(@.name==\"$container\")].image}"
attempt=1

while [ "$attempt" -le "$samples" ]; do
  active_image="$(kubectl --namespace "$namespace" get deployment "$deployment" -o "jsonpath=$image_path")"

  if [ "$active_image" != "$expected_image" ]; then
    write_receipt "FAILED"
    echo "Post-deployment observation found an unexpected active digest." >&2
    exit 1
  fi

  if ! curl --fail --silent --show-error --max-time 10 "$readiness_url" >/dev/null; then
    write_receipt "FAILED"
    echo "Post-deployment readiness failed." >&2
    exit 1
  fi

  error_rate="$(curl --fail --silent --show-error --max-time 10 "$error_rate_url" 2>/dev/null || true)"
  if ! awk -v value="$error_rate" -v maximum="$maximum_error_rate" \
    'BEGIN { exit !(value ~ /^[0-9]+([.][0-9]+)?$/ && value <= maximum) }'; then
    write_receipt "FAILED"
    echo "Post-deployment error rate exceeded policy or was unavailable." >&2
    exit 1
  fi

  if [ "$attempt" -lt "$samples" ] && [ "$interval" -gt 0 ]; then
    sleep "$interval"
  fi
  attempt=$((attempt + 1))
done

write_receipt "PASSED"
echo "SquirrelForge post-deployment observation PASSED."
