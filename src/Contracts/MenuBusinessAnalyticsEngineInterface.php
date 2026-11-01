<?php

declare(strict_types=1);

namespace Kaching\Intelligence\Contracts;

use Kaching\Intelligence\DTOs\MenuBusinessAnalyticsContextDTO;
use Kaching\Intelligence\DTOs\MenuBusinessAnalyticsResultDTO;

/**
 * Interface for menu business intelligence and portfolio quadrant mapping.
 * Pure mathematical classification and SAW scoring — zero DB access.
 */
interface MenuBusinessAnalyticsEngineInterface
{
    /**
     * Analyze menu portfolio using BCG matrix classification and SAW multi-criteria ranking.
     *
     * @param MenuBusinessAnalyticsContextDTO $context
     * @return MenuBusinessAnalyticsResultDTO
     */
    public function analyze(MenuBusinessAnalyticsContextDTO $context): MenuBusinessAnalyticsResultDTO;
}
