<?php

namespace Kaching\Intelligence\Contracts;

use Kaching\Intelligence\DTOs\ActorContextDTO;

interface IntelligenceContextInterface
{
    /**
     * Resolves the immutable Actor Context DTO for the given request/user.
     */
    public function resolveActorContext(int $userId, ?int $outletId, string $role = 'staff', string $planTier = 'basic', string $locale = 'id'): ActorContextDTO;

    /**
     * Resolves and verifies tenant-scoped outlet ID. Returns -1 if unauthorized.
     */
    public function resolveAuthorizedOutletId(int $userId, ?int $requestedOutletId): ?int;
}
