<?php

namespace Tests\Unit;

use Modules\Hospitality\Models\ServiceOrderItem;
use PHPUnit\Framework\TestCase;

class ServiceOrderItemSnapshotTest extends TestCase
{
    public function test_it_exposes_snapshot_contracts_without_leaking_storage_columns(): void
    {
        $item = new ServiceOrderItem([
            'inventory_item_snapshot' => [
                'id' => 5,
                'name' => 'Signature Bottle',
                'unit' => 'bottle',
                'current_stock' => '12.000',
                'selling_price' => '9500.00',
            ],
            'inventory_transaction_snapshot' => [
                'id' => 19,
                'item_id' => 5,
                'type' => 'hospitality_service',
                'direction' => 'out',
                'quantity' => '1.000',
                'balance_after' => '11.000',
                'module_source' => 'hospitality',
                'reference_type' => 'hospitality_service_order_item',
                'reference_id' => '99',
            ],
        ]);

        $payload = $item->toArray();

        $this->assertSame('Signature Bottle', $payload['inventory_item']['name']);
        $this->assertSame('hospitality_service', $payload['inventory_transaction']['type']);
        $this->assertArrayNotHasKey('inventory_item_snapshot', $payload);
        $this->assertArrayNotHasKey('inventory_transaction_snapshot', $payload);
    }
}
