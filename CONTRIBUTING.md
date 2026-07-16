# Contributing to SquirrelForge

Thank you for contributing to SquirrelForge.

This project is designed as a modular, maintainable AI agent architecture. Consistency, clarity, and long-term maintainability are valued over short-term convenience.

## Guiding Principles

Every contribution should strive to make SquirrelForge:

- Easier to understand
- Easier to extend
- Easier to test
- Easier to maintain
- More secure
- More observable
- More explainable

When in doubt, favor simplicity and explicitness.

## Evidence-First Methodology

SquirrelForge uses an evidence-first engineering methodology. Existing certified work is treated as correct until new evidence demonstrates otherwise. Contributors must not expand a taxonomy, create a category, duplicate ownership, or add a `WP-ERROR-XXX` entry merely to increase catalog coverage.

Evaluate technical claims using the strongest available evidence, in this order:

1. Runtime verification.
2. WordPress core source.
3. Official documentation.
4. Architectural reasoning.

Repository precedent and prior reviews provide context, but they do not override current runtime behavior or authoritative source evidence. When evidence is incomplete, record what remains unknown and what must be verified before proposing a repository change.

Every analysis and review should distinguish clearly between:

- **Verified fact:** directly supported by captured runtime behavior, authoritative source, or another identified artifact.
- **Architectural reasoning:** a conclusion derived from established ownership boundaries and specifications.
- **Inference:** a provisional conclusion supported indirectly by available evidence.
- **Speculation:** an unverified possibility that must not be presented as established fact.

The governing requirements remain in the engineering specifications. This guide summarizes how contributors enter that system; it does not replace those authorities.

## Starting Repository Work

Before changing the repository:

1. Read [`AGENTS.md`](AGENTS.md) and follow [`AI-BOOTSTRAP.md`](AI-BOOTSTRAP.md).
2. Verify the repository root, branch, current commit, and working-tree state as required by [`14_ENGINE/PROJECT-LOADER.md`](14_ENGINE/PROJECT-LOADER.md).
3. Preserve unrelated work and identify applicable repository instructions.
4. State the exact task and whether research must finish before modification begins.

A short task prompt should identify the active state and stop condition:

```text
We are working on the SquirrelForge repository.

Current branch: <branch>
Current commit: <commit>

Task:
<specific task>

Do not modify the repository until the research is complete.
Flag any uncertainty before proposing changes.
```

Replace the placeholders with values verified from the current repository. Do not reuse a commit identifier from an earlier session without checking it.

## Change Classification and Scope

Before proposing a change, identify:

- the affected specification, taxonomy, knowledge entry, evidence record, review, runtime component, or other artifact;
- every document or implementation file that would be affected;
- why the change belongs with those owners; and
- why the proposed scope is the smallest sufficient scope.

Classify the work as one of the following:

- **New work:** a new capability or artifact supported by an established need and applicable governance.
- **Post-certification correction:** a correction to certified work supported by new evidence. Category taxonomy and knowledge-entry corrections follow [`SF-SPEC-013`](docs/standards/SF-SPEC-013-KNOWLEDGE-CATEGORY-LIFECYCLE.md); other artifacts follow their own governing specification and versioning policy.
- **Maintenance:** a non-semantic correction such as a valid link repair, inventory update, or implementation-preserving cleanup.
- **Research only:** investigation that produces findings or evidence but does not yet authorize a repository change.

Do not silently convert research into implementation or use a maintenance label to bypass lifecycle, review, or post-certification requirements.

## Development Workflow

All work should follow this general lifecycle:

1. Define the problem.
2. Design the solution.
3. Review architecture impacts.
4. Implement the change.
5. Validate functionality.
6. Add or update tests.
7. Update documentation.
8. Submit for review.

No implementation should bypass architecture, governance, evidence, review, or security requirements. Research-first tasks must remain read-only until their stated research gate is complete.

## WordPress Knowledge Work

WordPress knowledge work must preserve existing category and entry ownership. Before proposing a taxonomy or `WP-ERROR-XXX` change:

1. Inspect the governing taxonomy and existing sibling entries.
2. Determine whether an existing entry already owns the condition or its observable failure stage.
3. Verify technical claims against WordPress core source or runtime behavior whenever practical.
4. Treat a new category or entry as unsupported until evidence establishes a genuine ownership gap.
5. Follow [`SF-SPEC-001`](docs/standards/SF-SPEC-001-ERROR-KNOWLEDGE.md) for error knowledge and [`SF-SPEC-013`](docs/standards/SF-SPEC-013-KNOWLEDGE-CATEGORY-LIFECYCLE.md) for category lifecycle and post-certification change.

