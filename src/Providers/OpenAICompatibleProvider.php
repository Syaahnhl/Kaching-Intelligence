<?php

declare(strict_types=1);

namespace Kaching\Intelligence\Providers;

class OpenAICompatibleProvider extends AbstractOpenAICompatibleProvider
{
    public const IDENTIFIER = 'openai_compatible';

    public function __construct(
        string $apiKey,
        string $baseUrl,
        string $defaultModel,
        protected readonly string $customIdentifier = self::IDENTIFIER,
        array $customHeaders = []
    ) {
        parent::__construct($apiKey, $baseUrl, $defaultModel, $customHeaders);
    }

    public function getIdentifier(): string
    {
        return $this->customIdentifier;
    }
}
