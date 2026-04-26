<?php

namespace Tests\Feature;

use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductCategory;
use Modules\Inventory\Models\Tag;
use Tests\TestCase;

class InventoryProductCatalogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $databasePath = (string) env('DB_DATABASE', database_path('testing-inventory.sqlite'));
        if ($databasePath !== ':memory:' && ! file_exists($databasePath)) {
            $databaseDirectory = dirname($databasePath);
            if (! is_dir($databaseDirectory)) {
                mkdir($databaseDirectory, 0777, true);
            }

            touch($databasePath);
        }

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', $databasePath);
        config()->set('database.connections.central', array_merge(
            config('database.connections.sqlite'),
            ['database' => $databasePath]
        ));

        app('db')->purge();

        $this->artisan('migrate:fresh', [
            '--path' => base_path('Modules/Inventory/database/migrations'),
            '--realpath' => true,
            '--force' => true,
        ]);
        $this->withoutMiddleware();
    }

    public function test_product_options_include_expected_currencies_and_country_list(): void
    {
        $response = $this->getJson('/api/v1/inventory/products/options');

        $response->assertOk();

        $currencies = collect($response->json('currency_options'));
        $countries = collect($response->json('countries'));

        $this->assertTrue($currencies->contains(fn (array $currency) => $currency['code'] === 'USD'));
        $this->assertTrue($currencies->contains(fn (array $currency) => $currency['code'] === 'ETB'));
        $this->assertTrue($countries->contains(fn (array $country) => $country['code'] === 'ET' && $country['name'] === 'Ethiopia'));
        $this->assertTrue($countries->contains(fn (array $country) => $country['code'] === 'US'));
    }

    public function test_it_can_create_update_and_show_a_product_using_currency_country_and_nested_form_fields(): void
    {
        [$category, $primaryTag, $secondaryTag, $parentProduct] = $this->seedCatalog();

        $createResponse = $this->post('/api/v1/inventory/products', [
            'name' => 'Inventory Test Water',
            'sku' => 'INV-TEST-001',
            'stock_code' => 'INV-STOCK-001',
            'description' => 'Created through the product catalog form flow.',
            'status' => 'draft',
            'product_category_id' => (string) $category->id,
            'unit' => 'unit',
            'uom' => 'unit',
            'currency' => 'ETB',
            'unit_price' => '129.99',
            'tax_rate' => '15',
            'cost_of_good' => '100.00',
            'sale_price' => '89.50',
            'reorder_point' => '5',
            'initial_stock' => '12',
            'barcode' => '1234567890128',
            'is_variant' => 'true',
            'parent_product_id' => (string) $parentProduct->id,
            'track_inventory' => 'true',
            'allow_backorders' => 'false',
            'tags' => json_encode([$primaryTag->id]),
            'variant_attributes' => json_encode([
                ['key' => 'flavor', 'value' => 'lemon'],
            ]),
            'nutritional_info' => json_encode([
                ['key' => 'Calories', 'value' => '25 kcal'],
            ]),
            'country_of_origin' => 'ET',
            'hs_code' => '2201.10',
            'units_per_package' => '24',
            'weight' => '1.250',
            'length' => '10',
            'width' => '5',
            'height' => '25',
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('currency', 'ETB')
            ->assertJsonPath('country_of_origin', 'ET')
            ->assertJsonPath('quantity', '12.000')
            ->assertJsonPath('parent_product_id', $parentProduct->id)
            ->assertJsonPath('tags.0.name', $primaryTag->name);

        $this->assertArrayNotHasKey('supplier_id', $createResponse->json());
        $this->assertArrayNotHasKey('supplier', $createResponse->json());

        $productId = (int) $createResponse->json('id');
        $this->assertGreaterThan(0, $productId);

        $updateResponse = $this->patch("/api/v1/inventory/products/{$productId}", [
            'name' => 'Inventory Test Water Updated',
            'sku' => 'INV-TEST-001',
            'stock_code' => 'INV-STOCK-002',
            'description' => 'Updated through the product catalog form flow.',
            'status' => 'published',
            'product_category_id' => (string) $category->id,
            'unit' => 'box',
            'uom' => 'box',
            'currency' => 'USD',
            'unit_price' => '149.99',
            'tax_rate' => '18',
            'cost_of_good' => '110.00',
            'sale_price' => '99.99',
            'reorder_point' => '8',
            'set_quantity' => '18',
            'barcode' => '1234567890128',
            'is_variant' => 'true',
            'parent_product_id' => (string) $parentProduct->id,
            'track_inventory' => 'true',
            'allow_backorders' => 'true',
            'tags' => json_encode([$secondaryTag->id]),
            'variant_attributes' => json_encode([
                ['key' => 'flavor', 'value' => 'berry'],
                ['key' => 'size', 'value' => 'family'],
            ]),
            'nutritional_info' => json_encode([
                ['key' => 'Calories', 'value' => '30 kcal'],
                ['key' => 'Sugar', 'value' => '4 g'],
            ]),
            'country_of_origin' => 'US',
            'hs_code' => '2201.90',
            'units_per_package' => '30',
            'weight' => '1.750',
            'length' => '12',
            'width' => '6',
            'height' => '28',
        ]);

        $updateResponse
            ->assertOk()
            ->assertJsonPath('name', 'Inventory Test Water Updated')
            ->assertJsonPath('currency', 'USD')
            ->assertJsonPath('country_of_origin', 'US')
            ->assertJsonPath('quantity', '18.000')
            ->assertJsonPath('parent_product_id', $parentProduct->id)
            ->assertJsonPath('allow_backorders', true)
            ->assertJsonPath('tags.0.name', $secondaryTag->name);

        $this->assertArrayNotHasKey('supplier_id', $updateResponse->json());
        $this->assertArrayNotHasKey('supplier', $updateResponse->json());

        $showResponse = $this->getJson("/api/v1/inventory/products/{$productId}");

        $showResponse
            ->assertOk()
            ->assertJsonPath('product.name', 'Inventory Test Water Updated')
            ->assertJsonPath('product.currency', 'USD')
            ->assertJsonPath('product.country_of_origin', 'US')
            ->assertJsonPath('country_name', 'United States');

        $this->assertArrayNotHasKey('supplier_id', $showResponse->json('product'));
        $this->assertArrayNotHasKey('supplier', $showResponse->json('product'));

        $product = Product::query()->with('tags')->findOrFail($productId);

        $this->assertSame('USD', $product->currency);
        $this->assertSame('US', $product->country_of_origin);
        $this->assertSame('18.000', $product->quantity);
        $this->assertSame($parentProduct->id, $product->parent_product_id);
        $this->assertSame('Inventory Test Water Updated', $product->name);
        $this->assertSame([$secondaryTag->id], $product->tags->pluck('id')->all());
        $this->assertSame('berry', $product->attributes[0]['value'] ?? null);
        $this->assertSame('30 kcal', $product->nutritional_info[0]['value'] ?? null);
    }

    /**
     * @return array{0: ProductCategory, 1: Tag, 2: Tag, 3: Product}
     */
    protected function seedCatalog(): array
    {
        $category = ProductCategory::query()->create([
            'tenant_id' => 'central',
            'name' => 'Beverages',
            'is_active' => true,
        ]);

        $primaryTag = Tag::query()->create([
            'tenant_id' => 'central',
            'name' => 'featured',
            'slug' => 'featured',
            'is_active' => true,
        ]);

        $secondaryTag = Tag::query()->create([
            'tenant_id' => 'central',
            'name' => 'export-ready',
            'slug' => 'export-ready',
            'is_active' => true,
        ]);

        $parentProduct = Product::query()->create([
            'tenant_id' => 'central',
            'name' => 'Parent Variant Product',
            'sku' => 'PARENT-SKU-001',
            'status' => 'published',
            'currency' => 'USD',
            'unit_price' => '50.00',
            'tax_rate' => '15.00',
            'cost_of_good' => '25.00',
            'reorder_point' => 1,
            'quantity' => '10.000',
        ]);

        return [$category, $primaryTag, $secondaryTag, $parentProduct];
    }
}
