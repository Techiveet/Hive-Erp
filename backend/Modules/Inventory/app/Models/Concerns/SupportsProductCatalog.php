<?php

namespace Modules\Inventory\Models\Concerns;

use Modules\Inventory\Models\Product;
use Modules\Inventory\Support\InventoryTenantContext;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Str;

trait SupportsProductCatalog
{
    public static function bootSupportsProductCatalog(): void
    {
        static::saved(function ($model) {
            $model->syncWithCatalog();
        });

        static::deleted(function ($model) {
            $model->removeFromCatalog();
        });
    }

    /**
     * Logic to map the model's data to the product catalog entries.
     */
    abstract public function toCatalogArray(): array;

    /**
     * Get the corresponding catalog product.
     */
    public function catalogProduct(): MorphOne
    {
        return $this->morphOne(Product::class, 'source');
    }

    /**
     * Synchronize the model with the product catalog.
     */
    public function syncWithCatalog(): void
    {
        $data = $this->toCatalogArray();
        $tenantId = $data['tenant_id'] ?? $this->tenant_id ?? InventoryTenantContext::id();
        $sku = $data['sku'] ?? $this->sku ?? (string) Str::uuid();

        // 1. Try to find by direct source link
        $product = Product::withoutGlobalScopes()
            ->where('source_type', get_class($this))
            ->where('source_id', $this->id)
            ->where('tenant_id', $tenantId)
            ->first();

        // 2. If not found, try to find by tenant and sku (adoption logic)
        if (!$product) {
            $product = Product::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('sku', $sku)
                ->first();
            
            if ($product) {
                // Adopt the product
                $product->source_type = get_class($this);
                $product->source_id = $this->id;
            } else {
                // Create new product
                $product = new Product();
                $product->tenant_id = $tenantId;
                $product->source_type = get_class($this);
                $product->source_id = $this->id;
                $product->sku = $sku;
            }
        }

        // 3. Update attributes
        $product->fill(array_merge($data, [
            'sku' => $sku,
            'status' => $data['status'] ?? 'published',
        ]));
        
        $product->save();
    }

    /**
     * Remove the model from the product catalog.
     */
    public function removeFromCatalog(): void
    {
        $this->catalogProduct()->delete();
    }
}
