#!/usr/bin/env bash
#
# validate-repo.sh
#
# Standalone, manually-run mechanical check for the two recurring defect
# classes SF-REVIEW-032, SF-REVIEW-039, SF-REVIEW-040, and SF-REVIEW-052
# each found only via a dedicated, after-the-fact category consistency
# review: (A) a sibling entry still citing another entry as a
# "conceptual reference" after that entry has actually been authored,
# and (B) an SF-TAXONOMY-XXX status table that disagrees with an
# entry's own Status field.
#
# This does not replace a category consistency review under SF-SPEC-013
# Section 5.4 -- it catches the two specific, deterministic gap classes
# SF-SPEC-013 Section 5.7 already names, so they can be caught before a
# promotion or taxonomy-update commit lands rather than only by the next
# scheduled review. See FRAMEWORK-OBSERVATIONS.md, 2026-07-14 entry.
#
# Check A intentionally scopes to *live* citing documents only --
# docs/knowledge/wp-errors/*.md and docs/standards/SF-TAXONOMY-*.md --
# not docs/reviews/*.md. A review record's "conceptual reference" text
# describes repository state as of that review's own date and is
# preserved unmodified by design (SF-SPEC-012 Section 4.3; SF-SPEC-013
# Section 5.8): it is evidence, not a live cross-reference that should
# track the entry's current status. Only a citation in a document that
# itself claims to describe *current* repository state is a defect.
#
# Usage: scripts/validate-repo.sh [repo-root]
# Exit status: 0 if no issues found, 1 if any issue found.

set -uo pipefail

ROOT="${1:-.}"
KNOWLEDGE_DIR="$ROOT/docs/knowledge/wp-errors"
STANDARDS_DIR="$ROOT/docs/standards"

issues=0

echo "== Check A: stale 'conceptual reference' citations =="
echo

# Find every "WP-ERROR-XXX ... (conceptual reference" citation anywhere
# under docs/, then check whether WP-ERROR-XXX actually exists now.
while IFS=: read -r file line rest; do
    id=$(echo "$rest" | grep -oE 'WP-ERROR-[0-9]+' | head -1)
    [ -z "$id" ] && continue

    match=$(find "$KNOWLEDGE_DIR" -maxdepth 1 -iname "${id}-*.md" 2>/dev/null | head -1)
    if [ -n "$match" ]; then
        echo "STALE: $file:$line cites $id as a conceptual reference, but $match now exists."
        issues=$((issues + 1))
    fi
done < <(grep -rn "conceptual reference" "$KNOWLEDGE_DIR" "$STANDARDS_DIR" 2>/dev/null)

if [ "$issues" -eq 0 ]; then
    echo "No stale conceptual-reference citations found."
fi

echo
echo "== Check B: SF-TAXONOMY-XXX status table vs. entry Status field =="
echo

for tax in "$STANDARDS_DIR"/SF-TAXONOMY-*.md; do
    [ -f "$tax" ] || continue
    echo "-- $tax --"

    # Table rows look like: | `WP-ERROR-021` | Title | Owns | Status text |
    while IFS='|' read -r _ entrycol _ _ statuscol _; do
        id=$(echo "$entrycol" | grep -oE 'WP-ERROR-[0-9]+')
        [ -z "$id" ] && continue

        tax_status=$(echo "$statuscol" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')
        entry_file=$(find "$KNOWLEDGE_DIR" -maxdepth 1 -iname "${id}-*.md" 2>/dev/null | head -1)

        if [ -z "$entry_file" ]; then
            if [[ "$tax_status" == Existing* ]]; then
                echo "MISMATCH: $tax lists $id as \"$tax_status\" but no such entry file exists."
                issues=$((issues + 1))
            fi
            continue
        fi

        actual_status=$(grep -m1 -E '^\*?[[:space:]]*\*\*Status:\*\*' "$entry_file" | sed -E 's/^\*?[[:space:]]*\*\*Status:\*\*[[:space:]]*//')

        if [[ "$tax_status" == Planned* ]] && [ -n "$actual_status" ]; then
            echo "MISMATCH: $tax lists $id as \"$tax_status\" but $entry_file's own Status is \"$actual_status\" (entry now exists)."
            issues=$((issues + 1))
        elif [[ "$tax_status" == Existing* ]] && [ -n "$actual_status" ] && [[ "$tax_status" != *"$actual_status"* ]]; then
            echo "MISMATCH: $tax lists $id as \"$tax_status\" but $entry_file's own Status is \"$actual_status\"."
            issues=$((issues + 1))
        fi
    done < <(grep -E '^\| `WP-ERROR-[0-9]+`' "$tax")
done

echo
if [ "$issues" -eq 0 ]; then
    echo "RESULT: clean. No stale conceptual references, no taxonomy/entry status drift."
    exit 0
else
    echo "RESULT: $issues issue(s) found."
    exit 1
fi
