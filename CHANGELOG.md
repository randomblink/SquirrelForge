# Changelog

Version: 1.0.0
Status: Draft
Owner: Release Maintainers
Depends On: `23_GOVERNANCE/VERSIONING.md`
Used By: Contributors and releases
Last Updated: 2026-07-03

All notable changes to SquirrelForge are recorded here.

## Unreleased

### Added

- Layered system architecture, lifecycle, glossary, configuration, interfaces, governance, execution, and testing documentation.
- README navigation for every active framework layer.
- Standard document metadata.
- Eight pipeline role agents (`src/Agent/Roles/`: Architect, Planner, Developer, Reviewer, Security, Performance, Documentation, Release) implementing the roles specified in `16_AGENTS/`.
- `AgentOrchestrator` (`src/Agent/AgentOrchestrator.php`) coordinating the Architect -> Planner -> Developer -> Reviewer -> Security -> Performance -> Documentation -> Release handoff sequence.
- `tests/RolePipelineTest.php` covering the pipeline's happy path and its stop/hold conditions.
- `LlmClientInterface` (`src/Contracts/LlmClientInterface.php`) and `AnthropicClient` (`src/Llm/AnthropicClient.php`), a cURL-based Anthropic Messages API client with no new Composer dependency.
- `AbstractRoleAgent::reason()`: lets a role agent ask an injected LLM to fill in judgment fields the caller didn't explicitly supply, with explicit context values always taking precedence over the model's answer.
- Real LLM reasoning wired into Architect, Planner, Reviewer, Security, Performance, and Documentation agents (architecture blueprint fields, execution phases, review issues, security/performance findings, documentation updates respectively).
- `tests/Support/FakeLlmClient.php` and `tests/LlmReasoningTest.php` covering: no LLM call when fields are explicit, LLM used only for missing fields, explicit values overriding LLM guesses, and errors on invalid/incomplete LLM JSON responses.
- `.github/workflows/tests.yml`: runs `composer test` on every push/PR to `main` across a PHP 8.2/8.3/8.4 matrix.
- Tests status badge on `README.md`.
- `README.md` for `00_CORE`, `30_LEARNING`, `32_OPTIMIZATION`, and `35_RESILIENCE` -- the four numbered layers that didn't have one. Every numbered layer now has a README.
- `AgentPipelineModule` (`src/Agent/AgentPipelineModule.php`): a `ModuleInterface` that registers the eight role agents and the orchestrator into `AgentRegistry`, loaded through `ModuleLoader` from a new `Kernel::loadModules()` step instead of being hardcoded in a service provider.
- `src/Llm/LlmClientResolver.php`: extracted the `ANTHROPIC_API_KEY`/`ConfigurationInterface` resolution logic out of `AgentServiceProvider` so any module can resolve an LLM client the same way.

### Fixed

- `ARCHITECTURE.md`: closed an unclosed code fence that was suppressing all Markdown formatting past "Primary Flow", and corrected stale directory references (`31_OBSERVABILITY` -> `27_OBSERVABILITY`, `27_LEARNING` -> `30_LEARNING`).
- `11_OVERVIEW/PROJECT-INVENTORY.md`: refreshed to match the actual post-restructure directory tree and runtime source files; previous "Missing Pieces" list was stale (the listed items already existed).
- `README.md`: "Architecture" table and "Repository Structure" tree were still describing the pre-restructure layout (`01_INPUT`, `02_VALIDATION`, ... `34_RESPONSE`); now list the actual `01_RULES`, `02_WORKFLOWS`, ... `38_WORDPRESS` layers. "Roadmap" and "Status" no longer describe the project as pre-implementation now that `src/`, `tests/`, and CI exist. "License" now notes that `LICENSE` exists but is empty, rather than implying no file exists.

### Changed

- `AgentServiceProvider` now only registers the `AgentRegistry`/`AgentOrchestrator` infrastructure; it no longer constructs role agents itself. Role-agent registration (and the `AnthropicClient` built from `ANTHROPIC_API_KEY`/`llm.anthropic.api_key`, with neither set every agent staying fully deterministic) now happens in `AgentPipelineModule`.
- `Kernel::boot()` now resolves `ModuleLoader` after all providers have registered and booted, and loads `AgentPipelineModule` through it -- `ModuleLoader` existed before but was never actually invoked.
