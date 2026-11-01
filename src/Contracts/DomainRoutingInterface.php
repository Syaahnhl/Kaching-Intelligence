<?php

declare(strict_types=1);

namespace Kaching\Intelligence\Contracts;

use Kaching\Intelligence\DTOs\ActorContextDTO;

interface DomainRoutingInterface
{
    /**
     * Resolve and filter active domains based on classified intent, user actor role, and permissions.
     *
     * @param array<int, string> $targetDomains
     * @param ActorContextDTO $actor
     * @return array<int, string> Permitted and active domains
     */
    public function resolveRoutes(array $targetDomains, ActorContextDTO $actor): array;
}
