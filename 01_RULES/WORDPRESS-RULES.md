# SquirrelForge WordPress Rules

Version: 1.0.0
Status: Stable
Owner: SquirrelForge Maintainers
Depends On: `AGENT-BEHAVIOR.md`
Used By: WordPress workflows and agents
Last Updated: 2026-07-01

- Validate and sanitize input; escape output for its context.
- Enforce capabilities and nonces for privileged state changes.
- Use WordPress APIs and coding standards where applicable.
- Parameterize database queries and avoid direct schema assumptions.
- Preserve backward compatibility or document a governed breaking change.
- Make strings translatable and interfaces accessible.
- Load assets only where needed and measure performance-sensitive changes.
- Add tests appropriate to the risk and record validation evidence.
