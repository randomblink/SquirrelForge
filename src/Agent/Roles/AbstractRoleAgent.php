<?php

declare(strict_types=1);

namespace SquirrelForge\Agent\Roles;

use SquirrelForge\Agent\Roles\Support\ContextGuards;
use SquirrelForge\Agent\Support\BootableHealthCheck;
use SquirrelForge\Contracts\AgentInterface;
use SquirrelForge\Contracts\LlmClientInterface;
use SquirrelForge\Llm\Reasoner;
use SquirrelForge\Tools\ToolRegistry;

/**
 * Common plumbing shared by every pipeline role agent described in `16_AGENTS/`.
 *
 * Each concrete role agent only needs to implement `stage()` (its position in the
 * handoff sequence) and `process()` (its role-specific behavior).
 *
 * Role agents are deterministic by default: they package and validate the
 * context handed to them according to the model documented for their role,
 * and never invent decisions, evidence, or test results that were not
 * supplied by the caller. When an `LlmClientInterface` is injected, agents
 * may use `reason()` to ask that model to fill in the specific judgment
 * calls their spec describes -- but only for fields the caller did not
 * already supply explicitly. Explicit input always wins over a model's
 * guess.
 *
 * The `AgentInterface` boot/health boilerplate is shared via
 * `BootableHealthCheck` (also used by `AgentOrchestrator` and
 * `CallbackAgent`, which can't share it through inheritance since all three
 * implement `AgentInterface` directly). The context-reading guards
 * (`requireField`/`requireHistory`) live in `ContextGuards`. Prompt
 * building, JSON parsing, and validation for `reason()` live in
 * `SquirrelForge\Llm\Reasoner`, constructed only when an LLM client is
 * actually injected.
 */
abstract class AbstractRoleAgent implements AgentInterface
{
    use BootableHealthCheck;
    use ContextGuards;

    private readonly ?Reasoner $reasoner;

    public function __construct(
        ?LlmClientInterface $llm = null,
        private readonly string $version = '1.0.0',
        ?ToolRegistry $tools = null
    ) {
        $this->reasoner = $llm !== null ? new Reasoner($llm, $tools) : null;
    }

    /**
     * The pipeline stage key this agent handles, e.g. "architect".
     */
    abstract public function stage(): string;

    abstract public function getName(): string;

    abstract public function getDescription(): string;

    /**
     * Role-specific behavior. Receives the shared pipeline context (including
     * `history` of prior stage results) and returns this stage's result array.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    abstract protected function process(array $context): array;

    /**
     * @return array<string, mixed>
     */
    protected function healthDetails(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'stage' => $this->stage(),
        ];
    }

    public function getId(): string
    {
        return $this->stage();
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function supports(array $context): bool
    {
        return ($context['stage'] ?? null) === $this->stage();
    }

    public function execute(array $context): array
    {
        $result = $this->process($context);
        $result['stage'] = $this->stage();

        return $result;
    }

    public function metadata(): array
    {
        return [
            'stage' => $this->stage(),
            'version' => $this->version,
        ];
    }

    /**
     * Whether an LLM client is available for this agent to reason with.
     */
    protected function isReasoningEnabled(): bool
    {
        return $this->reasoner !== null;
    }

    /**
     * Ask the injected LLM to produce the given fields, expressed as a
     * strict JSON object with exactly those keys. Returns null when no LLM
     * client is configured, so callers can fall back to a deterministic
     * default rather than failing outright.
     *
     * @param array<int, string> $fields Keys the model must return.
     * @param array<string, mixed> $payload Data the model should reason over.
     * @return array<string, mixed>|null
     *
     * @throws \RuntimeException if the LLM is configured but returns a
     *                            response that cannot be parsed as JSON, or
     *                            that is missing a requested key.
     */
    protected function reason(string $instructions, array $fields, array $payload): ?array
    {
        return $this->reasoner?->reason($this->getName(), static::class, $instructions, $fields, $payload);
    }

    /**
     * Every tool call made and its result during the most recent tool-driven
     * `reason()` call (empty when no LLM is configured, or tools were not
     * active for that call).
     *
     * @return array<int, array{name: string, input: array<string, mixed>, result: array<string, mixed>}>
     */
    protected function lastToolCalls(): array
    {
        return $this->reasoner?->lastToolCalls() ?? [];
    }
}
