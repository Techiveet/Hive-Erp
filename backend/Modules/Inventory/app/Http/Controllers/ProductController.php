<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\FileEntry;
use Modules\Core\Support\OwnedMediaPathResolver;
use Modules\Inventory\Enums\UomEnum;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductCategory;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Models\Tag;
use Modules\Inventory\Support\InventoryCountryCatalog;
use Modules\Inventory\Support\InventoryTenantContext;
use Modules\Inventory\Models\InventoryEntityRecord;
use Modules\Inventory\Services\QualityComplianceService;
use Symfony\Component\Intl\Countries;

class ProductController extends Controller
{
    public function summary()
    {
        $products = Product::query();

        return response()->json([
            'totals' => [
                'products' => (clone $products)->count(),
                'published' => (clone $products)->where('status', 'published')->count(),
                'draft' => (clone $products)->where('status', 'draft')->count(),
                'archived' => (clone $products)->where('status', 'archived')->count(),
                'variants' => (clone $products)->whereNotNull('parent_product_id')->count(),
                'low_stock' => (clone $products)
                    ->where('track_inventory', true)
                    ->whereColumn('quantity', '<=', 'reorder_point')
                    ->count(),
            ],
            'catalog' => [
                'categories' => ProductCategory::query()->count(),
                'tags' => Tag::query()->count(),
                'suppliers' => Supplier::query()->count(),
            ],
            'recent_products' => Product::query()
                ->with(['category:id,name', 'tags:id,name'])
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn (Product $product) => $this->decorateProduct($product)),
        ]);
    }

    public function options(Request $request)
    {
        $excludeProductId = $request->integer('exclude_product_id');

        $parentProductsQuery = Product::query()
            ->whereNull('parent_product_id')
            ->orderBy('name');

        if ($excludeProductId > 0) {
            $parentProductsQuery->where('id', '!=', $excludeProductId);
        }

        return response()->json([
            'categories' => ProductCategory::query()->orderBy('name')->get(['id', 'name', 'parent_id']),
            'tags' => Tag::query()->orderBy('name')->get(['id', 'name', 'slug']),
            'parent_products' => $parentProductsQuery->get(['id', 'name', 'sku']),
            'uom_options' => UomEnum::values(),
            'status_options' => ['draft', 'published', 'archived'],
            'currency_options' => [
                ['code' => 'USD', 'symbol' => '$', 'label' => 'USD - US Dollar'],
                ['code' => 'ETB', 'symbol' => 'Br', 'label' => 'ETB - Ethiopian Birr'],
                ['code' => 'EUR', 'symbol' => 'EUR', 'label' => 'EUR - Euro'],
                ['code' => 'GBP', 'symbol' => 'GBP', 'label' => 'GBP - Pound Sterling'],
                ['code' => 'AED', 'symbol' => 'AED', 'label' => 'AED - UAE Dirham'],
                ['code' => 'SAR', 'symbol' => 'SAR', 'label' => 'SAR - Saudi Riyal'],
                ['code' => 'CNY', 'symbol' => 'CNY', 'label' => 'CNY - Chinese Yuan'],
            ],
            'countries' => $this->countries(),
        ]);
    }

    public function generateBarcode(Request $request)
    {
        $prefix = preg_replace('/\D+/', '', (string) $request->input('prefix', '200')) ?: '200';
        $prefix = substr($prefix, 0, 6);

        $barcode = $this->generateUniqueBarcode($prefix);
        $svg = $this->renderBarcodeSvg($barcode);

        return response()->json([
            'barcode' => $barcode,
            'preview_data_url' => 'data:image/svg+xml;base64,' . base64_encode($svg),
        ]);
    }

    public function index(Request $request)
    {
        $query = Product::query()
            ->with([
                'category:id,name',
                'parent:id,name,sku',
                'tags:id,name,slug',
            ])
            ->withCount('variants');

        if ($request->filled('search')) {
            $term = '%' . trim((string) $request->input('search')) . '%';
            $query->where(function ($builder) use ($term): void {
                $builder
                    ->where('name', 'like', $term)
                    ->orWhere('sku', 'like', $term)
                    ->orWhere('stock_code', 'like', $term);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('product_category_id')) {
            $query->where('product_category_id', (int) $request->input('product_category_id'));
        }

        if ($request->boolean('variants_only', false)) {
            $query->whereNotNull('parent_product_id');
        }

        if ($request->boolean('low_stock_only', false)) {
            $query->where('track_inventory', true)
                ->whereColumn('quantity', '<=', 'reorder_point');
        }

        if ($request->boolean('ids_only', false)) {
            return response()->json(
                $query->pluck('id')->values()
            );
        }

        $sortableColumns = ['created_at', 'name', 'sku', 'stock_code', 'quantity', 'unit_price', 'status'];
        $sortCol = (string) $request->input('sort_col', 'created_at');
        if (!in_array($sortCol, $sortableColumns, true)) {
            $sortCol = 'created_at';
        }
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $products = $query
            ->orderBy($sortCol, $sortDir)
            ->paginate((int) $request->integer('per_page', 10));

        $productIds = $products->getCollection()->pluck('id')->toArray();
        $latestBatches = InventoryEntityRecord::query()
            ->where('entity_type', 'product_batches')
            ->whereIn('payload->product_id', $productIds)
            ->get()
            ->groupBy(fn ($item) => $item->payload['product_id'])
            ->map(fn ($group) => $group->sortByDesc('created_at')->first());

        $products->setCollection(
            $products->getCollection()->map(function (Product $product) use ($latestBatches) {
                $batch = $latestBatches->get($product->id);
                $product->setAttribute('qa_status', $batch?->payload['qa_status'] ?? 'no_batches');
                return $this->decorateProduct($product);
            })
        );

        return response()->json($products);
    }

    public function show($id)
    {
        $product = Product::query()
            ->with([
                'category:id,name,parent_id',
                'parent:id,name,sku',
                'variants:id,name,sku,parent_product_id,status,quantity,reorder_point,track_inventory',
                'tags:id,name,slug',
            ])
            ->findOrFail($id);

        $this->decorateProduct($product);

        return response()->json([
            'product' => $product,
            'country_name' => $this->resolveCountryName($product->country_of_origin),
        ]);
    }

    public function store(Request $request)
    {
        $product = $this->persist($request);

        return response()->json(
            $this->loadProduct($product->id),
            201
        );
    }

    public function update(Request $request, $id)
    {
        $product = Product::query()->findOrFail($id);
        $product = $this->persist($request, $product);

        return response()->json(
            $this->loadProduct($product->id)
        );
    }

    public function syncTags(Request $request, $id)
    {
        $tenantId = InventoryTenantContext::id();
        $product = Product::query()->findOrFail($id);

        $validated = $request->validate([
            'tag_ids' => ['required', 'array'],
            'tag_ids.*' => [
                'required',
                'integer',
                Rule::exists(\Modules\Inventory\Models\Tag::class, 'id')->where(
                    fn ($query) => $query->where('tenant_id', $tenantId)
                ),
            ],
        ]);

        $product->tags()->sync($validated['tag_ids']);

        return response()->json(
            $this->loadProduct($product->id)
        );
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'min:1'],
        ]);

        $products = Product::query()
            ->whereIn('id', $validated['ids'])
            ->get();

        /** @var Product $product */
        foreach ($products as $product) {
            $this->deleteAssets($product);
            $product->delete();
        }

        return response()->json([
            'deleted_count' => $products->count(),
        ]);
    }

    public function assignShelf(Request $request, $id)
    {
        $product = Product::query()->findOrFail($id);

        $validated = $request->validate([
            'shelf_box_id' => ['required', 'integer', 'min:1'],
        ]);

        $shelfBox = \Modules\Inventory\Models\InventoryEntityRecord::query()
            ->where('entity_type', 'shelf_boxes')
            ->where('id', $validated['shelf_box_id'])
            ->firstOrFail();

        $product->update([
            'metadata' => array_merge($product->metadata ?? [], [
                'assigned_shelf_box' => [
                    'shelf_box_id' => $shelfBox->id,
                    'shelf_box_name' => $shelfBox->name,
                    'assigned_at' => now()->toIso8601String(),
                    'assigned_by' => auth()->id(),
                ],
            ]),
        ]);

        return response()->json([
            'message' => 'Shelf assigned successfully',
            'product' => $product->fresh(),
        ]);
    }

    public function bulkUpdateStatus(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'min:1'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
        ]);

        $updatedCount = Product::query()
            ->whereIn('id', $validated['ids'])
            ->update([
                'status' => $validated['status'],
            ]);

        return response()->json([
            'updated_count' => $updatedCount,
            'status' => $validated['status'],
        ]);
    }

    public function destroy($id)
    {
        $product = Product::query()->findOrFail($id);
        $this->deleteAssets($product);
        $product->tags()->detach();
        $product->delete();

        return response()->json(null, 204);
    }

    protected function persist(Request $request, ?Product $product = null): Product
    {
        $tenantId = InventoryTenantContext::id();
        $isUpdate = $product !== null;
        $this->normalizeInputs($request, $isUpdate);

        $validated = $request->validate($this->rules($tenantId, $product));

        $unitPrice = array_key_exists('unit_price', $validated)
            ? (float) $validated['unit_price']
            : (float) ($product?->unit_price ?? 0);
        $salePrice = $validated['sale_price'] ?? $product?->sale_price;
        if ($salePrice !== null && (float) $salePrice >= $unitPrice) {
            throw ValidationException::withMessages([
                'sale_price' => 'Sale price must be less than unit price.',
            ]);
        }

        if ($request->has('is_variant')) {
            if ($validated['is_variant']) {
                $validated['attributes'] = $this->filterKeyValueRows($validated['variant_attributes'] ?? []);
                if (empty($validated['parent_product_id']) && empty($product?->parent_product_id)) {
                    throw ValidationException::withMessages([
                        'parent_product_id' => 'Parent product is required when variant mode is enabled.',
                    ]);
                }
            } else {
                $validated['parent_product_id'] = null;
                $validated['attributes'] = null;
            }
        }

        if ($request->has('nutritional_info')) {
            $validated['nutritional_info'] = $this->filterKeyValueRows($validated['nutritional_info'] ?? []);
        }

        if ($request->has('unit') || $request->has('uom')) {
            $validated['unit'] = $validated['unit'] ?? $validated['uom'] ?? null;
        }

        $validated['supplier_id'] = null;

        if ($request->has('country_of_origin')) {
            $validated['country_of_origin'] = isset($validated['country_of_origin'])
                ? strtoupper((string) $validated['country_of_origin'])
                : null;
        }

        $validated = \Illuminate\Support\Arr::except($validated, ['variant_attributes', 'is_variant', 'uom']);

        if (empty($validated['sku'])) {
            $validated['sku'] = $product?->sku ?: $this->generateUniqueSku(
                (string) ($validated['name'] ?? $product?->name ?? 'item')
            );
        }

        if (!$isUpdate) {
            $validated['tenant_id'] = $tenantId;
            $validated['quantity'] = (float) ($validated['initial_stock'] ?? 0);
        } elseif (array_key_exists('set_quantity', $validated)) {
            $validated['quantity'] = (float) $validated['set_quantity'];
        }

        $validated = \Illuminate\Support\Arr::except($validated, ['initial_stock', 'set_quantity']);

        if ($request->hasFile('new_image')) {
            if ($product && !empty($product->image)) {
                Storage::disk('public')->delete($product->image);
            }
            $validated['image'] = $request->file('new_image')->store('inventory/products', 'public');
        } elseif ($request->filled('image_path')) {
            $validated['image'] = $request->input('image_path');
        }

        if ($request->hasFile('new_3d_model')) {
            if ($product && !empty($product->model_3d_path)) {
                Storage::disk('public')->delete($product->model_3d_path);
            }
            $validated['model_3d_path'] = $request->file('new_3d_model')->store('inventory/product-models', 'public');
        } elseif ($request->filled('model_3d_path')) {
            $validated['model_3d_path'] = $request->input('model_3d_path');
        }

        $validated = \Illuminate\Support\Arr::except($validated, [
            'tags',
            'image_path',
            'new_image',
            'new_3d_model'
        ]);

        if ($product) {
            $product->fill($validated);
            $product->save();
            $record = $product->fresh();
        } else {
            $record = new Product($validated);
            $record->save();
            $record = $record->fresh();
        }

        if ($request->has('barcode') && !empty($validated['barcode'])) {
            $barcodePath = $this->storeBarcodeSvg($record, (string) $validated['barcode']);
            $record->updateQuietly(['barcode_path' => $barcodePath]);
        } elseif ($request->has('barcode') && empty($validated['barcode']) && $product && !empty($product->barcode_path)) {
            Storage::disk('public')->delete((string) $product->barcode_path);
            $record->updateQuietly(['barcode_path' => null]);
        }

        if ($request->has('tags')) {
            $tagIds = array_values(array_unique(array_map('intval', $request->input('tags', []))));
            $record->tags()->sync($tagIds);
        }

        return $record;
    }

    protected function rules(string $tenantId, ?Product $product = null): array
    {
        $productId = $product?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => [
                'nullable',
                'string',
                'alpha_dash',
                'max:255',
                Rule::unique(\Modules\Inventory\Models\Product::class, 'sku')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($productId),
            ],
            'stock_code' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'new_image' => ['nullable', 'image', 'max:2048'],
            'image_path' => ['nullable', 'string'],
            'new_3d_model' => ['nullable', 'file', 'mimes:glb,gltf', 'max:20240'],
            'model_3d_path' => ['nullable', 'string'],
            'cost_of_good' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:10'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'barcode' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique(\Modules\Inventory\Models\Product::class, 'barcode')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($productId),
            ],
            'track_inventory' => ['sometimes', 'boolean'],
            'allow_backorders' => ['sometimes', 'boolean'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'product_category_id' => [
                'nullable',
                Rule::exists(\Modules\Inventory\Models\ProductCategory::class, 'id')->where(
                    fn ($query) => $query->where('tenant_id', $tenantId)
                ),
            ],
            'parent_product_id' => [
                'nullable',
                Rule::exists(\Modules\Inventory\Models\Product::class, 'id')->where(
                    fn ($query) => $query->where('tenant_id', $tenantId)
                ),
            ],
            'is_variant' => ['sometimes', 'boolean'],
            'variant_attributes' => ['nullable', 'array'],
            'variant_attributes.*.key' => ['nullable', 'string'],
            'variant_attributes.*.value' => ['nullable', 'string'],
            'unit' => ['nullable', Rule::in(UomEnum::values())],
            'uom' => ['nullable', Rule::in(UomEnum::values())],
            'units_per_package' => ['nullable', 'integer', 'min:1'],
            'reorder_point' => ['required', 'integer', 'min:0'],
            'initial_stock' => ['nullable', 'numeric', 'min:0'],
            'set_quantity' => ['nullable', 'numeric', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'length' => ['nullable', 'numeric', 'min:0'],
            'width' => ['nullable', 'numeric', 'min:0'],
            'height' => ['nullable', 'numeric', 'min:0'],
            'tags' => ['nullable', 'array'],
            'tags.*' => [
                'integer',
                Rule::exists(\Modules\Inventory\Models\Tag::class, 'id')->where(
                    fn ($query) => $query->where('tenant_id', $tenantId)
                ),
            ],
            'hs_code' => ['nullable', 'string', 'max:255'],
            'country_of_origin' => ['nullable', 'string', 'size:2'],
            'nutritional_info' => ['nullable', 'array'],
            'nutritional_info.*.key' => ['nullable', 'string'],
            'nutritional_info.*.value' => ['nullable', 'string'],
        ];
    }

    protected function normalizeInputs(Request $request, bool $isUpdate): void
    {
        $normalized = [];

        foreach (['tags', 'variant_attributes', 'nutritional_info'] as $field) {
            if ($request->has($field)) {
                $normalized[$field] = $this->normalizeArrayInput($request->input($field));
            }
        }

        foreach (['is_variant', 'track_inventory', 'allow_backorders'] as $booleanField) {
            if ($request->has($booleanField)) {
                $normalized[$booleanField] = filter_var(
                    $request->input($booleanField),
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                ) ?? false;
            }
        }

        if (!$isUpdate) {
            $normalized['track_inventory'] = $normalized['track_inventory'] ?? true;
            $normalized['allow_backorders'] = $normalized['allow_backorders'] ?? false;
            $normalized['initial_stock'] = $request->input('initial_stock', 0);
        }

        if ($request->has('country_of_origin')) {
            $normalized['country_of_origin'] = strtoupper((string) $request->input('country_of_origin'));
        }

        $request->merge($normalized);
    }

    protected function normalizeArrayInput(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    protected function filterKeyValueRows(array $rows): array
    {
        return array_values(array_filter($rows, static function ($row): bool {
            if (!is_array($row)) {
                return false;
            }

            return filled($row['key'] ?? null) && filled($row['value'] ?? null);
        }));
    }

    protected function loadProduct(int $id): Product
    {
        $product = Product::query()
            ->with([
                'category:id,name',
                'parent:id,name,sku',
                'variants:id,name,sku,parent_product_id,status,quantity,reorder_point,track_inventory',
                'tags:id,name,slug',
            ])
            ->findOrFail($id);

        return $this->decorateProduct($product);
    }

    protected function decorateProduct(Product $product): Product
    {
        $product->makeHidden(['supplier_id']);
        $product->setAttribute('image_preview_url', $this->resolveProductAssetPreviewUrl($product->image));
        $product->setAttribute('model_3d_preview_url', $this->resolveProductAssetPreviewUrl($product->model_3d_path));

        if (!$product->offsetExists('qa_status')) {
            $product->setAttribute('qa_status', app(QualityComplianceService::class)->getProductLatestQaStatus($product->id));
        }

        $product->setAttribute('workflow_status', $product->workflow_status);

        return $product;
    }


    protected function resolveProductAssetPreviewUrl(?string $path): ?string
    {
        $value = trim((string) $path);
        if ($value === '') {
            return null;
        }

        if (
            Str::startsWith($value, ['http://', 'https://']) ||
            (str_contains($value, '/api/v1/files/') && str_contains($value, '/serve'))
        ) {
            return $value;
        }

        $ownerId = (int) auth()->id();
        if ($ownerId <= 0) {
            return null;
        }

        $media = app(OwnedMediaPathResolver::class)->resolveOwnedMediaFromPath($value, $ownerId);

        if (!$media || $media->model_type !== FileEntry::class) {
            return null;
        }

        return url("/api/v1/files/{$media->model_id}/serve");
    }

    protected function generateUniqueSku(string $base): string
    {
        $seed = Str::upper(Str::slug($base, '-'));
        $seed = $seed !== '' ? $seed : 'ITEM';
        $candidate = $seed;
        $counter = 1;

        while (Product::query()->where('sku', $candidate)->exists()) {
            $candidate = "{$seed}-{$counter}";
            $counter++;
        }

        return $candidate;
    }

    protected function generateUniqueBarcode(string $prefix): string
    {
        do {
            $randomBody = str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
            $base12 = substr($prefix . $randomBody, 0, 12);
            $base12 = str_pad($base12, 12, '0');
            $barcode = $this->calculateEan13Checksum($base12);
        } while (Product::query()->where('barcode', $barcode)->exists());

        return $barcode;
    }

    protected function calculateEan13Checksum(string $number): string
    {
        $number = preg_replace('/\D+/', '', $number) ?? '';
        $number = str_pad(substr($number, 0, 12), 12, '0');

        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $number[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        $checksum = (10 - ($sum % 10)) % 10;
        return $number . $checksum;
    }

    protected function storeBarcodeSvg(Product $product, string $barcode): string
    {
        $svg = $this->renderBarcodeSvg($barcode);
        $path = "inventory/barcodes/product-{$product->id}.svg";
        Storage::disk('public')->put($path, $svg);
        return $path;
    }

    protected function renderBarcodeSvg(string $barcode): string
    {
        $digits = preg_replace('/\D+/', '', $barcode) ?? '';

        if (strlen($digits) !== 13) {
            $safeText = e($barcode);
            return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="420" height="90" viewBox="0 0 420 90">
  <rect width="100%" height="100%" fill="white" />
  <text x="12" y="45" font-family="monospace" font-size="24" fill="black">{$safeText}</text>
</svg>
SVG;
        }

        $lPatterns = [
            '0' => '0001101', '1' => '0011001', '2' => '0010011', '3' => '0111101', '4' => '0100011',
            '5' => '0110001', '6' => '0101111', '7' => '0111011', '8' => '0110111', '9' => '0001011',
        ];
        $gPatterns = [
            '0' => '0100111', '1' => '0110011', '2' => '0011011', '3' => '0100001', '4' => '0011101',
            '5' => '0111001', '6' => '0000101', '7' => '0010001', '8' => '0001001', '9' => '0010111',
        ];
        $rPatterns = [
            '0' => '1110010', '1' => '1100110', '2' => '1101100', '3' => '1000010', '4' => '1011100',
            '5' => '1001110', '6' => '1010000', '7' => '1000100', '8' => '1001000', '9' => '1110100',
        ];
        $parityPatterns = [
            '0' => 'LLLLLL', '1' => 'LLGLGG', '2' => 'LLGGLG', '3' => 'LLGGGL', '4' => 'LGLLGG',
            '5' => 'LGGLLG', '6' => 'LGGGLL', '7' => 'LGLGLG', '8' => 'LGLGGL', '9' => 'LGGLGL',
        ];

        $firstDigit = $digits[0];
        $leftDigits = substr($digits, 1, 6);
        $rightDigits = substr($digits, 7, 6);
        $parity = $parityPatterns[$firstDigit] ?? 'LLLLLL';

        $bits = '101';
        for ($i = 0; $i < 6; $i++) {
            $digit = $leftDigits[$i];
            $bits .= $parity[$i] === 'L'
                ? $lPatterns[$digit]
                : $gPatterns[$digit];
        }
        $bits .= '01010';
        for ($i = 0; $i < 6; $i++) {
            $digit = $rightDigits[$i];
            $bits .= $rPatterns[$digit];
        }
        $bits .= '101';

        $barWidth = 2;
        $quietZone = 12;
        $barHeight = 60;
        $svgWidth = (strlen($bits) * $barWidth) + ($quietZone * 2);
        $svgHeight = 90;

        $bars = [];
        $x = $quietZone;
        for ($i = 0; $i < strlen($bits); $i++) {
            if ($bits[$i] === '1') {
                $bars[] = "<rect x=\"{$x}\" y=\"6\" width=\"{$barWidth}\" height=\"{$barHeight}\" fill=\"black\" />";
            }
            $x += $barWidth;
        }

        $textY = 82;
        $safeDigits = e($digits);
        $barMarkup = implode("\n  ", $bars);

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$svgWidth}" height="{$svgHeight}" viewBox="0 0 {$svgWidth} {$svgHeight}">
  <rect width="100%" height="100%" fill="white" />
  {$barMarkup}
  <text x="{$quietZone}" y="{$textY}" font-family="monospace" font-size="14" fill="black">{$safeDigits}</text>
</svg>
SVG;
    }

    protected function deleteAssets(Product $product): void
    {
        foreach (['image', 'model_3d_path', 'barcode_path'] as $pathField) {
            $path = (string) ($product->{$pathField} ?? '');
            if ($path !== '') {
                Storage::disk('public')->delete($path);
            }
        }
    }

    protected function countries(): array
    {
        if (!class_exists(Countries::class)) {
            return InventoryCountryCatalog::all();
        }

        $names = Countries::getNames('en');
        asort($names);

        $countries = [];
        foreach ($names as $code => $name) {
            $countries[] = [
                'code' => strtoupper((string) $code),
                'name' => $name,
            ];
        }

        return $countries;
    }

    protected function resolveCountryName(?string $countryCode): ?string
    {
        $normalized = strtoupper(trim((string) $countryCode));
        if ($normalized === '') {
            return null;
        }

        if (class_exists(Countries::class)) {
            $countryName = Countries::getName($normalized, 'en');
            if (filled($countryName)) {
                return $countryName;
            }
        }

        return InventoryCountryCatalog::name($normalized);
    }
}
