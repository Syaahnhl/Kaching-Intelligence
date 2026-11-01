<?php

declare(strict_types=1);

namespace Kaching\Intelligence\Providers;

class NineRouterProvider extends AbstractOpenAICompatibleProvider
{
    public const IDENTIFIER = '9router';

    public function __construct(
        string $apiKey,
        string $baseUrl = 'https://api.9router.com/v1',
        string $defaultModel = 'kachingcopilot',
        array $customHeaders = []
    ) {
        parent::__construct($apiKey, $baseUrl, $defaultModel, $customHeaders);
    }

    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }
}
