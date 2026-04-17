<?php

namespace Modules\Inventory\Contracts;

interface InventoryIntegrationGateway
{
    /**
     * @param  array<int, int|string|null>  $inventoryItemIds
     * @return array<int, array<string, mixed>>
     */
    public function getItemSnapshots(array $inventoryItemIds): array;

    /**
     * @param  array<int, int|string|null>  $inventoryTransactionIds
     * @return array<int, array<string, mixed>>
     */
    public function getTransactionSnapshots(array $inventoryTransactionIds): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function consume(int $inventoryItemId, float $quantity, array $payload = []): array;
}
