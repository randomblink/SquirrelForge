# SquirrelForge Project Loader

Version: 1.0.0
Status: Stable
Owner: Engine Maintainers
Depends On: `12_AGENT/BOOTSTRAP.md`, `01_RULES`, `21_CONFIGURATION`, `28_RUNTIME-CONFIG`
Used By: Engine, Capability Router, Workflow Selector, Task Router
Last Updated: 2026-07-04

## Purpose

The Project Loader establishes verified project context before planning or project-changing execution begins.

It identifies the project root, project type, repository state, applicable instructions, configuration, available interfaces, domain requirements, and current limitations.

The Project Loader does not select the final implementation strategy or perform project-changing actions.

---

## Loading Process

1. Verify the project root and repository identity using the Repository Identity Verification Procedure below.
2. Detect project type and active domains.
3. Inspect current project state and uncommitted work when available.
4. Load root project instructions and mandatory rules.
5. Load project configuration and runtime profile.
6. Discover available interfaces, tools, integrations, and execution limits.
7. Identify relevant domain knowledge without loading unrelated domains.
8. Initialize or restore Engine planning state.
9. Initialize task context with verified evidence and known unknowns.
10. Pass project context to capability routing and workflow selection.
11. Confirm readiness state and publish limitations.

---

## Repository Identity Verification Procedure

Before any write operation, and after any change of working directory into another project, verify repository identity:

1. Run `pwd` to confirm the current working directory.
2. Run `git rev-parse --show-toplevel` to resolve the repository root.
3. Run `git status --short` to inspect the current working tree state.
4. When repository identity remains ambiguous — similarly named projects, nested repositories, or an unclear remote — also run `git remote -v`.
5. Compare the resolved repository root and project name against the user's stated target project.

### Mismatch Behavior

If the resolved repository does not match the user's stated target project:

- stop before making any edit,
- report the current repository path and name and the requested project by name,
- require explicit correction or confirmation before continuing,
- never assume two similarly named projects are the same project.

### Project-Switch Behavior

After any `cd` into a different project directory:

- re-run the full Repository Identity Verification Procedure,
- treat the new repository as a fresh execution context,
- do not carry over file paths, assumptions, staged changes, or commit plans from the prior project.

### Dirty Working Tree Behavior

When `git status --short` reports modified or untracked files before a new task begins:

- do not discard or overwrite the existing changes,
- report the modified and untracked files to the user,
- continue only when the new task can be safely isolated from the existing changes, or the user explicitly directs how to proceed.

### Examples

**SquirrelForge work requested while the current repository is Hospital/CSHD**

```text
pwd → /Users/randomblink/Local Sites/hospital/app/public/wp-content/plugins/cshd
git rev-parse --show-toplevel → .../wp-content/plugins/cshd
Requested project: SquirrelForge

Result: Mismatch. Stop before editing. Report the current repository (Hospital/CSHD)
and the requested project (SquirrelForge). Require the user to confirm the correct
path or explicitly switch directories before continuing.
```

**Hospital/CSHD work requested while the current repository is SquirrelForge**

```text
pwd → /Users/randomblink/Projects/SquirrelForge
git rev-parse --show-toplevel → /Users/randomblink/Projects/SquirrelForge
Requested project: Hospital/CSHD

Result: Mismatch. Stop before editing. Report the current repository (SquirrelForge)
and the requested project (Hospital/CSHD). Do not assume the two are the same project
because both are WordPress-adjacent.
```

**User switches repositories mid-session**

```text
Session starts in SquirrelForge; work proceeds normally.
User then asks for a change in Hospital/CSHD and the agent `cd`s into that repository.

Result: Re-run the full verification procedure in the new directory. Treat
Hospital/CSHD as a fresh execution context. Do not reuse SquirrelForge file paths,
staged changes, or commit plans.
```

**Working tree is dirty before a new task**

```text
git status --short → M includes/class-something.php (modified, unrelated to the new request)

Result: Do not discard or overwrite the modification. Report it to the user.
Proceed only if the new task can be isolated from that file, or the user explicitly
directs how to handle it.
```

---

## Project Context Record

The Project Loader should produce a structured context record containing, when available:

- project root,
- repository identity,
- active branch or revision,
- project type,
- active domains,
- relevant instructions,
- applicable rules,
- configuration profile,
- runtime environment,
- available tools and interfaces,
- permission boundaries,
- current changes,
- known test commands or validation paths,
- known risks,
- missing context,
- and readiness state.

Unknown values must remain explicitly unknown rather than being guessed.

---

## Core Components

| Component | Location |
|---|---|
| Workflow Selector | `14_ENGINE/WORKFLOW-SELECTOR.md` |
| Task Router | `14_ENGINE/TASK-ROUTER.md` |
| State Manager | `14_ENGINE/STATE-MANAGER.md` |
| Context Manager | `14_ENGINE/CONTEXT-MANAGER.md` |
| Validation | `14_ENGINE/VALIDATION.md` |
| Output Rules | `14_ENGINE/OUTPUT-RULES.md` |

---

## Domain Loading Rule

The Project Loader identifies relevant domains but does not force-load every domain layer.

Examples:

- WordPress plugin or theme project → identify `38_WORDPRESS` as relevant.
- General architecture cleanup → load architecture, overview, Agent, Engine, and affected layer documents.
- Runtime PHP implementation → inspect `src/`, `tests/`, configuration, interfaces, and execution requirements.

WordPress must not be assumed merely because SquirrelForge supports WordPress.

---

## Readiness States

| State | Meaning |
|---|---|
| `READY` | Required project context is available for the requested work. |
| `READY_WITH_LIMITATIONS` | Work may continue, but unavailable tools, tests, interfaces, or context must be disclosed. |
| `BLOCKED` | Required project identity, permission, context, or dependency is missing. |
| `RECOVERY_REQUIRED` | Existing incomplete or unsafe project state must be resolved first. |
| `LOAD_FAILED` | The project could not be loaded and no safe degraded path exists. |

---

## Startup Checklist

- [ ] Project root verified
- [ ] Repository or project identity verified
- [ ] Project type identified
- [ ] Active domains identified
- [ ] Current state inspected
- [ ] Applicable instructions loaded
- [ ] Mandatory rules loaded
- [ ] Configuration and runtime profile loaded where applicable
- [ ] Available interfaces and tools identified
- [ ] Permission boundaries identified
- [ ] Relevant domain knowledge identified
- [ ] State initialized or restored
- [ ] Context initialized with evidence and unknowns
- [ ] Readiness state published

---

## Rule

> A project is ready only when the Project Loader has verified enough context for the requested work and has disclosed any remaining limitations. Readiness must not be inferred from documentation presence alone.
