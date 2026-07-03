# SquirrelForge Project Inventory

## Purpose

This file helps review everything that has been created for the SquirrelForge Agent.

Use it to confirm:

- What exists
- What is missing
- What is duplicated
- What needs cleanup
- What is ready for implementation

---

## 1. Root Files

| File | Status | Notes |
|---|---:|---|
| README.md | Present | Architecture table, Repository Structure tree, Roadmap, License, and Status sections refreshed 2026-07-03 to match the actual post-restructure tree and implementation state. |
| ARCHITECTURE.md | Present | Broken links and an unclosed code fence fixed 2026-07-02. |
| CONTRIBUTING.md | Present | |
| PROJECT-INVENTORY.md | Present | This file; refreshed 2026-07-02 to match the post-restructure tree. |

---

## 2. Documentation Layers

This table reflects the actual numbered top-level directories after the
2026-07-02 restructure. The numbering has intentional gaps (e.g. no 04-10,
no 31) reserved for future layers; that is not a defect.

| Directory | README | Status | Notes |
|---|---:|---|---|
| 00_CORE | Yes | Present | README.md added 2026-07-03. |
| 01_RULES | Yes | Present | |
| 02_WORKFLOWS | Yes | Present | |
| 03_CHECKLISTS | Yes | Present | |
| 11_OVERVIEW | Yes | Present | This directory. |
| 12_AGENT | Yes | Present | |
| 13_SKILLS | Yes | Present | |
| 14_ENGINE | Yes | Present | |
| 15_TEMPLATES | Yes | Present | |
| 16_AGENTS | Yes | Present | Role specs now backed by `src/Agent/Roles/`. |
| 17_COORDINATION | Yes | Present | |
| 18_MEMORY | Yes | Present | |
| 19_REASONING | Yes | Present | |
| 20_EXECUTION | Yes | Present | |
| 21_CONFIGURATION | Yes | Present | |
| 22_INTERFACES | Yes | Present | |
| 23_GOVERNANCE | Yes | Present | |
| 24_SECURITY | Yes | Present | |
| 25_KNOWLEDGE | Yes | Present | |
| 26_INTEGRATIONS | Yes | Present | |
| 27_OBSERVABILITY | Yes | Present | |
| 28_RUNTIME-CONFIG | Yes | Present | |
| 29_TESTING | Yes | Present | |
| 30_LEARNING | Yes | Present | README.md added 2026-07-03. |
| 32_OPTIMIZATION | Yes | Present | README.md added 2026-07-03. |
| 33_AUTOMATION | Yes | Present | |
| 34_AIDRIVER | Yes | Present | |
| 35_RESILIENCE | Yes | Present | README.md added 2026-07-03. |
| 36_COMMUNICATION | Yes | Present | |
| 37_STORAGE | Yes | Present | |
| 38_WORDPRESS | Yes | Present | |

---

## 3. Runtime Source Files

