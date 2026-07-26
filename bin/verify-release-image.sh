#!/bin/sh
set -eu

required_commands="cosign gh trivy"
for command_name in $required_commands; do
  if ! command -v "$command_name" >/dev/null 2>&1; then
    echo "Admission verification requires $command_name." >&2
    exit 1
  fi
done

image="${SQUIRRELFORGE_ADMISSION_IMAGE:-}"
expected_repository="${SQUIRRELFORGE_ADMISSION_REPOSITORY:-}"
source_repository="${SQUIRRELFORGE_ADMISSION_SOURCE_REPOSITORY:-}"
identity_regexp="${SQUIRRELFORGE_ADMISSION_CERTIFICATE_IDENTITY_REGEXP:-}"
oidc_issuer="${SQUIRRELFORGE_ADMISSION_OIDC_ISSUER:-https://token.actions.githubusercontent.com}"
severities="${SQUIRRELFORGE_ADMISSION_BLOCK_SEVERITIES:-CRITICAL,HIGH}"
receipt_path="${SQUIRRELFORGE_ADMISSION_RECEIPT_PATH:-}"

if [ -z "$image" ] || [ -z "$expected_repository" ] || [ -z "$source_repository" ] || [ -z "$identity_regexp" ]; then
  echo "Admission image, expected repository, source repository, and certificate identity are required." >&2
  exit 1
fi

case "$image" in
  "$expected_repository"@sha256:[0-9a-f][0-9a-f]*)
    ;;
  *)
    echo "Admission rejected: image must use the expected repository and an immutable SHA-256 digest." >&2
    exit 1
    ;;
esac

digest="${image##*@}"
case "$digest" in
  sha256:*[!0-9a-f]*)
    echo "Admission rejected: image digest contains non-hexadecimal characters." >&2
    exit 1
    ;;
esac

if [ "${#digest}" -ne 71 ]; then
  echo "Admission rejected: image digest has an invalid length." >&2
  exit 1
fi

cosign verify \
  --certificate-identity-regexp "$identity_regexp" \
  --certificate-oidc-issuer "$oidc_issuer" \
  "$image" >/dev/null

gh attestation verify "oci://$image" \
  --repo "$source_repository" >/dev/null

gh attestation verify "oci://$image" \
  --repo "$source_repository" \
  --predicate-type https://cyclonedx.org/bom >/dev/null

trivy image \
  --exit-code 1 \
  --severity "$severities" \
  --scanners vuln \
  --quiet \
  "$image"

if [ -n "$receipt_path" ]; then
  if [ -e "$receipt_path" ]; then
    echo "Admission receipt output already exists." >&2
    exit 1
  fi
  umask 077
  printf '{\n  "schema_version": 1,\n  "status": "PASSED",\n  "image": "%s",\n  "verified_at": "%s",\n  "signature": "VERIFIED",\n  "provenance": "VERIFIED",\n  "sbom": "VERIFIED",\n  "vulnerability_policy": "PASSED"\n}\n' \
    "$image" "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" > "$receipt_path"
fi

printf '%s\n' \
  "SquirrelForge image admission PASSED." \
  "repository=$expected_repository" \
  "digest=$digest" \
  "signature=VERIFIED" \
  "provenance=VERIFIED" \
  "sbom=VERIFIED" \
  "vulnerability_policy=PASSED"
