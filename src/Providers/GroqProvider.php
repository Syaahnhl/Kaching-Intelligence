<?php

declare(strict_types=1);

namespace Kaching\Intelligence\Providers;

class GroqProvider extends AbstractOpenAICompatibleProvider
{
    public const IDENTIFIER = 'groq';

    public function __construct(
        string $apiKey,
        string $baseUrl = 'https://api.groq.com/openai/v1',
        string $defaultModel = 'llama-3.3-70b-versatile',
        array $customHeaders = []
    ) {
        parent::__construct($apiKey, $baseUrl, $defaultModel, $customHeaders);
    }

    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }
}