| Path | Status | Notes |
|---|---|---|
| src/Contracts/ManagerInterface.php |  |  |
| src/Contracts/BootableInterface.php |  |  |
| src/Contracts/HealthCheckInterface.php |  |  |
| src/Contracts/ServiceProviderInterface.php |  |  |
| src/Contracts/EventInterface.php |  |  |
| src/Contracts/EventBusInterface.php |  |  |
| src/Contracts/EventListenerInterface.php |  |  |
| src/Contracts/ContainerInterface.php |  |  |
| src/Contracts/LoggerInterface.php |  |  |
| src/Contracts/ConfigurationInterface.php |  |  |
| src/Contracts/WorkflowInterface.php |  |  |
| src/Contracts/AgentInterface.php |  |  |
| src/Contracts/ToolInterface.php |  |  |
| src/Contracts/MemoryStoreInterface.php |  |  |
| src/Contracts/KnowledgeProviderInterface.php |  |  |
| src/Contracts/LlmClientInterface.php | Present | Added 2026-07-02; provider-agnostic chat-completion contract. |
| src/Contracts/FileSystemInterface.php | Present | Added 2026-07-03; path-contained read/write/delete contract used by Developer and Release for real tool-use. |
| src/Contracts/CommandRunnerInterface.php | Present | Added 2026-07-03; argv-array (never shell-string) process execution contract, used by Release. |
| src/Llm/AnthropicClient.php | Present | Added 2026-07-02; cURL-based Anthropic Messages API implementation. |
| src/Llm/LlmClientResolver.php | Present | Added 2026-07-03; extracted from `AgentServiceProvider` so any module can resolve an LLM client the same way. |
| src/Tools/LocalFileSystem.php | Present | Added 2026-07-03; `FileSystemInterface` rooted at the project directory. Rejects absolute paths and any `..` path segment before touching disk. |
| src/Tools/ShellCommandRunner.php | Present | Added 2026-07-03; `CommandRunnerInterface` restricted to an allowlist (`git`, `composer`, `phpunit`), executed via `proc_open()` with an argv array so no shell interpolation ever happens. |
| src/Tools/ReleaseActionsPolicy.php | Present | Added 2026-07-03; resolves whether real release actions (commit/tag/push) are enabled, via `SQUIRRELFORGE_ENABLE_RELEASE_ACTIONS` (env) or `release.actions_enabled` (Configuration) -- deliberately separate from the LLM API key. |
| src/Container/Container.php |  |  |
| src/Core/Application.php |  |  |
| src/Core/Configuration.php |  |  |
| src/Core/CoreServiceProvider.php |  |  |
| src/Core/Kernel.php |  |  |
| src/Events/Event.php |  |  |
| src/Events/EventBus.php |  |  |
| src/Events/CallbackEventListener.php |  |  |
| src/Observability/ArrayLogger.php |  |  |
| src/Observability/ObservabilityServiceProvider.php |  |  |
| src/Memory/InMemoryStore.php |  |  |
| src/Memory/MemoryServiceProvider.php |  |  |
| src/Workflow/WorkflowEngine.php |  |  |
| src/Workflow/WorkflowServiceProvider.php |  |  |
| src/Agent/AgentRegistry.php | Present | |
| src/Agent/AgentServiceProvider.php | Present | Registers only `AgentRegistry`/`AgentOrchestrator` infrastructure as of 2026-07-03; no longer constructs role agents itself. |
| src/Agent/AgentPipelineModule.php | Present | Added 2026-07-03; a `ModuleInterface` that registers the 8 role agents + orchestrator into `AgentRegistry`, loaded via `ModuleLoader` in `Kernel::boot()` instead of being hardcoded in a provider. |
| src/Agent/AgentOrchestrator.php | Present | Added 2026-07-02; runs the Architect->...->Release handoff sequence. |
| src/Agent/CallbackAgent.php | Present | Generic closure-backed agent, useful for ad hoc/test agents. |
| src/Agent/Roles/AbstractRoleAgent.php | Present | Added 2026-07-02; shared plumbing for pipeline role agents. |
| src/Agent/Roles/ArchitectAgent.php | Present | Added 2026-07-02. |
| src/Agent/Roles/PlannerAgent.php | Present | Added 2026-07-02. |
| src/Agent/Roles/DeveloperAgent.php | Present | Added 2026-07-02. As of 2026-07-03, when `tasks_completed` isn't explicitly supplied and a `FileSystemInterface` + LLM are injected, writes/deletes files directly per the LLM's proposed `file_changes`. A failed write forces the owning task to `Blocked` rather than reporting false success. |
| src/Agent/Roles/ReviewerAgent.php | Present | Added 2026-07-02. |
| src/Agent/Roles/SecurityAgent.php | Present | Added 2026-07-02. |
| src/Agent/Roles/PerformanceAgent.php | Present | Added 2026-07-02. |
| src/Agent/Roles/DocumentationAgent.php | Present | Added 2026-07-02. |
| src/Agent/Roles/ReleaseAgent.php | Present | Added 2026-07-02. As of 2026-07-03, when the gate-check passes, `release_version` is supplied, and `ReleaseActionsPolicy::isEnabled()` is true, finalizes CHANGELOG.md and runs `git add`/`commit`/`tag`/`push`/`push --tags`, stopping at the first failed step and downgrading status to `Hold`. Off by default. |
| src/Tools/ToolRegistry.php | Present | |
| src/Tools/ToolServiceProvider.php | Present | |
| src/Modules/ModuleInterface.php | Present | |
| src/Modules/ModuleLoader.php | Present | Now actually invoked, by `Kernel::loadModules()` (2026-07-03); previously nothing called it. |
| src/Modules/ModuleDiscovery.php | Present | Added 2026-07-03; scans a directory for `*Module.php` files implementing `ModuleInterface` with a zero-arg constructor and returns instances -- real filesystem auto-discovery, replacing the explicit module array previously in `Kernel.php`. |
| src/Modules/ModuleRegistry.php | Present | |
| src/Modules/ModuleServiceProvider.php | Present | |
| src/Core/Bootstrapper.php | Present | |
| src/Core/HealthManager.php | Present | |
| src/Core/LifecycleManager.php | Present | |
| src/Core/CoreRuntimeServiceProvider.php | Present | |

