# SF-REVIEW-168 — Repository Hygiene Inventory

# 1. Review Information

**Review ID:** SF-REVIEW-168

**Review Date:** 2026-07-16

**Status:** Inventory Complete — structural changes not yet approved by evidence classification

# 2. Purpose

Establish the repository-hygiene baseline before moving, renaming, archiving, merging, or deleting any tracked artifact. This review separates observed facts from recommendations and preserves repository cleanup as work distinct from `WP-VERIFICATION-004`.

# 3. Baseline

- Starting branch: `agent/repository-hygiene`
- Starting commit: `26132dc` (`Merge WP-VERIFICATION-004 correction`)
- Tracked files: 801
- Repository Markdown files outside `vendor/`: 674
- Markdown files under `docs/`: 253
- Review records: 168
- WP-ERROR documents: 43 (including directory navigation artifacts)
- WP-VERIFICATION documents: 5 (four records plus README)
- Existing top-level architecture map: `ARCHITECTURE.md`
- Existing numbered-layer README files: present throughout the numbered architecture
- Existing `docs/README.md`: absent

The raw, sorted file and tree inventories were generated under `/private/tmp` for analysis and were not added to the repository as root-level clutter. This review is the durable inventory artifact.

# 4. Inventory by Ownership

| Area | Purpose | Current observation |
|---|---|---|
| Root numbered layers | AI-agent architecture and operating knowledge | Deliberate architecture documented by `ARCHITECTURE.md`; not root clutter to relocate generically |
| `src/` and `tests/` | PHP runtime and regression suite | Established executable implementation; no structural defect found during inventory |
| `docs/standards/` | Specifications, methodologies, templates, and taxonomies | Mixed artifact classes, but filenames and stable citations make relocation high-churn; navigation should precede any move |
| `docs/knowledge/wp-errors/` | Certified WordPress error knowledge | Stable identifiers and extensive cross-references; leave in place |
| `docs/knowledge/verifications/` | Reference Implementation evidence | Has its own README and sequential records; leave in place |
| `docs/reviews/` | Author, independent, consistency, baseline, and governance reviews | 168 stable sequential records in one directory; categorization would aid navigation, but moving them would rewrite a very large citation graph |
| `docs/baselines/` | Framework baseline declarations | Small, distinct authority class; leave in place |
| `docs/engineering/` | Plans and framework observations | Active engineering-history artifacts; classify before any archive decision |
| `docs/validation/` | Validation records outside WP-VERIFICATION | Small, distinct validation area; leave in place pending navigation |

# 5. Findings and Classification

| ID | Verified fact | Classification | Recommendation | Evidence |
|---|---|---|---|---|
| RH-001 | `docs/` and most major `docs/` sections lack navigation README files. | Add | Add navigation without relocating authorities. | Directory inventory |
| RH-002 | A relative-link sweep found 17 unresolved Markdown targets. Most are review records linking to WP-ERROR files as though those files lived in `docs/reviews/`. | Correct | Resolve only genuine links; retain illustrative `file.md` example text. | Relative-link sweep from every Markdown file |
| RH-003 | `33_AUTOMATION` and `33_WORDPRESS_ROLES` reuse the same layer number, while `ARCHITECTURE.md` says layer numbers must not be reused and identifies `38_WORDPRESS` as the WordPress boundary. | Relocate candidate | Do not rename ad hoc. Analyze references and propose moving WordPress roles under `38_WORDPRESS/` or another explicitly-owned location. Requires owner decision because it moves tracked authorities. | Root tree and `ARCHITECTURE.md` Cleanup Rules |
| RH-004 | Six empty directories exist inside the standard plugin template. | Leave Alone pending template audit | Empty scaffolding may be intentional because Git does not preserve empty directories without placeholders; do not delete based on emptiness alone. | Filesystem inventory |
| RH-005 | `.DS_Store`, PHPUnit cache, and `vendor/` are present locally but ignored and untracked. | Leave Alone | They are local generated state, not repository contents. | `git ls-files`, `git check-ignore` |
| RH-006 | A basename-only orphan heuristic produced 137 candidates, dominated by review records and architecture components designed as leaf authorities. | Research only | Do not archive or delete from heuristic output. Build a dependency-aware classifier before acting. | Candidate-orphan sweep and ownership review |
| RH-007 | The repository already has `ARCHITECTURE.md` with an architecture map and cleanup rules. | Leave Alone / improve navigation | Do not add a competing `docs/ARCHITECTURE.md`; link the existing authority from new navigation. | Direct document review |
| RH-008 | Splitting 168 review files into new folders would require widespread link and history churn while adding no technical accuracy. | Leave Alone for current phase | Add an indexed README first; reconsider physical partitioning only if dependency analysis proves the benefit exceeds link churn. | Review count and stable identifier usage |

# 6. Immediate Low-Risk Cleanup Program

1. Add `docs/README.md` and section README files that explain purpose, audience, contents, and reading order.
2. Correct the verified broken relative links without changing historical findings.
3. Expand `scripts/validate-repo.sh` with a Markdown relative-link check so repaired links cannot regress.
4. Analyze every reference to `33_WORDPRESS_ROLES` and present a bounded relocation proposal before moving tracked files.
5. Do not archive, rename, or delete any knowledge authority based only on age, file count, or an orphan heuristic.

# 7. Explicit Non-Findings

- Duplicate README basenames are expected because README files are directory entry points.
- Sequential review records are not duplicate concepts merely because they use similar templates.
- Stable WP-ERROR, SF-SPEC, SF-TAXONOMY, SF-REVIEW, and WP-VERIFICATION filenames are not cleanup debt.
- The numbered root layers are intentional product architecture, not a generic root-minimalism violation.

# 8. Gate Decision

Proceed with additive navigation, verified link repairs, and validator expansion as separate focused commits. Stop before moving `33_WORDPRESS_ROLES` or any other tracked authority until the reference-impact analysis is complete and the repository owner approves the destination.

# 9. Revision History

| Version | Date | Summary | Status |
|---|---|---|---|
| 1.0 | 2026-07-16 | Initial evidence-first repository inventory and problem classification. No tracked artifact moved, renamed, archived, merged, or deleted. | Inventory Complete |
| 1.1 | 2026-07-16 | Records completion of approved RH-003 remediation after impact analysis: 19 stable WordPress role authorities moved to `38_WORDPRESS/ROLES/`, 13 files explicitly marked as deprecated compatibility references removed, and repository references updated. The Version 1.0 finding above remains unchanged as the historical evidence that prompted the action. | RH-003 Corrected |
