Status: Stable

---
# SquirrelForge WordPress Performance Knowledge

## Purpose

Defines performance knowledge for plugins, themes, blocks, REST endpoints, database operations, assets, cron tasks, and admin screens.

## Review Areas

Review PHP execution cost, database queries, caching, autoloaded options, REST response size, cron workload, assets, JavaScript, CSS, images, and external API calls.

## Output

This Knowledge file must support:

- performance review notes;
- performance risk classification;
- optimization recommendations;
- measurement or evidence requirements;
- and performance validation handoff.

## Validation Requirements

Performance guidance is valid only when:

- claims are supported by measurement, trace, benchmark, code-path analysis, or documented limitation;
- database query cost and caching behavior are reviewed where relevant;
- asset loading, bundle size, and frontend impact are considered;
- REST response size and external API latency are considered where relevant;
- cron and background workload are bounded;
- and performance changes do not weaken correctness, security, accessibility, or maintainability.

## Handoff Rules

- Performance-sensitive findings route to `33_WORDPRESS_ROLES/PERFORMANCE-ENGINEER.md`.
- Database performance issues route to the database implementation owner.
- Frontend asset issues route to the relevant JavaScript, block, or theme role.
- Security tradeoffs discovered during optimization route to `33_WORDPRESS_ROLES/SECURITY-ENGINEER.md`.

## Completion Criteria

This Knowledge file is complete when performance work can be reviewed using evidence, scoped risk, and clear validation expectations.

## Rule

Performance claims must be evidence-based and must not weaken correctness, security, accessibility, or maintainability.
