<?php

namespace Kaching\Intelligence\DTOs;

readonly class PromptContextDTO
{
    /**
     * @param array<int, array{role: string, content: string}> $conversationHistory
     * @param array<string, mixed> $businessContext
     * @param array<string, mixed> $options
     */
    public function __construct(
        public ?ActorContextDTO $actor = null,
        public string $locale = 'id',
        public string $context = 'PUBLIC_MARKETING',
        public string $tier = 'basic',
        public array $conversationHistory = [],
        public array $businessContext = [],
        public array $options = []
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'actor' => $this->actor?->toArray(),
            'locale' => $this->locale,
            'context' => $this->context,
            'tier' => $this->tier,
            'conversation_history_count' => count($this->conversationHistory),
            'business_context' => $this->businessContext,
            'options' => $this->options,
        ];
    }
}
