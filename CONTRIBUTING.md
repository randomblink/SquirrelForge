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

No implementation should bypass architecture, governance, or security requirements.

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

If the answer to any of these is "no," revise the change before merging.

## What Comes Next

At this point, you've essentially completed the architectural specification of SquirrelForge. The next phase is not another documentation file—it's the reference implementation.

The recommended implementation order is:

1. Core framework (`src/Core`)
2. Shared interfaces (`src/Contracts`)
3. Event bus
4. Dependency injection container
5. Plugin/module loader
6. Workflow engine
7. Agent runtime
8. Tool adapter framework
9. Memory subsystem
10. Observability subsystem

This marks the transition from architecture to building the actual AI agent runtime.
