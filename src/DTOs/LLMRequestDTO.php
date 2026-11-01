<?php

declare(strict_types=1);

namespace Kaching\Intelligence\DTOs;

final class LLMRequestDTO
{
    /**
     * @param array<int, array{role: string, content: string|null, tool_calls?: array}> $messages
     * @param array<int, array<string, mixed>> $tools
     * @param array<string, mixed> $customOptions
     */
    public function __construct(
        public readonly array $messages,
        public readonly string $model,
        public readonly float $temperature = 0.4,
        public readonly int $maxTokens = 1024,
        public readonly array $tools = [],
        public readonly string|array $toolChoice = 'auto',
        public readonly int $timeoutSeconds = 20,
        public readonly int $connectTimeoutSeconds = 5,
        public readonly array $customOptions = []
    ) {
    }

    /**
     * Convert to standard OpenAI-compatible payload array.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        $payload = [
            'model'       => $this->model,
            'messages'    => $this->messages,
            'temperature' => $this->temperature,
            'max_tokens'  => $this->maxTokens,
            'stream'      => false,
        ];

        if (count($this->tools) > 0) {
            $payload['tools'] = $this->tools;
            $payload['tool_choice'] = $this->toolChoice;
        }

        foreach ($this->customOptions as $key => $val) {
            if (!isset($payload[$key])) {
                $payload[$key] = $val;
            }
        }

        return $payload;
    }
}
