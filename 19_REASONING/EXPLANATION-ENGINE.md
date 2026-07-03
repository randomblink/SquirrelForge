# SquirrelForge Explanation Engine

Version: 1.0.0
Status: Draft
Owner: SquirrelForge Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-01

## Purpose

The Explanation Engine produces clear, traceable explanations for the decisions, strategies, and actions taken by SquirrelForge. It enables users and agents to understand not only *what* was chosen, but *why*.

## Responsibilities

- Explain significant decisions.
- Summarize reasoning.
- Reference applicable rules.
- Reference supporting knowledge.
- Describe tradeoffs considered.
- Document execution rationale.
- Prepare explanations for audit and review.

## Explanation Process

1. Receive the execution strategy.
2. Collect supporting decisions.
3. Collect applicable rules.
4. Collect relevant knowledge.
5. Summarize tradeoffs.
6. Generate a clear explanation.
7. Forward the result to the Confidence Scorer.

## Explanation Model

| Field | Description |
|---|---|
| Decision | Decision being explained |
| Goal | Original objective |
| Rules Applied | Governing rules |
| Tradeoffs | Alternatives considered |
| Knowledge Used | Relevant memory or patterns |
| Reasoning | Why this approach was selected |
| Expected Outcome | Intended result |

## Explanation Principles

- Be concise.
- Be technically accurate.
- Reference evidence whenever possible.
- Distinguish facts from assumptions.
- Explain why rejected alternatives were not selected.
- Maintain consistency across explanations.

## Rule

Every significant decision should have a documented explanation that allows another developer or agent to understand and reproduce the reasoning behind it.