Avoid taxonomy creep, speculative categories, duplicated ownership, symptom-only entries that duplicate an existing mechanism, and unsupported claims presented as catalog facts.

## Runtime Verification

Runtime verification must use a disposable environment unless the user explicitly authorizes another target. Follow [`SF-SPEC-002`](docs/standards/SF-SPEC-002-RUNTIME-EVIDENCE.md), [`SF-SPEC-006`](docs/standards/SF-SPEC-006-REPOSITORY-VALIDATION.md), [`SF-SPEC-011`](docs/standards/SF-SPEC-011-EVIDENCE-GOVERNANCE.md), and the [`WP-VERIFICATION-XXX` series convention](docs/knowledge/verifications/README.md).

Capture at minimum:

- environment and relevant versions;
- baseline state;
- exact trigger;
- observed behavior;
- negative validation;
- cleanup and restored state;
- differences from current documentation; and
- repository changes required by the evidence, if any.

Runtime evidence must be reproducible, traceable to the claim it supports, and kept separate from inference or expected behavior.

## Review and Production Readiness

Engineering review is governed by [`SF-SPEC-005`](docs/standards/SF-SPEC-005-ENGINEERING-REVIEW.md). Reviewer independence is governed by [`SF-SPEC-012`](docs/standards/SF-SPEC-012-REVIEW-INDEPENDENCE.md):

- **Class A:** author review; may find and correct defects but cannot by itself satisfy an independence requirement or authorize a lifecycle state conditioned on independent review.
- **Class B:** independent review satisfying the specification's independence requirements.
- **Class C:** qualified human engineering review when another authority explicitly requires it.

`Production Ready` is not a general claim that a document looks complete. Its requirements depend on the artifact type and its governing specification. A contributor must identify and satisfy the applicable definition, evidence, review class, validation, and lifecycle requirements before changing that status.

## Coding Standards

Contributors should:

- Follow the project's established architecture.
- Keep components focused on a single responsibility.
- Prefer composition over inheritance where appropriate.
- Avoid unnecessary complexity.
- Write self-explanatory code.
- Remove dead code rather than commenting it out.
- Keep public interfaces stable whenever possible.

## Documentation Standards

Every major component should include:

- Purpose
- Responsibilities
- Inputs
- Outputs
- Dependencies
- Process description
- Error handling
- Extension points
- Completion criteria

Documentation should explain why a component exists, not only what it does.

## Naming Conventions

Use clear, descriptive names.

Examples:

- `WorkflowManager`
- `MemoryStore`
- `ActionDispatcher`
- `RuleEvaluator`
- `ToolRegistry`

Avoid abbreviations unless they are widely understood.

## Repository Organization

Each layer owns its own documentation.

New components should be placed in the appropriate directory rather than creating duplicate responsibilities elsewhere.

## Testing Expectations

Every significant change should be validated.

Testing may include:

- Unit tests
- Integration tests
- Workflow validation
- Security validation
- Performance checks
- Regression testing

Bug fixes should include a test that prevents the issue from recurring.

## Documentation Updates

If behavior changes, update the relevant documentation in the same change.

Architecture documentation should remain synchronized with implementation.

## Pull Request Checklist

Before submitting a change:

- [ ] Architecture reviewed
- [ ] Code builds successfully
- [ ] Tests pass
- [ ] Documentation updated
- [ ] No unnecessary dependencies introduced
- [ ] Security implications considered
- [ ] Observability maintained
- [ ] Breaking changes documented

## Review Criteria

Changes are evaluated based on:

- Correctness
- Clarity
- Maintainability
- Security
- Performance
- Testability
- Documentation quality
- Architectural consistency

## Design Philosophy

SquirrelForge favors:

- Explicit behavior over implicit behavior
- Small focused components
- Loose coupling
- Clear interfaces
- Provider independence
- Explainable decisions
- Observable execution
- Controlled evolution

## Questions to Ask Before Merging

- Does this solve the intended problem?
- Does it fit the architecture?
- Is the behavior understandable?
- Can it be tested?
- Is it secure?
- Is it observable?
- Is it documented?
- Will it still make sense a year from now?
- Which statements are verified facts, architectural reasoning, inferences, or speculation?
- Does the evidence support the exact scope of the change?
- Does an existing owner already cover this responsibility?
- Have post-certification requirements been applied where relevant?

If the answer to any of these is "no," revise the change before merging.