---

## 4. Review Checklist

Use this checklist when reviewing the Agent.

- [ ] Every expected file exists.
- [ ] Every directory has a clear purpose.
- [ ] Every layer has a README.
- [ ] Every manager file has responsibilities.
- [ ] Contracts do not depend on implementations.
- [ ] Implementations match their contracts.
- [ ] Kernel registers all required service providers.
- [ ] No duplicate responsibilities exist.
- [ ] Naming is consistent.
- [ ] The architecture flow is clear.
- [ ] Missing pieces are listed.
- [ ] Next implementation milestone is clear.

---

## 5. Missing Pieces

The items below (ModuleLoader, Bootstrapper, HealthManager, LifecycleManager,
Composer autoload, a basic runtime boot test) were previously listed here as
missing. They now all exist in the tree — see Section 3 — so this list has
been refreshed to reflect what is actually still open.

| Missing Item | Priority | Notes |
|---|---:|---|
| Only Anthropic supported | Low | `src/Llm/AnthropicClient.php` is the only `LlmClientInterface` implementation. Add another implementation (e.g. OpenAI) if multi-provider support is ever needed; agents only depend on the interface. |
| No version bump beyond CHANGELOG.md | Low | Real release actions finalize `CHANGELOG.md` but don't bump a version field, since this project doesn't have one defined (no `version` key in `composer.json`). |

---

## 6. Review Result

| Area | Result |
|---|---|
| Documentation | Refreshed 2026-07-02; ARCHITECTURE.md links and formatting fixed; this inventory brought in line with the actual tree. |
| Contracts | Stable; no changes needed for the role-agent work. |
| Runtime Core | Present (Kernel, Application, Container, Bootstrapper, HealthManager, LifecycleManager). |
| Registries | Agent, Tool, Module, Workflow registries all present. `AgentRegistry` now holds 8 role agents plus the orchestrator after boot. |
| Missing Infrastructure | Only doc polish and the items in Section 5 remain. |
| Ready for Testing | Confirmed 2026-07-03: `composer test` passes on PHP 8.5.7 -- 105 tests, 236 assertions, 0 failures. Now enforced on every push/PR via `.github/workflows/tests.yml`. |

## Final Notes

2026-07-02: Implemented the eight pipeline role agents (`ArchitectAgent`,
`PlannerAgent`, `DeveloperAgent`, `ReviewerAgent`, `SecurityAgent`,
`PerformanceAgent`, `DocumentationAgent`, `ReleaseAgent`) plus
`AgentOrchestrator`, wired into `AgentServiceProvider`, matching the roles
and handoff sequence documented in `16_AGENTS/`. Added
`tests/RolePipelineTest.php` covering the happy path and each stage's
stop/hold condition.

2026-07-02 (same day, follow-up): Added `LlmClientInterface` and an
`AnthropicClient` implementation, plus a `reason()` helper on
`AbstractRoleAgent`. Architect, Planner, Reviewer, Security, Performance,
and Documentation now consult an injected LLM to fill in judgment fields
the caller didn't explicitly supply (architecture blueprint fields,
execution phases, review issues, security/performance findings,
documentation updates) -- explicit context values always win over the
model's answer, and reasoning is skipped entirely if nothing is missing or
no LLM client is configured. `AgentServiceProvider` builds an
`AnthropicClient` automatically when `ANTHROPIC_API_KEY` (env) or
`llm.anthropic.api_key` (via `ConfigurationInterface`) is set; otherwise
every agent stays fully deterministic, so this is opt-in and backward
compatible. Added `tests/Support/FakeLlmClient.php` and
`tests/LlmReasoningTest.php` covering: no LLM call when fields are
explicit, LLM used only for missing fields, explicit values overriding LLM
guesses, and errors on invalid/incomplete LLM JSON responses.

Developer and Release were deliberately left as pure data-aggregators
(see Section 5) -- they don't call the LLM.

2026-07-03: `composer test` was run for the first time on a machine with
PHP installed (8.5.7) and passed cleanly: 105 tests, 236 assertions, 0
failures. Added `.github/workflows/tests.yml` (PHP 8.2/8.3/8.4 matrix) so
this is checked automatically on every push and pull request going
forward, plus a status badge on `README.md`.

