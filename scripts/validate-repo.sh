#!/usr/bin/env bash
#
# validate-repo.sh
#
# Standalone, manually-run mechanical check for recurring defect classes
# this catalog's own category consistency reviews have repeatedly found
# only after the fact: (A) a sibling entry still citing another entry as
# a "conceptual reference" after that entry has actually been authored;
# (B) an SF-TAXONOMY-XXX status table that disagrees with an entry's own
# Status field; (C) a specification missing its required Revision History
# section; and (D) a WP-ERROR entry's own Related Errors intro sentence
# deviating from this catalog's majority wording.
#
# This does not replace a category consistency review under SF-SPEC-013
# Section 5.4 -- it catches specific, deterministic gap classes so they
# can be caught before a promotion or taxonomy-update commit lands rather
# than only by the next scheduled review. See FRAMEWORK-OBSERVATIONS.md,
# 2026-07-14 entries.
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

        if [[ "$tax_status" == Planned* ]] && [ "$actual_status" == "Production Ready" ]; then
            echo "MISMATCH: $tax lists $id as \"$tax_status\" but $entry_file's own Status is \"$actual_status\"."
            issues=$((issues + 1))
        elif [[ "$tax_status" == Existing* ]] && [ -n "$actual_status" ] && [[ "$tax_status" != *"$actual_status"* ]]; then
            echo "MISMATCH: $tax lists $id as \"$tax_status\" but $entry_file's own Status is \"$actual_status\"."
            issues=$((issues + 1))
        fi
    done < <(grep -E '^\| `WP-ERROR-[0-9]+`' "$tax")
done

echo
echo "== Check C: every specification has a Revision History section =="
echo

# Per SF-SPEC-004 Section 5.9 (added SF-REVIEW-058/059; migrated across the
# library by SF-REVIEW-060): every docs/standards/SF-SPEC-*.md file shall
# contain a top-level "Revision History" section as its structure requires.
check_c_issues=0
for spec in "$STANDARDS_DIR"/SF-SPEC-*.md; do
    [ -f "$spec" ] || continue
    if ! grep -qE '^# [0-9]+\. Revision History' "$spec"; then
        echo "MISSING: $spec has no top-level Revision History section (SF-SPEC-004 Section 5.9)."
        issues=$((issues + 1))
        check_c_issues=$((check_c_issues + 1))
    fi
done
if [ "$check_c_issues" -eq 0 ]; then
    echo "All specifications have a Revision History section."
fi

echo
echo "== Check D: Related Errors (Section 16) intro sentence wording =="
echo

# Every WP-ERROR entry's own "# 16. Related Errors" section shall open with
# either this catalog's majority wording, or WP-ERROR-013's own legitimate
# variant (used only when every citation in that entry's own Section 16 is
# genuinely conceptual, with no real links at all). Both are literal,
# deliberately-chosen sentences, not paraphrased shorthand -- any entry
# whose own text differs from both is a wording drift, the defect class
# first caught only by category consistency reviews (SF-REVIEW-094,
# SF-REVIEW-103, SF-REVIEW-112). See FRAMEWORK-OBSERVATIONS.md, 2026-07-14
# entry.
MAJORITY_WORDING="The following are cited as they exist in this repository, or as conceptual distinctions where noted."
CONCEPTUAL_ONLY_WORDING="The following are cited as conceptual distinctions only. No corresponding \`WP-ERROR\` document currently exists in this repository for any of them, and no link is provided."

check_d_issues=0
for entry in "$KNOWLEDGE_DIR"/WP-ERROR-*.md; do
    [ -f "$entry" ] || continue

    intro=$(awk '/^# 16\. Related Errors/{found=1; next} found && NF{print; exit}' "$entry")
    [ -z "$intro" ] && continue

    if [ "$intro" != "$MAJORITY_WORDING" ] && [ "$intro" != "$CONCEPTUAL_ONLY_WORDING" ]; then
        echo "DRIFT: $entry's own Section 16 intro sentence does not match either standard wording:"
        echo "  found: $intro"
        issues=$((issues + 1))
        check_d_issues=$((check_d_issues + 1))
    fi
done
if [ "$check_d_issues" -eq 0 ]; then
    echo "All entries' Section 16 intro sentences match a standard wording."
fi

echo
if [ "$issues" -eq 0 ]; then
    echo "RESULT: clean. No stale conceptual references, no taxonomy/entry status drift, no missing Revision History section, no Related Errors wording drift."
    exit 0
else
    echo "RESULT: $issues issue(s) found."
    exit 1
fi
