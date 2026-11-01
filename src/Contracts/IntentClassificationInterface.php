<?php

declare(strict_types=1);

namespace Kaching\Intelligence\Contracts;

use Kaching\Intelligence\DTOs\ActorContextDTO;

interface IntentClassificationInterface
{
    /**
     * Parse and classify user input message into intent name, domains, and extracted entities.
     *
     * @param string $message
     * @param ActorContextDTO $actor
     * @return array{intent: string, domains: array<int, string>, entities: array<string, mixed>, confidence: float}
     */
    public function classify(string $message, ActorContextDTO $actor): array;
}
