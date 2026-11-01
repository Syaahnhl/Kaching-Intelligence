<?php

declare(strict_types=1);

namespace Kaching\Intelligence\Contracts;

use Kaching\Intelligence\DTOs\LLMRequestDTO;
use Kaching\Intelligence\DTOs\LLMResponseDTO;

interface LLMProviderInterface
{
    /**
     * Get unique provider identifier (e.g., 'groq', '9router', 'openai_compatible').
     */
    public function getIdentifier(): string;

    /**
     * Check if the provider has necessary configuration / credentials supplied.
     */
    public function isAvailable(): bool;

    /**
     * Execute chat completion request against the upstream provider.
     *
     * @throws \Kaching\Intelligence\Exceptions\IntelligenceException
     */
    public function sendChatRequest(LLMRequestDTO $request): LLMResponseDTO;
}
