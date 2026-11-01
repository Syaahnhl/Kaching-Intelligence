<?php

namespace Kaching\Intelligence\DTOs;

readonly class PromptResultDTO
{
    /**
     * @param array<int, array<string, mixed>> $allowedTools
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $systemPrompt,
        public string $locale,
        public string $personaContext,
        public bool $hasToolAccess = false,
        public array $allowedTools = [],
        public int $estimatedTokenCount = 0,
        public array $metadata = []
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'system_prompt' => $this->systemPrompt,
            'locale' => $this->locale,
            'persona_context' => $this->personaContext,
            'has_tool_access' => $this->hasToolAccess,
            'allowed_tools_count' => count($this->allowedTools),
            'estimated_token_count' => $this->estimatedTokenCount,
            'metadata' => $this->metadata,
        ];
    }
}
