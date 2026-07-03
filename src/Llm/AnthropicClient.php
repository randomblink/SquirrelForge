<?php

declare(strict_types=1);

namespace SquirrelForge\Llm;

use RuntimeException;
use SquirrelForge\Contracts\LlmClientInterface;

/**
 * Calls the Anthropic Messages API (https://api.anthropic.com/v1/messages)
 * using PHP's cURL extension directly, so no additional Composer HTTP
 * client dependency is required.
 */
final class AnthropicClient implements LlmClientInterface
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'claude-sonnet-5',
        private readonly int $maxTokens = 1024,
        private readonly int $timeoutSeconds = 30
    ) {
        if (trim($this->apiKey) === '') {
            throw new RuntimeException('AnthropicClient requires a non-empty API key.');
        }
    }

    public function complete(string $systemPrompt, string $userPrompt): string
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('AnthropicClient requires the PHP cURL extension.');
        }

        $payload = json_encode([
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ], JSON_THROW_ON_ERROR);

        $curl = curl_init(self::API_URL);

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_HTTPHEADER => [
                'content-type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: ' . self::API_VERSION,
            ],
            CURLOPT_POSTFIELDS => $payload,
        ]);

        $response = curl_exec($curl);
        $errorNumber = curl_errno($curl);
        $errorMessage = curl_error($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($errorNumber !== 0 || $response === false) {
            throw new RuntimeException(sprintf(
                'AnthropicClient request failed: %s',
                $errorMessage !== '' ? $errorMessage : 'unknown cURL error'
            ));
        }

        $decoded = json_decode($response, true);

        if ($statusCode < 200 || $statusCode >= 300) {
            $apiMessage = is_array($decoded) ? ($decoded['error']['message'] ?? $response) : $response;

            throw new RuntimeException(sprintf(
                'AnthropicClient received HTTP %d: %s',
                $statusCode,
                $apiMessage
            ));
        }

        if (!is_array($decoded) || !isset($decoded['content']) || !is_array($decoded['content'])) {
            throw new RuntimeException('AnthropicClient received an unexpected response shape.');
        }

        $text = '';

        foreach ($decoded['content'] as $block) {
            if (($block['type'] ?? null) === 'text') {
                $text .= (string) ($block['text'] ?? '');
            }
        }

        return $text;
    }
}
