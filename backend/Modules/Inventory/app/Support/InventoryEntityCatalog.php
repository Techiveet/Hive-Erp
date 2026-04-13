<?php

namespace Modules\Inventory\Support;

final class InventoryEntityCatalog
{
    private const MAP = [
        'goods' => 'goods',
        'customers' => 'customers',
        'recipients' => 'recipients',
        'warehouses' => 'warehouses',
        'shelves' => 'shelves',
        'shelf-boxes' => 'shelf_boxes',
        'vehicles' => 'vehicles',
        'routes' => 'routes',
        'assets' => 'assets',
        'boms' => 'boms',
        'product-batches' => 'product_batches',
        'qa-tests' => 'qa_tests',
    ];

    public static function all(): array
    {
        return self::MAP;
    }

    public static function entityTypeFor(string $resource): ?string
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
