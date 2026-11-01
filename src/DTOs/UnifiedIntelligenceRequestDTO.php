<?php

declare(strict_types=1);

namespace Kaching\Intelligence\DTOs;

final class UnifiedIntelligenceRequestDTO
{
    /**
     * @param string $message User question / prompt
     * @param ActorContextDTO $actor Actor authorization context
     * @param string|null $locale Preferred language / locale ('id' or 'en')
     * @param array<string, mixed> $requestedDomains Explicitly requested domains or empty for auto-route
     * @param array<string, mixed> $extraParameters Contextual overrides (item_name, commodity, horizon_days, etc.)
     */
    public function __construct(
        public readonly string $message,
        public readonly ActorContextDTO $actor,
        public readonly ?string $locale = 'id',
        public readonly array $requestedDomains = [],
        public readonly array $extraParameters = []
    ) {
    }

    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'actor' => $this->actor->toArray(),
            'locale' => $this->locale,
            'requested_domains' => $this->requestedDomains,
            'extra_parameters' => $this->extraParameters,
        ];
    }
}
