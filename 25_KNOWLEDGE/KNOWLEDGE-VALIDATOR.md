# SquirrelForge Knowledge Validator

Version: 1.0.0
Status: Stable
Owner: Knowledge Maintainers
Depends On: `25_KNOWLEDGE/KNOWLEDGE-REGISTRY.md`, `25_KNOWLEDGE/CITATION-MANAGER.md`, `25_KNOWLEDGE/KNOWLEDGE-VERSIONING.md`, `14_ENGINE/VALIDATION.md`, `19_REASONING/RULE-EVALUATOR.md`, `23_GOVERNANCE/POLICY-ENGINE.md`
Used By: `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md`, `25_KNOWLEDGE/KNOWLEDGE-REGISTRY.md`, `25_KNOWLEDGE/SEMANTIC-SEARCH.md`, Reasoning, Agents, Workflows
Last Updated: 2026-07-08

## Purpose

The Knowledge Validator owns knowledge-domain quality assessment.

It checks provenance completeness, source adequacy, citation/reference evidence, freshness indicators, consistency, contradiction signals, and trust assessment before knowledge becomes available for retrieval or reasoning.

The Knowledge Validator consumes citation status from `25_KNOWLEDGE/CITATION-MANAGER.md` and reports validation/trust results to `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md` and `25_KNOWLEDGE/KNOWLEDGE-REGISTRY.md`.

It does not own platform-wide validation, evaluate governance policy, perform rule evaluation, own citation records, create version records, independently activate or promote knowledge, own raw source storage, or own general logging, audit, or observability infrastructure.

---

## Responsibilities

- Assess knowledge-domain integrity.
- Check required metadata and provenance completeness.
- Assess source adequacy and freshness indicators.
- Consume citation/reference status from `25_KNOWLEDGE/CITATION-MANAGER.md`.
- Detect consistency issues and contradiction signals.
- Assign knowledge trust assessment results.
- Produce Knowledge Validation Records.
- Report validation/trust results to Knowledge Manager and Knowledge Registry.

---

## Validation Process

1. Receive knowledge for validation.
2. Verify registration.
3. Check required metadata and provenance completeness.
4. Assess source adequacy.
5. Read citation/reference status from `25_KNOWLEDGE/CITATION-MANAGER.md`.
6. Check consistency with existing registered knowledge.
7. Detect contradiction signals without performing general rule evaluation.
8. Assess freshness indicators.
9. Assign trust assessment result.
10. Produce validation/trust result references.
11. Report results to `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md` and `25_KNOWLEDGE/KNOWLEDGE-REGISTRY.md`.

---

## Validation Categories

| Category | Description |
|---|---|
| Structure | Required fields and metadata |
| Provenance | Source, owner, and origin completeness |
| Source Adequacy | Whether supplied sources are adequate for trusted use |
| Citation Reference | Citation status supplied by `25_KNOWLEDGE/CITATION-MANAGER.md` |
| Completeness | Required information present |
| Consistency | Alignment with existing registered knowledge |
| Contradiction Signals | Potential conflicts requiring review |
| Freshness | Age and update indicators |
| Trust | Knowledge-domain trust assessment |

---

## Validation Status

| Status | Meaning |
|---|---|
| Pending | Awaiting validation |
| Valid | Passed all required checks |
| Warning | Minor issues identified |
| Failed | Validation unsuccessful |
| Rejected | Not approved for use |

---

## Validation Record

| Field | Description |
|---|---|
| Validation ID | Unique identifier |
| Knowledge ID | Validated knowledge asset |
| Registry Reference | Registry entry assessed |
| Citation Status Reference | Citation/reference status consumed from Citation Manager |
| Version Reference | Version reference from Knowledge Versioning, when applicable |
| Result | Pass / Warning / Fail |
| Trust Level | Assigned trust classification |
| Limitations | Missing, unavailable, stale, or uncertain evidence |
| Timestamp | Validation time |
| Notes | Validation observations |

---

## Validation Checklist

- Knowledge is registered.
- Required metadata exists.
- Provenance is complete enough for the intended use.
- Source adequacy has been assessed.
- Citation/reference status has been consumed from Citation Manager where required.
- Trust requirements are satisfied.
- Consistency and contradiction signals have been checked.
- Freshness indicators have been assessed.
- Limitations or unavailable evidence are recorded.

---

## Boundary With Other Validators

Knowledge validation is not platform-wide validation.

`14_ENGINE/VALIDATION.md` coordinates task, workflow, output, acceptance, and completion evidence. The Knowledge Validator only assesses knowledge-domain quality.

`19_REASONING/RULE-EVALUATOR.md` evaluates proposed decisions or actions against rules. The Knowledge Validator may identify contradiction signals in knowledge content, but it does not perform rule evaluation or decide rule compliance.

`23_GOVERNANCE/POLICY-ENGINE.md` evaluates governance and policy compliance. The Knowledge Validator does not evaluate governance policy.

`25_KNOWLEDGE/CITATION-MANAGER.md` owns citation records and citation status. The Knowledge Validator consumes those citation/reference results.

`25_KNOWLEDGE/KNOWLEDGE-VERSIONING.md` owns version records. The Knowledge Validator may reference a version but does not create or mutate version history.

---

## Permission Boundary

The Knowledge Validator may assess knowledge-domain quality, produce validation/trust results, identify limitations, and report results to the Knowledge Manager and Knowledge Registry.

It must not own platform-wide validation, perform rule evaluation, evaluate governance policy, own citation records, create version records, independently activate or promote knowledge, own raw source storage, or own general logging, audit, storage, or observability infrastructure.

---

## Domain Rule

Knowledge-domain quality assessment applies identically regardless of domain. Domain-specific knowledge may require domain evidence, but the Knowledge Validator still assesses knowledge quality rather than replacing the domain owner.

---

## Rule

No knowledge asset may be treated as trusted Knowledge Layer content until it has a Knowledge Validation Record or an explicitly recorded limitation, exception, or unavailable-evidence status routed through the Knowledge Manager.
