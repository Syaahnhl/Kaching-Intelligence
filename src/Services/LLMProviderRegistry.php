<?php

declare(strict_types=1);

namespace Kaching\Intelligence\Services;

use Kaching\Intelligence\Contracts\LLMProviderInterface;
use Kaching\Intelligence\Contracts\LLMProviderRegistryInterface;

class LLMProviderRegistry implements LLMProviderRegistryInterface
{
    /**
     * @var array<string, LLMProviderInterface>
     */
    protected array $providers = [];

    /**
     * @param array<int|string, LLMProviderInterface> $initialProviders
     */
    public function __construct(array $initialProviders = [])
    {
        foreach ($initialProviders as $provider) {
            if ($provider instanceof LLMProviderInterface) {
                $this->registerProvider($provider);
            }
        }
    }

    public function registerProvider(LLMProviderInterface $provider): void
    {
        $this->providers[$provider->getIdentifier()] = $provider;
    }

    public function getProvider(string $identifier): ?LLMProviderInterface
    {
        return $this->providers[$identifier] ?? null;
    }

    public function hasProvider(string $identifier): bool
    {
        return isset($this->providers[$identifier]);
    }

    public function getAllProviders(): array
    {
        return $this->providers;
    }
}
