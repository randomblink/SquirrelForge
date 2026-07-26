#!/bin/sh
set -eu

for command_name in kubectl curl awk php; do
  if ! command -v "$command_name" >/dev/null 2>&1; then
    echo "$command_name is required." >&2
    exit 2
  fi
done

namespace="${SQUIRRELFORGE_MONITOR_NAMESPACE:-squirrelforge}"
deployment="${SQUIRRELFORGE_MONITOR_DEPLOYMENT:-squirrelforge}"
container="${SQUIRRELFORGE_MONITOR_CONTAINER:-squirrelforge}"
expected_image="${SQUIRRELFORGE_MONITOR_EXPECTED_IMAGE:-}"
readiness_url="${SQUIRRELFORGE_MONITOR_READINESS_URL:-}"
error_rate_url="${SQUIRRELFORGE_MONITOR_ERROR_RATE_URL:-}"
backup_age_url="${SQUIRRELFORGE_MONITOR_BACKUP_AGE_URL:-}"
maximum_error_rate="${SQUIRRELFORGE_MONITOR_MAXIMUM_ERROR_RATE_PERCENT:-1}"
maximum_backup_age="${SQUIRRELFORGE_MONITOR_MAXIMUM_BACKUP_AGE_SECONDS:-86400}"
receipt_path="${SQUIRRELFORGE_MONITOR_RECEIPT_PATH:-}"

if [ -z "$expected_image" ] || [ -z "$readiness_url" ] \
  || [ -z "$error_rate_url" ] || [ -z "$backup_age_url" ]; then
  echo "Expected image and readiness, error-rate, and backup-age URLs are required." >&2
  exit 2
fi

if ! printf '%s\n' "$maximum_error_rate" \
  | awk '/^[0-9]+([.][0-9]+)?$/ && $1 >= 0 && $1 <= 100 { found=1 } END { exit !found }'; then
  echo "Maximum error rate must be a percentage between 0 and 100." >&2
  exit 2
fi

if ! printf '%s\n' "$maximum_backup_age" \
  | awk '/^[0-9]+$/ && $1 > 0 { found=1 } END { exit !found }'; then
  echo "Maximum backup age must be a positive integer." >&2
  exit 2
fi

failures=""
add_failure() {
  if [ -n "$failures" ]; then
    failures="$failures,$1"
  else
    failures="$1"
  fi
}

active_image="UNKNOWN"
if resolved_image="$(kubectl --namespace "$namespace" get "deployment/$deployment" \
  -o "jsonpath={.spec.template.spec.containers[?(@.name=='$container')].image}" 2>/dev/null)" \
  && [ -n "$resolved_image" ]; then
  active_image="$resolved_image"
  if [ "$active_image" != "$expected_image" ]; then
    add_failure "IMAGE_DRIFT"
  fi
else
  add_failure "DEPLOYMENT_UNAVAILABLE"
fi

readiness_status="FAIL"
if readiness_body="$(curl --fail --silent --show-error --max-time 10 "$readiness_url" 2>/dev/null)" \
  && printf '%s' "$readiness_body" \
    | php -r '$body=json_decode(stream_get_contents(STDIN), true); exit(($body["ready"] ?? false) === true ? 0 : 1);'; then
  readiness_status="PASS"
else
  add_failure "READINESS_FAILED"
fi

error_rate="UNKNOWN"
if sampled_error_rate="$(curl --fail --silent --show-error --max-time 10 "$error_rate_url" 2>/dev/null)" \
  && printf '%s\n' "$sampled_error_rate" \
    | awk '/^[0-9]+([.][0-9]+)?$/ && $1 >= 0 && $1 <= 100 { found=1 } END { exit !found }'; then
  error_rate="$sampled_error_rate"
  if ! awk -v actual="$error_rate" -v maximum="$maximum_error_rate" \
    'BEGIN { exit !(actual <= maximum) }'; then
    add_failure "ERROR_RATE_EXCEEDED"
  fi
else
  add_failure "ERROR_RATE_UNAVAILABLE"
fi

backup_age="UNKNOWN"
if sampled_backup_age="$(curl --fail --silent --show-error --max-time 10 "$backup_age_url" 2>/dev/null)" \
  && printf '%s\n' "$sampled_backup_age" \
    | awk '/^[0-9]+$/ && $1 >= 0 { found=1 } END { exit !found }'; then
  backup_age="$sampled_backup_age"
  if ! awk -v actual="$backup_age" -v maximum="$maximum_backup_age" \
    'BEGIN { exit !(actual <= maximum) }'; then
    add_failure "BACKUP_STALE"
  fi
else
  add_failure "BACKUP_AGE_UNAVAILABLE"
fi

status="HEALTHY"
exit_code=0
if [ -n "$failures" ]; then
  status="UNHEALTHY"
  exit_code=1
fi

if [ -n "$receipt_path" ]; then
  if [ -e "$receipt_path" ]; then
    echo "Health receipt output already exists." >&2
    exit 2
  fi

  umask 077
  RECEIPT_STATUS="$status" \
  RECEIPT_EXPECTED_IMAGE="$expected_image" \
  RECEIPT_ACTIVE_IMAGE="$active_image" \
  RECEIPT_READINESS="$readiness_status" \
  RECEIPT_ERROR_RATE="$error_rate" \
  RECEIPT_BACKUP_AGE="$backup_age" \
  RECEIPT_FAILURES="$failures" \
  php -r '
    $failures = getenv("RECEIPT_FAILURES");
    $receipt = [
      "schema_version" => 1,
      "status" => getenv("RECEIPT_STATUS"),
      "checked_at" => gmdate("Y-m-d\\TH:i:s\\Z"),
      "expected_image" => getenv("RECEIPT_EXPECTED_IMAGE"),
      "active_image" => getenv("RECEIPT_ACTIVE_IMAGE"),
      "readiness" => getenv("RECEIPT_READINESS"),
      "error_rate_percent" => is_numeric(getenv("RECEIPT_ERROR_RATE"))
        ? (float) getenv("RECEIPT_ERROR_RATE") : null,
      "backup_age_seconds" => ctype_digit((string) getenv("RECEIPT_BACKUP_AGE"))
        ? (int) getenv("RECEIPT_BACKUP_AGE") : null,
      "failure_codes" => $failures === "" ? [] : explode(",", $failures),
    ];
    echo json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
  ' > "$receipt_path"
fi

printf '%s\n' "$status"
exit "$exit_code"
