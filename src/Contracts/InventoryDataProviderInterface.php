<?php

namespace Kaching\Intelligence\Contracts;

use Kaching\Intelligence\DTOs\InventorySnapshotDTO;
use Kaching\Intelligence\DTOs\StockoutRiskDTO;
use Kaching\Intelligence\DTOs\RestockRecommendationDTO;

/**
 * Kontrak penyedia data inventaris bagi domain analitik dan AI Intelligence.
 *
 * INVARIANT:
 * 1. Interface ini murni READ-ONLY.
 * 2. Dilarang menambahkan mutasi stok, pembuatan Purchase Order, atau transaksi supplier.
 * 3. Seluruh return value berbasis DTO typed murni tanpa keterikatan pada Eloquent Model Core.
 */
interface InventoryDataProviderInterface
{
    /**
     * Mengambil daftar snapshot sisa stok dan ambang batas per item pada outlet.
     *
     * @param int $userId Tenant/Owner ID
     * @param int|null $outletId Outlet ID spesifik
     * @return array<InventorySnapshotDTO>
     */
    public function getInventorySnapshots(int $userId, ?int $outletId = null): array;

    /**
     * Mengevaluasi risiko kehabisan stok (stockout) bahan baku/menu berdasarkan proyeksi konsumsi.
     *
     * @param int $userId Tenant/Owner ID
     * @param int|null $outletId Outlet ID spesifik
     * @param int|null $menuId Menu ID spesifik (null = evaluasi seluruh menu)
     * @return array<StockoutRiskDTO>
     */
    public function getStockoutRisks(int $userId, ?int $outletId = null, ?int $menuId = null): array;

    /**
     * Mengambil rekomendasi bahan baku yang perlu di-restock berdasarkan run-rate dan safety stock.
     * MURNI ADVISORY: Tidak mengeksekusi purchase order atau mutasi stok.
     *
     * @param int $userId Tenant/Owner ID
     * @param int|null $outletId Outlet ID spesifik
     * @param array $options Opsi horizon hari, lead time, dll.
     * @return array<RestockRecommendationDTO>
     */
    public function getRestockRecommendations(int $userId, ?int $outletId = null, array $options = []): array;
}
