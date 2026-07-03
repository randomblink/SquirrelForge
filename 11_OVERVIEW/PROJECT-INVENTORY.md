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
| src/Llm/AnthropicClient.php | Present | Added 2026-07-02; cURL-based Anthropic Messages API implementation. |
| src/Llm/LlmClientResolver.php | Present | Added 2026-07-03; extracted from `AgentServiceProvider` so any module can resolve an LLM client the same way. |
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
| src/Agent/Roles/DeveloperAgent.php | Present | Added 2026-07-02. |
| src/Agent/Roles/ReviewerAgent.php | Present | Added 2026-07-02. |
| src/Agent/Roles/SecurityAgent.php | Present | Added 2026-07-02. |
| src/Agent/Roles/PerformanceAgent.php | Present | Added 2026-07-02. |
| src/Agent/Roles/DocumentationAgent.php | Present | Added 2026-07-02. |
| src/Agent/Roles/ReleaseAgent.php | Present | Added 2026-07-02. |
| src/Tools/ToolRegistry.php | Present | |
| src/Tools/ToolServiceProvider.php | Present | |
| src/Modules/ModuleInterface.php | Present | |
| src/Modules/ModuleLoader.php | Present | Now actually invoked, by `Kernel::loadModules()` (2026-07-03); previously nothing called it. |
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
| True filesystem module auto-discovery | Low | As of 2026-07-03, role-agent registration goes through `AgentPipelineModule` + `ModuleLoader` (see `Kernel::loadModules()`) instead of being hardcoded in a service provider's `boot()`. The module list passed to `ModuleLoader::load()` is still an explicit array in `Kernel.php`, though -- nothing scans a directory for modules yet. Revisit if/when third-party or plugin-style modules need to be discovered without editing `Kernel.php`. |
| Developer/Release agents still pure data-aggregators | Medium | Architect, Planner, Reviewer, Security, Performance, and Documentation now call an injected LLM to fill in judgment fields the caller didn't supply (see `src/Agent/Roles/AbstractRoleAgent::reason()`). Developer and Release intentionally were not given this: Developer would otherwise mean an LLM autonomously writing/editing project files with no tool-use or review loop, and Release is meant to be a pure gate-check. Revisit if/when real tool-use (file edits, test execution) is wired in. |
| Only Anthropic supported | Low | `src/Llm/AnthropicClient.php` is the only `LlmClientInterface` implementation. Add another implementation (e.g. OpenAI) if multi-provider support is ever needed; agents only depend on the interface. |

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
