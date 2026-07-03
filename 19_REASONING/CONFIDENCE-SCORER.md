# SquirrelForge Confidence Scorer

Version: 1.0.0
Status: Draft
Owner: SquirrelForge Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-01

## Purpose

The Confidence Scorer evaluates how reliable a decision or implementation strategy is based on available evidence, validation history, project knowledge, and identified risks.

## Responsibilities

- Assign a confidence score.
- Evaluate supporting evidence.
- Consider validation history.
- Consider known risks.
- Consider rule compliance.
- Record confidence rationale.
- Provide recommendations when confidence is low.

## Confidence Evaluation Process

1. Receive the completed explanation.
2. Evaluate supporting evidence.
3. Review rule compliance.
4. Review validation results.
5. Review identified risks.
6. Calculate the confidence level.
7. Record the reasoning behind the score.
8. Forward the final recommendation.

## Confidence Factors

| Factor | Description |
|---|---|
| Validation | Has the approach been successfully validated? |
| Rule Compliance | Does it satisfy all applicable rules? |
| Risk Level | What is the remaining implementation risk? |
| Evidence | How much supporting evidence exists? |
| Knowledge | Has this approach succeeded before? |
| Complexity | Does complexity reduce confidence? |

## Confidence Levels

| Level | Meaning |
|---|---|
| Very High | Strong evidence, validated, minimal risk |
| High | Well-supported with manageable risk |
| Moderate | Acceptable but requires monitoring |
| Low | Limited evidence or elevated risk |
| Very Low | Significant uncertainty; review recommended |

## Confidence Record

| Field | Description |
|---|---|
| Decision ID | Related decision |
| Confidence Level | Very High / High / Moderate / Low / Very Low |
| Supporting Evidence | Summary of evidence |
| Remaining Risks | Outstanding concerns |
| Recommendations | Suggested follow-up actions |
| Date | Evaluation date |

## Rule

Every significant decision should receive a documented confidence assessment before execution. Decisions with **Low** or **Very Low** confidence should be reviewed or revised before implementation whenever practical.