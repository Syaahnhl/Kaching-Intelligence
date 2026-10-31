<?php

namespace Kaching\Intelligence\Contracts;

use Kaching\Intelligence\DTOs\ActorContextDTO;
use Kaching\Intelligence\DTOs\IntelligenceResponseDTO;

interface IntelligenceEngineInterface
{
    /**
     * Process message through Intelligence reasoning, orchestration, and LLM providers.
     */
    public function processMessage(ActorContextDTO $context, string $message, array $history = []): IntelligenceResponseDTO;
}
