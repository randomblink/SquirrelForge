<?php

declare(strict_types=1);

namespace SquirrelForge\Agent\Roles;

use DateTimeImmutable;
use SquirrelForge\Contracts\AgentInterface;

/**
 * Common plumbing shared by every pipeline role agent described in `16_AGENTS/`.
 *
 * Each concrete role agent only needs to implement `stage()` (its position in the
 * handoff sequence) and `process()` (its role-specific behavior). Role agents are
 * deliberately deterministic: they package and validate the context handed to them
 * according to the model documented for their role, they do not invent decisions,
 * evidence, or test results that were not supplied by the caller.
 */
abstract class AbstractRoleAgent implements AgentInterface
{
    private bool $booted = false;

    public function __construct(
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
}
