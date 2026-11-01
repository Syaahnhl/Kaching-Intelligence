<?php

namespace Kaching\Intelligence\Contracts;

use Kaching\Intelligence\DTOs\SafetyStockContextDTO;
use Kaching\Intelligence\DTOs\SafetyStockRecommendationDTO;

interface SafetyStockOptimizerInterface
{
    /**
     * Compute safety stock, reorder point, and recommendation.
     * Pure recommender: NEVER mutates inventory or database.
     *
     * @param SafetyStockContextDTO $context
     * @return SafetyStockRecommendationDTO
     */
    public function optimize(SafetyStockContextDTO $context): SafetyStockRecommendationDTO;
}
