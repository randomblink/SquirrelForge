#!/bin/sh
set -eu

for command_name in kubectl; do
  command -v "$command_name" >/dev/null 2>&1 || {
    echo "Verified rollback requires $command_name." >&2
    exit 1
  }
done

target_image="${SQUIRRELFORGE_ROLLBACK_IMAGE:-}"
namespace="${SQUIRRELFORGE_ROLLOUT_NAMESPACE:-default}"
deployment="${SQUIRRELFORGE_ROLLOUT_STABLE_DEPLOYMENT:-squirrelforge}"
container="${SQUIRRELFORGE_ROLLOUT_CONTAINER:-squirrelforge}"
timeout="${SQUIRRELFORGE_ROLLOUT_TIMEOUT:-180s}"
admission_command="${SQUIRRELFORGE_ROLLOUT_ADMISSION_COMMAND:-$(dirname "$0")/verify-release-image.sh}"
receipt_path="${SQUIRRELFORGE_ROLLBACK_RECEIPT_PATH:-}"

case "$target_image" in *@sha256:*) ;; *) echo "Rollback target must use an immutable digest." >&2; exit 1 ;; esac

SQUIRRELFORGE_ADMISSION_IMAGE="$target_image" "$admission_command" >/dev/null
kubectl --namespace "$namespace" set image "deployment/$deployment" "$container=$target_image"
kubectl --namespace "$namespace" rollout status "deployment/$deployment" --timeout="$timeout"

image_path="{.spec.template.spec.containers[?(@.name==\"$container\")].image}"
active_image="$(kubectl --namespace "$namespace" get deployment "$deployment" -o "jsonpath=$image_path")"

if [ "$active_image" != "$target_image" ]; then
  echo "Rollback completed without restoring the expected digest." >&2
  exit 1
fi

if [ -n "$receipt_path" ]; then
  umask 077
  printf '{\n  "status": "ROLLED_BACK",\n  "active_image": "%s"\n}\n' "$active_image" > "$receipt_path"
fi

echo "SquirrelForge verified rollback PASSED."
