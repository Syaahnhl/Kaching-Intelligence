<?php

declare(strict_types=1);

namespace Kaching\Intelligence\Contracts;

use Kaching\Intelligence\DTOs\BusinessRadarContextDTO;
use Kaching\Intelligence\DTOs\BusinessRadarResultDTO;

/**
 * Interface for synthesizing external radar signals and market trends.
 */
interface BusinessRadarEngineInterface
{
    /**
     * Synthesize radar signals and produce attributed business recommendations.
     *
     * @param BusinessRadarContextDTO $context
     * @return BusinessRadarResultDTO
     */
    public function inspect(BusinessRadarContextDTO $context): BusinessRadarResultDTO;
}