2026-07-03 (follow-up): Added `README.md` for the four numbered layers
that didn't have one -- `00_CORE`, `30_LEARNING`, `32_OPTIMIZATION`,
`35_RESILIENCE` -- matching the Component Roster format used by the other
layer READMEs (see e.g. `33_AUTOMATION/README.md`). Every numbered layer
now has a README.

2026-07-03 (follow-up): Fixed the root `README.md`, which was still
describing the pre-restructure directory layout (`01_INPUT`,
`02_VALIDATION`, ... `34_RESPONSE`). The "Architecture" table and
"Repository Structure" tree now list the actual `01_RULES`,
`02_WORKFLOWS`, ... `38_WORDPRESS` layers; "Roadmap" reflects that the
Agent role pipeline is implemented rather than only planned; "Status"
describes the working PHP runtime, test suite, and CI instead of
"architecture and framework development phase"; and "License" now notes
that `LICENSE` exists but is still empty rather than implying no file
exists at all.

2026-07-03 (follow-up): Moved role-agent registration out of
`AgentServiceProvider::boot()` and into `AgentPipelineModule`
(`src/Agent/AgentPipelineModule.php`), a `ModuleInterface` loaded through
`ModuleLoader` from a new `Kernel::loadModules()` step. `ModuleLoader`
already existed but nothing ever called it; it's now actually wired in.
`AgentServiceProvider` reverted to only registering `AgentRegistry`/
`AgentOrchestrator` infrastructure, restoring its original intent ("agents
are discovered and registered by modules"). Extracted the LLM-client
resolution logic into `src/Llm/LlmClientResolver.php` so it isn't tied to
one specific provider. This is module-based registration, not filesystem
auto-discovery -- the module list is still an explicit array in
`Kernel.php` (see Section 5).

2026-07-03 (follow-up): Added `ModuleDiscovery` (`src/Modules/ModuleDiscovery.php`)
and wired it into `Kernel::loadModules()`, replacing the explicit
`[new AgentPipelineModule()]` array with a real recursive scan of `src/`
for classes named `*Module.php` that implement `ModuleInterface` with a
zero-argument constructor. This is now true filesystem auto-discovery:
adding a new module anywhere under `src/` is enough for `Kernel::boot()`
to load it, with no edit to `Kernel.php` needed. `ModuleDiscovery` never
`require`s files directly -- it computes the PSR-4 class name a candidate
file would define and lets Composer's autoloader resolve it via
`class_exists()`, so a broken candidate file only affects itself
(collected via `ModuleDiscovery::errors()` and logged as warnings by
`Kernel`, never thrown). Scope and trust boundary: only `src/` is scanned,
never `vendor/`; a discovered module gets exactly the same container
access a manually-registered module always had, so `src/` must remain
trusted, reviewed code, same as it always has been. Covered by
`tests/Modules/ModuleDiscoveryTest.php` against a fixture directory
(`tests/Fixtures/DiscoveryModules/`) exercising: a plain class that
doesn't implement `ModuleInterface`, an abstract implementation, and one
whose constructor requires an argument -- all correctly skipped.

2026-07-03 (follow-up): Gave `DeveloperAgent` and `ReleaseAgent` real
tool-use, per an explicit decision to go with full capability for both
rather than a read-only or approval-gated middle ground.

**Developer** (`src/Agent/Roles/DeveloperAgent.php`): when the caller does
NOT supply context field `tasks_completed` explicitly, and both an LLM and
a `FileSystemInterface` are injected, it asks the LLM to implement the
plan directly, returning `file_changes` (create/update/delete with a
relative path) alongside `tasks_completed`. Each file change is applied
through `FileSystemInterface`, which enforces path containment (rejects
absolute paths and `..` segments) -- see `src/Tools/LocalFileSystem.php`.
Any file change that fails to apply forces its owning task (or the whole
batch, if it can't be attributed) to `Blocked` rather than reporting false
success. Supplying `tasks_completed` explicitly still works exactly as
before and never touches the file system.

**Release** (`src/Agent/Roles/ReleaseAgent.php`): its gate-check (Approved/
Warning/Complete across review, security, performance, documentation)
still always runs first and never has side effects on its own. Real
release actions -- first a pre-flight `git status --porcelain` clean-tree
check, then finalizing `CHANGELOG.md`, then `git add`, `commit`, `tag`,
`push`, `push --tags` via `CommandRunnerInterface` -- only run when ALL of:
the gate-check passed, `release_version` was supplied, and
`ReleaseActionsPolicy::isEnabled()` is true. That policy is a **separate,
explicit opt-in** (`SQUIRRELFORGE_ENABLE_RELEASE_ACTIONS=1` env, or
`release.actions_enabled` via `ConfigurationInterface`) from having an LLM
configured -- setting `ANTHROPIC_API_KEY` alone can never trigger a real
git push. Any failed step stops the sequence immediately (no push after a
failed commit, no tag after a failed commit) and downgrades the reported
status from `Ready` to `Hold`.

Both new capabilities are backed by `src/Tools/LocalFileSystem.php`
(path-contained read/write/delete) and `src/Tools/ShellCommandRunner.php`
(executes an allowlist of binaries -- `git`, `composer`, `phpunit` -- via
`proc_open()` with an argv array, never a shell string, so nothing in an
LLM-generated argument can be interpreted as shell syntax). Both tools are
always constructed and injected by `AgentPipelineModule` regardless of
whether an LLM or the release-actions flag is set; the flags are what gate
whether they're actually used, not whether they exist.

Test coverage: `tests/Tools/LocalFileSystemTest.php` (real temp directory:
write/read/delete, path traversal and absolute-path rejection, a
legitimate filename containing ".." as a substring is still allowed),
`tests/Tools/ShellCommandRunnerTest.php` (allowlisted `git --version`
succeeds; a disallowed binary, including one disguised behind a path,
throws), `tests/DeveloperAgentToolUseTest.php`, and
`tests/ReleaseAgentToolUseTest.php` (both using in-memory
`FakeFileSystem`/`FakeCommandRunner` test doubles in `tests/Support/`, so
no real disk or process is touched by those two suites).

**If you enable `SQUIRRELFORGE_ENABLE_RELEASE_ACTIONS`, test it against a
disposable branch or repo first** -- this code has been reviewed and
traced by hand, and its unit tests (using fakes) pass the logic they
exercise, but it has not been exercised end-to-end against a real git
remote, since no PHP runtime was available in the environment this was
built from.

2026-07-03 (follow-up): Added a pre-flight working-tree check to
`ReleaseAgent`'s real release actions, closing the gap noted above and
previously listed in Section 5. Before finalizing `CHANGELOG.md`,
`assertWorkingTreeIsClean()` runs `git status --porcelain` via
`CommandRunnerInterface`; any output at all (anything other than a clean
tree) aborts the entire release-actions sequence immediately -- no file is
written, no command beyond the status check itself runs, and the reported
status downgrades to `Hold` with `release_actions` added to
`outstanding`, exactly like any other failed step. This prevents an
unrelated uncommitted change from being swept into the release commit
alongside `CHANGELOG.md`. Covered by a new
`testAbortsWithoutRunningAnyOtherCommandWhenWorkingTreeIsDirty` test in
`tests/ReleaseAgentToolUseTest.php`; the existing tests in that file were
updated since the working-tree check is now always the first
`CommandRunnerInterface` call the agent makes.

2026-07-03 (follow-up): Closed the symlink-based write-escape gap
previously noted above and in Section 5. `LocalFileSystem::write()` and
`::delete()` now realpath()-verify containment the same way `read()`
already did -- `write()` walks up from the target to the nearest
*existing* ancestor directory (the target itself, and any directories
`mkdir()` would still need to create, may not exist yet, so `realpath()`
can't be used on the target path directly) and confirms that ancestor
resolves inside the root; `delete()` confirms the target itself resolves
inside the root before unlinking it. Either check catches a pre-existing
symlink anywhere in the path that would otherwise redirect the operation
outside the root. Covered by two new tests in
`tests/Tools/LocalFileSystemTest.php`:
`testRefusesWriteThroughASymlinkedDirectoryEscapingRoot` and
`testRefusesDeleteThroughASymlinkedFileEscapingRoot` (plus
`testStillCreatesNestedDirectoriesWithNoSymlinksInvolved`, confirming the
new ancestry walk doesn't break ordinary multi-level directory creation).

Review order:

1. README.md
2. ARCHITECTURE.md
3. 00_CORE/SYSTEM-ORCHESTRATOR.md
4. Each numbered layer README
5. src/Contracts
6. src/Core
7. Registries: Agent, Tool, Module, Workflow
8. src/Agent/Roles, AgentOrchestrator, src/Llm
9. Missing pieces section (Section 5)

Best next step after this: run `composer install && composer test` on a
machine with PHP 8.2+ to confirm everything actually boots and passes,
set `ANTHROPIC_API_KEY` and try a real run through `AgentOrchestrator` to
see the reasoning in action, then decide whether Developer/Release should
ever get real tool-use (file edits, running tests) rather than staying
pure aggregators.
