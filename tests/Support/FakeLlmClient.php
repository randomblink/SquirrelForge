<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Support;

use Closure;
use SquirrelForge\Contracts\LlmClientInterface;

/**
 * A test double for LlmClientInterface. Configure it with either a fixed
 * response string or a callable(systemPrompt, userPrompt): string, and
 * inspect `$calls` afterward to assert whether (and how) it was invoked.
 */
final class FakeLlmClient implements LlmClientInterface
{
    /**
     * @var array<int, array{system: string, prompt: string}>
     */
    public array $calls = [];

    public function __construct(
        private readonly string|Closure $response
    ) {
    }

    public function complete(string $systemPrompt, string $userPrompt): string
    {
        $this->calls[] = ['system' => $systemPrompt, 'prompt' => $userPrompt];

        if ($this->response instanceof Closure) {
            return ($this->response)($systemPrompt, $userPrompt);
        }

        return $this->response;
    }
}
