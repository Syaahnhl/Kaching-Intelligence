<?php

namespace Kaching\Intelligence\Tests\Contract;

use PHPUnit\Framework\TestCase;
use Kaching\Intelligence\Tests\Fakes\FakeInventoryDataProvider;
use Kaching\Intelligence\DTOs\InventorySnapshotDTO;

class InventoryDataProviderContractTest extends TestCase
{
    public function test_inventory_data_provider_respects_tenant_isolation(): void
    {
        $provider = new FakeInventoryDataProvider();

        // Seed tenant 1
        $provider->seed(1, 10, [
            ['id' => 1, 'name' => 'Kopi Susu', 'stock' => 20],
            ['id' => 2, 'name' => 'Espresso', 'stock' => 15],
        ]);

        // Seed tenant 2
        $provider->seed(2, 20, [
            ['id' => 3, 'name' => 'Burger Beef', 'stock' => 5],
        ]);

        // Query for tenant 1
        $snapshots1 = $provider->getInventorySnapshots(1, 10);
        $this->assertIsArray($snapshots1);
        $this->assertCount(2, $snapshots1);
        $this->assertInstanceOf(InventorySnapshotDTO::class, $snapshots1[0]);
        $this->assertSame('Kopi Susu', $snapshots1[0]->itemName);

        // Query for tenant 2
        $snapshots2 = $provider->getInventorySnapshots(2, 20);
        $this->assertCount(1, $snapshots2);
        $this->assertSame('Burger Beef', $snapshots2[0]->itemName);

        // Verify tenant 1 cannot see tenant 2 items
        foreach ($snapshots1 as $snap) {
            $this->assertNotSame('Burger Beef', $snap->itemName);
        }
    }
}
