<?php

declare(strict_types=1);

namespace SquirrelForge\Agent\Roles;

use DateTimeImmutable;
use RuntimeException;
use SquirrelForge\Contracts\AgentInterface;
use SquirrelForge\Contracts\LlmClientInterface;

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
 */
abstract class AbstractRoleAgent implements AgentInterface
{
    private bool $booted = false;

    public function __construct(
        private readonly ?LlmClientInterface $llm = null,
        private readonly string $version = '1.0.0'
    ) {
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

    public function boot(): void
    {
        $this->booted = true;
    }

    public function isHealthy(): bool
    {
        return $this->booted;
    }

    public function health(): array
    {
        return [
            'status' => $this->booted ? 'healthy' : 'unhealthy',
            'component' => static::class,
            'timestamp' => (new DateTimeImmutable())->format(DATE_ATOM),
            'details' => [
                'id' => $this->getId(),
                'name' => $this->getName(),
                'stage' => $this->stage(),
            ],
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
     * Fetch a required field from the context, throwing when it is missing so
     * that agents never silently fabricate data they were not given.
     *
     * @param array<string, mixed> $context
     */
    protected function requireField(array $context, string $key): mixed
    {
        if (!array_key_exists($key, $context) || $context[$key] === null || $context[$key] === '') {
            throw new \InvalidArgumentException(
                sprintf('%s requires context field "%s".', static::class, $key)
            );
        }

        return $context[$key];
    }

    /**
     * Fetch the result recorded for a prior stage, throwing if that stage has
     * not run yet (its handoff has not happened).
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    protected function requireHistory(array $context, string $stage): array
    {
        $history = $context['history'] ?? [];

        if (!isset($history[$stage]) || !is_array($history[$stage])) {
            throw new \RuntimeException(
                sprintf('%s requires a completed handoff from stage "%s".', static::class, $stage)
            );
        }

        return $history[$stage];
    }

    /**
     * Whether an LLM client is available for this agent to reason with.
     */
    protected function isReasoningEnabled(): bool
    {
        return $this->llm !== null;
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
     * @throws RuntimeException if the LLM is configured but returns a
     *                           response that cannot be parsed as JSON, or
     *                           that is missing a requested key.
     */
    protected function reason(string $instructions, array $fields, array $payload): ?array
    {
        if ($this->llm === null) {
            return null;
        }

        $system = sprintf(
            "You are the %s in the SquirrelForge agent pipeline.\n%s\n\n".
            "Respond with ONLY a single JSON object containing exactly these keys: %s. ".
            "Do not include any prose, explanation, or Markdown code fences around the JSON.",
            $this->getName(),
            $instructions,
            implode(', ', $fields)
        );

        $userPrompt = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $raw = trim($this->llm->complete($system, $userPrompt));
        $raw = $this->stripCodeFence($raw);

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            throw new RuntimeException(sprintf(
                '%s: expected a JSON object from the LLM but got: %s',
                static::class,
                substr($raw, 0, 200)
            ));
        }

        foreach ($fields as $field) {
            if (!array_key_exists($field, $decoded)) {
                throw new RuntimeException(sprintf(
                    '%s: LLM response is missing required field "%s".',
                    static::class,
                    $field
                ));
            }
        }

        return $decoded;
    }

    /**
     * Models sometimes wrap JSON in ```json ... ``` fences despite being
     * told not to. Strip that defensively rather than failing the parse.
     */
    private function stripCodeFence(string $raw): string
    {
        if (str_starts_with($raw, '```')) {
            $raw = preg_replace('/^```[a-zA-Z]*\n|\n```$/', '', $raw) ?? $raw;
        }

        return trim($raw);
    }
}
