<?php

declare(strict_types=1);

namespace Kaching\Intelligence\DTOs;

final class LLMResponseDTO
{
    /**
     * @param array<int, array{id: string, type: string, function: array{name: string, arguments: string|array}}> $toolCalls
     * @param array<string, mixed> $usage
     * @param array<string, mixed> $rawResponse
     */
    public function __construct(
        public readonly bool $success,
        public readonly string $providerIdentifier,
        public readonly string $model,
        public readonly ?string $content = null,
        public readonly array $toolCalls = [],
        public readonly ?string $finishReason = null,
        public readonly array $usage = [],
        public readonly ?LLMProviderErrorDTO $error = null,
        public readonly array $rawResponse = []
    ) {
    }

    public function hasToolCalls(): bool
    {
        return count($this->toolCalls) > 0;
    }

    public function toArray(): array
    {
        return [
            'success'         => $this->success,
            'provider'        => $this->providerIdentifier,
            'model'           => $this->model,
            'content'         => $this->content,
            'tool_calls'      => $this->toolCalls,
            'finish_reason'   => $this->finishReason,
            'usage'           => $this->usage,
            'error'           => $this->error?->toArray(),
        ];
    }
}
