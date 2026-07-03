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
- `ModuleDiscovery` (`src/Modules/ModuleDiscovery.php`): real filesystem auto-discovery. Recursively scans a directory for `*Module.php` files, derives each candidate's PSR-4 class name, and instantiates every concrete `ModuleInterface` implementation with a zero-argument constructor found there. Never `require`s files directly -- resolves classes via `class_exists()` through Composer's autoloader, and collects (never throws) errors from any one broken candidate so it can't take down boot.
- `tests/Modules/ModuleDiscoveryTest.php` and `tests/Fixtures/DiscoveryModules/` covering: only concrete, constructor-free `ModuleInterface` implementations are discovered; a non-implementing class, an abstract implementation, and a constructor-requiring implementation are all correctly skipped; a nonexistent directory returns no modules; an explicit exclude list is honored.
- Real tool-use for `DeveloperAgent` and `ReleaseAgent`. `FileSystemInterface`/`LocalFileSystem` (`src/Contracts/FileSystemInterface.php`, `src/Tools/LocalFileSystem.php`): path-contained read/write/delete rooted at the project directory; rejects absolute paths and `..` segments before touching disk. `CommandRunnerInterface`/`ShellCommandRunner` (`src/Contracts/CommandRunnerInterface.php`, `src/Tools/ShellCommandRunner.php`): runs an allowlisted binary (`git`, `composer`, `phpunit`) via `proc_open()` with an argv array, never a shell string, so nothing in an argument can be interpreted as shell syntax.
- `DeveloperAgent` now writes/deletes files directly (per the LLM's proposed `file_changes`) when `tasks_completed` isn't supplied explicitly and both an LLM and `FileSystemInterface` are injected. A failed write forces its task to `Blocked` instead of reporting false success. Supplying `tasks_completed` explicitly is unchanged and never touches disk.
- `ReleaseAgent` now finalizes `CHANGELOG.md` and runs `git add`/`commit`/`tag`/`push`/`push --tags` when its gate-check passes, `release_version` is supplied, and `ReleaseActionsPolicy::isEnabled()` is true. `src/Tools/ReleaseActionsPolicy.php` gates this behind `SQUIRRELFORGE_ENABLE_RELEASE_ACTIONS` (env) or `release.actions_enabled` (Configuration) -- a separate opt-in from the LLM API key, so setting `ANTHROPIC_API_KEY` alone can never trigger a real push. Any failed step stops the sequence and downgrades status to `Hold`.
- `tests/Support/FakeFileSystem.php` and `tests/Support/FakeCommandRunner.php` test doubles, plus `tests/DeveloperAgentToolUseTest.php` and `tests/ReleaseAgentToolUseTest.php` covering the new tool-use paths without touching real disk or processes, and `tests/Tools/LocalFileSystemTest.php`/`tests/Tools/ShellCommandRunnerTest.php` covering the tools themselves against a real temp directory and real (but read-only/allowlist-rejecting) process execution.
- `ReleaseAgent` pre-flight working-tree check: before finalizing `CHANGELOG.md` or running any git command, it now runs `git status --porcelain` via `CommandRunnerInterface` and aborts the entire release-actions sequence (downgrading status to `Hold`) if the tree isn't clean, so unrelated uncommitted changes can't be swept into the release commit. Covered by a new `testAbortsWithoutRunningAnyOtherCommandWhenWorkingTreeIsDirty` test in `tests/ReleaseAgentToolUseTest.php`.
- `LocalFileSystem::write()`/`::delete()` now `realpath()`-verify containment (of the nearest existing ancestor directory for `write()`, of the target itself for `delete()`), closing a narrow symlink-based write/delete escape that the textual `..`-segment check alone didn't catch. Covered by two new tests in `tests/Tools/LocalFileSystemTest.php`.

### Fixed

- `ARCHITECTURE.md`: closed an unclosed code fence that was suppressing all Markdown formatting past "Primary Flow", and corrected stale directory references (`31_OBSERVABILITY` -> `27_OBSERVABILITY`, `27_LEARNING` -> `30_LEARNING`).
- `11_OVERVIEW/PROJECT-INVENTORY.md`: refreshed to match the actual post-restructure directory tree and runtime source files; previous "Missing Pieces" list was stale (the listed items already existed).
- `README.md`: "Architecture" table and "Repository Structure" tree were still describing the pre-restructure layout (`01_INPUT`, `02_VALIDATION`, ... `34_RESPONSE`); now list the actual `01_RULES`, `02_WORKFLOWS`, ... `38_WORDPRESS` layers. "Roadmap" and "Status" no longer describe the project as pre-implementation now that `src/`, `tests/`, and CI exist. "License" now notes that `LICENSE` exists but is empty, rather than implying no file exists.

### Changed

- `AgentServiceProvider` now only registers the `AgentRegistry`/`AgentOrchestrator` infrastructure; it no longer constructs role agents itself. Role-agent registration (and the `AnthropicClient` built from `ANTHROPIC_API_KEY`/`llm.anthropic.api_key`, with neither set every agent staying fully deterministic) now happens in `AgentPipelineModule`.
- `Kernel::boot()` now resolves `ModuleLoader` after all providers have registered and booted, and loads whatever `ModuleDiscovery` finds under `src/` through it -- `ModuleLoader` existed before but was never actually invoked, and the module list is no longer a hardcoded array in `Kernel.php`. Any discovery errors are logged as warnings via `LoggerInterface` rather than failing boot.
- `AgentPipelineModule` now always constructs and injects a real `LocalFileSystem`/`ShellCommandRunner` (rooted/working-directory at the project root) into `DeveloperAgent`/`ReleaseAgent`, regardless of whether an LLM or the release-actions flag is configured -- those flags gate *use*, not *presence*, of the tools.
