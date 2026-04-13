<?php

namespace Modules\Inventory\Support;

final class InventoryWorkflowAliasCatalog
{
    private const MAP = [
        'purchase-requests' => 'purchase_request',
        'purchase-orders' => 'purchase_order',
        'purchase-order' => 'purchase_order',
        'grns' => 'goods_receiving_note',
        'production-orders' => 'production_order',
        'store-vouchers' => 'store_voucher',
        'finished-goods-transfers' => 'finished_goods_transfer',
        'sales-orders' => 'sales_order',
        'sales-order' => 'sales_order',
        'sales-summaries' => 'sales_summary',
        'dispatches' => 'dispatch',
        'dispatch' => 'dispatch',
        'delivery-notes' => 'delivery_note',
        'delivery-note' => 'delivery_note',
        'returns' => 'goods_return_note',
        'goods-return' => 'goods_return_note',
        'goods-returns' => 'goods_return_note',
        'waste-vouchers' => 'waste_voucher',
    ];

    public static function all(): array
    {
        return self::MAP;
    }

    public static function documentTypeFor(string $resource): ?string
    {
        return self::MAP[$resource] ?? null;
    }

    public static function routeRegex(): string
    {
        $keys = array_keys(self::MAP);
        $parts = array_map(
            static fn (string $key): string => preg_quote($key, '#'),
            $keys
        );

        return implode('|', $parts);
    }
}
