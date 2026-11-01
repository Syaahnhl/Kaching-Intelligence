<?php

declare(strict_types=1);

namespace Kaching\Intelligence\Orchestration;

use Kaching\Intelligence\Contracts\DomainRoutingInterface;
use Kaching\Intelligence\DTOs\ActorContextDTO;

class PolicyDomainRouter implements DomainRoutingInterface
{
    /**
     * Map of roles to allowed domain sets.
     * Owner has full access.
     * Cashier / Staff is limited to basic operational inquiry.
     */
    protected array $rolePermissions = [
        'owner' => ['raw_material', 'demand_forecast', 'safety_stock', 'cash_flow', 'menu_analytics', 'prompt'],
        'manager' => ['raw_material', 'demand_forecast', 'safety_stock', 'menu_analytics', 'prompt'],
        'cashier' => ['prompt', 'demand_forecast'],
        'staff' => ['prompt'],
    ];

    /**
     * Resolve and filter active domains based on classified intent, user actor role, and permissions.
     *
     * @param array<int, string> $targetDomains
     * @param ActorContextDTO $actor
     * @return array<int, string> Permitted and active domains
     */
    public function resolveRoutes(array $targetDomains, ActorContextDTO $actor): array
    {
        $role = strtolower($actor->role);
        $allowed = $this->rolePermissions[$role] ?? $this->rolePermissions['owner'];

        $filtered = array_values(array_filter(
            $targetDomains,
            fn(string $domain) => in_array($domain, $allowed, true)
        ));

        // If all requested domains were filtered out by role, fallback to prompt
        if (empty($filtered)) {
            return ['prompt'];
        }

        return $filtered;
    }
}
