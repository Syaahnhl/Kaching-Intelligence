<?php

declare(strict_types=1);

namespace Kaching\Intelligence\Contracts;

interface LLMProviderRegistryInterface
{
    /**
     * Register a provider instance.
     */
    public function registerProvider(LLMProviderInterface $provider): void;

    /**
     * Get a provider by identifier.
     */
    public function getProvider(string $identifier): ?LLMProviderInterface;

    /**
     * Check if a provider exists in the registry.
     */
    public function hasProvider(string $identifier): bool;

    /**
     * Get all registered providers.
     *
     * @return array<string, LLMProviderInterface>
     */
    public function getAllProviders(): array;
}
