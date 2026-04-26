<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Core\Support\HandlesScalableTabularExports;
use Modules\Core\Support\ResolvesExportBranding;
use Modules\Inventory\Exports\GenericRowsExport;
use Modules\Inventory\Models\InventoryEntityRecord;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductCategory;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Models\Tag;
use Modules\Inventory\Support\InventoryEntityCatalog;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryExportController extends Controller
{
    use HandlesScalableTabularExports;
    use ResolvesExportBranding;

    public function products(Request $request): JsonResponse|StreamedResponse|Response
    {
        $query = Product::query()->with([
            'category:id,name',
            'supplier:id,name',
        ]);

        if ($request->filled('ids')) {
            $query->whereIn('id', $this->normalizeIds($request->input('ids')));
        } else {
            if ($request->filled('search')) {
                $term = '%' . trim((string) $request->input('search')) . '%';
                $query->where(function (Builder $builder) use ($term): void {
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

            if ($request->filled('supplier_id')) {
                $query->where('supplier_id', (int) $request->input('supplier_id'));
            }
        }

        $this->applySort($request, $query, [
            'created_at',
            'name',
            'sku',
            'stock_code',
            'quantity',
            'unit_price',
            'status',
        ], 'created_at');

        $columns = [
            'Name' => fn (Product $record) => $record->name,
            'SKU' => fn (Product $record) => $record->sku,
            'Stock Code' => fn (Product $record) => $record->stock_code ?: '-',
            'Category' => fn (Product $record) => $record->category?->name ?: 'Uncategorized',
            'Supplier' => fn (Product $record) => $record->supplier?->name ?: 'Not set',
            'Unit' => fn (Product $record) => $record->unit ?: '-',
            'Quantity' => fn (Product $record) => (string) $record->quantity,
            'Reorder Point' => fn (Product $record) => (string) $record->reorder_point,
            'Unit Price' => fn (Product $record) => (string) $record->unit_price,
            'Status' => fn (Product $record) => Str::title((string) $record->status),
            'Created At' => fn (Product $record) => optional($record->created_at)->format('Y-m-d H:i:s') ?? '-',
        ];

        return $this->handleExport(
            request: $request,
            query: $query,
            filenameBase: 'inventory_products_' . now()->format('Y-m-d_His'),
            title: 'Inventory Products',
            columns: $columns
        );
    }

    public function productCategories(Request $request): JsonResponse|StreamedResponse|Response
    {
        $query = ProductCategory::query()
            ->with('parent:id,name')
            ->withCount('products');

        if ($request->filled('ids')) {
            $query->whereIn('id', $this->normalizeIds($request->input('ids')));
        } else {
            if ($request->filled('search')) {
                $term = '%' . trim((string) $request->input('search')) . '%';
                $query->where('name', 'like', $term);
            }
        }

        $this->applySort($request, $query, ['created_at', 'name', 'is_active'], 'created_at');

        $columns = [
            'Category' => fn (ProductCategory $record) => $record->name,
            'Parent' => fn (ProductCategory $record) => $record->parent?->name ?: 'None',
            'Products' => fn (ProductCategory $record) => (string) ($record->products_count ?? 0),
            'Status' => fn (ProductCategory $record) => $record->is_active ? 'Active' : 'Inactive',
            'Created At' => fn (ProductCategory $record) => optional($record->created_at)->format('Y-m-d H:i:s') ?? '-',
        ];

        return $this->handleExport(
            request: $request,
            query: $query,
            filenameBase: 'inventory_product_categories_' . now()->format('Y-m-d_His'),
            title: 'Inventory Product Categories',
            columns: $columns
        );
    }

    public function tags(Request $request): JsonResponse|StreamedResponse|Response
    {
        $query = Tag::query()->withCount('products');

        if ($request->filled('ids')) {
            $query->whereIn('id', $this->normalizeIds($request->input('ids')));
        } else {
            if ($request->filled('search')) {
                $term = '%' . trim((string) $request->input('search')) . '%';
                $query->where(function (Builder $builder) use ($term): void {
                    $builder
                        ->where('name', 'like', $term)
                        ->orWhere('slug', 'like', $term);
                });
            }
        }

        $this->applySort($request, $query, ['created_at', 'name', 'slug', 'is_active'], 'created_at');

        $columns = [
            'Tag' => fn (Tag $record) => $record->name,
            'Slug' => fn (Tag $record) => $record->slug,
            'Products' => fn (Tag $record) => (string) ($record->products_count ?? 0),
            'Status' => fn (Tag $record) => $record->is_active ? 'Active' : 'Inactive',
            'Created At' => fn (Tag $record) => optional($record->created_at)->format('Y-m-d H:i:s') ?? '-',
        ];

        return $this->handleExport(
            request: $request,
            query: $query,
            filenameBase: 'inventory_tags_' . now()->format('Y-m-d_His'),
            title: 'Inventory Tags',
            columns: $columns
        );
    }

    public function suppliers(Request $request): JsonResponse|StreamedResponse|Response
    {
        $query = Supplier::query()->withCount('products');

        if ($request->filled('ids')) {
            $query->whereIn('id', $this->normalizeIds($request->input('ids')));
        } else {
            if ($request->filled('search')) {
                $term = '%' . trim((string) $request->input('search')) . '%';
                $query->where(function (Builder $builder) use ($term): void {
                    $builder
                        ->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('code', 'like', $term);
                });
            }
        }

        $this->applySort($request, $query, ['created_at', 'name', 'code', 'email', 'is_active'], 'name');

        $columns = [
            'Supplier' => fn (Supplier $record) => $record->name,
            'Code' => fn (Supplier $record) => $record->code ?: '-',
            'Email' => fn (Supplier $record) => $record->email ?: '-',
            'Phone' => fn (Supplier $record) => $record->phone ?: '-',
            'Status' => fn (Supplier $record) => $record->is_active ? 'Active' : 'Inactive',
            'Products' => fn (Supplier $record) => (string) ($record->products_count ?? 0),
            'Created At' => fn (Supplier $record) => optional($record->created_at)->format('Y-m-d H:i:s') ?? '-',
        ];

        return $this->handleExport(
            request: $request,
            query: $query,
            filenameBase: 'inventory_suppliers_' . now()->format('Y-m-d_His'),
            title: 'Inventory Suppliers',
            columns: $columns
        );
    }

    public function entities(Request $request, string $resource): JsonResponse|StreamedResponse|Response
    {
        $entityType = InventoryEntityCatalog::entityTypeFor($resource);
        abort_unless($entityType !== null, Response::HTTP_NOT_FOUND, 'Unsupported inventory entity resource.');

        $query = InventoryEntityRecord::query()
            ->where('entity_type', $entityType)
            ->with('parent:id,name,code');

        if ($request->filled('ids')) {
            $query->whereIn('id', $this->normalizeIds($request->input('ids')));
        } else {
            if ($request->filled('search')) {
                $term = '%' . trim((string) $request->input('search')) . '%';
                $query->where(function (Builder $builder) use ($term): void {
                    $builder
                        ->where('name', 'like', $term)
                        ->orWhere('code', 'like', $term);
                });
            }

            if ($request->filled('parent_id')) {
                $query->where('parent_id', (int) $request->input('parent_id'));
            }

            if ($request->boolean('active_only')) {
                $query->where('is_active', true);
            }
        }

        $this->applySort($request, $query, ['created_at', 'updated_at', 'name', 'code', 'is_active'], 'name');

        $resourceTitle = Str::of(str_replace('-', ' ', $resource))->title()->toString();
        $columns = [
            'Name' => fn (InventoryEntityRecord $record) => $record->name,
            'Code' => fn (InventoryEntityRecord $record) => $record->code ?: '-',
            'Parent' => fn (InventoryEntityRecord $record) => $record->parent?->name ?: 'None',
            'Status' => fn (InventoryEntityRecord $record) => $record->is_active ? 'Active' : 'Inactive',
            'Updated At' => fn (InventoryEntityRecord $record) => optional($record->updated_at)->format('Y-m-d H:i:s') ?? '-',
        ];

        return $this->handleExport(
            request: $request,
            query: $query,
            filenameBase: 'inventory_' . str_replace('-', '_', $resource) . '_' . now()->format('Y-m-d_His'),
            title: "Inventory {$resourceTitle}",
            columns: $columns
        );
    }

    /**
     * @param  Builder  $query
     * @param  array<string, Closure>  $columns
     */
    protected function handleExport(
        Request $request,
        Builder $query,
        string $filenameBase,
        string $title,
        array $columns
    ): JsonResponse|StreamedResponse|Response {
        $type = $this->normalizeExportType((string) $request->query('type', $request->query('format', 'xlsx')));
        $headings = array_keys($columns);
        $branding = $this->getExportBranding(true);

        if ($type === 'csv') {
            return $this->streamCsvDownload(
                $query,
                "{$filenameBase}.csv",
                $headings,
                function ($record) use ($columns): array {
                    return array_values($this->transformRecord($record, $columns));
                }
            );
        }

        if ($type === 'xlsx') {
            $this->enforceExcelLimit($query);
            $rows = (clone $query)->get()->map(
                fn ($record): array => array_values($this->transformRecord($record, $columns))
            )->all();

            return Excel::download(
                new GenericRowsExport($headings, $rows),
                "{$filenameBase}.xlsx",
                \Maatwebsite\Excel\Excel::XLSX
            );
        }

        if ($type === 'pdf') {
            ini_set('memory_limit', '512M');
            $this->enforcePdfLimit($query);
            $rows = (clone $query)
                ->limit($this->maxPdfRows())
                ->get()
                ->map(fn ($record): array => array_values($this->transformRecord($record, $columns)))
                ->all();

            $html = $this->buildPdfHtml($title, $headings, $rows, $branding);
            
            \Log::info("Generating Inventory PDF export", [
                'rows' => count($rows),
                'html_length' => strlen($html),
                'user_id' => auth()->id()
            ]);

            return Pdf::loadHTML($html)
                ->setPaper('a4', 'landscape')
                ->setWarnings(false)
                ->setOptions(['isRemoteEnabled' => true, 'isHtml5ParserEnabled' => true])
                ->download("{$filenameBase}.pdf");
        }

        if ($type === 'copy') {
            $this->enforceCopyLimit($query);
            $rows = (clone $query)
                ->limit($this->maxCopyRows())
                ->get()
                ->map(fn ($record): array => $this->transformRecord($record, $columns))
                ->values();

            return response()->json([
                'logo_url' => $branding['logo_url'],
                'branding' => $branding,
                'data' => $rows,
            ]);
        }

        $this->enforcePrintLimit($query);
        $rows = (clone $query)
            ->limit($this->maxPrintRows())
            ->get()
            ->map(fn ($record): array => $this->transformRecord($record, $columns))
            ->values();

        return response()->json([
            'logo_url' => $branding['logo_url'],
            'branding' => $branding,
            'data' => $rows,
        ]);
    }

    protected function normalizeExportType(string $type): string
    {
        $normalized = strtolower(trim($type));
        $normalized = $normalized === 'excel' ? 'xlsx' : $normalized;

        abort_unless(
            in_array($normalized, ['csv', 'xlsx', 'pdf', 'print', 'copy'], true),
            Response::HTTP_BAD_REQUEST,
            'Invalid export format.'
        );

        return $normalized;
    }

    /**
     * @param  array<string, Closure>  $columns
     * @return array<string, string>
     */
    protected function transformRecord(mixed $record, array $columns): array
    {
        $row = [];
        foreach ($columns as $heading => $resolver) {
            $value = $resolver($record);
            $row[$heading] = is_scalar($value) || $value === null
                ? (string) ($value ?? '')
                : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $row;
    }

    /**
     * @return array<int, int>
     */
    protected function normalizeIds(mixed $ids): array
    {
        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }

        if (!is_array($ids)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($value): int => (int) $value,
            $ids
        ), static fn (int $value): bool => $value > 0)));
    }

    /**
     * @param  array<int, string>  $allowedColumns
     */
    protected function applySort(
        Request $request,
        Builder $query,
        array $allowedColumns,
        string $defaultColumn
    ): void {
        $sortCol = (string) ($request->input('sortCol') ?? $request->input('sort_col') ?? $defaultColumn);
        $sortDir = strtolower((string) ($request->input('sortDir') ?? $request->input('sort_dir') ?? 'desc'));
        $sortDir = $sortDir === 'asc' ? 'asc' : 'desc';

        if (!in_array($sortCol, $allowedColumns, true)) {
            $sortCol = $defaultColumn;
        }

        $query->orderBy($sortCol, $sortDir);
    }

    /**
     * @param  array<int, string>  $headings
     * @param  array<int, array<int, string>>  $rows
     */
    protected function buildPdfHtml(
        string $title,
        array $headings,
        array $rows,
        array $branding
    ): string {
        $safeTitle = e($title);
        $safeApp = e((string) ($branding['app_title'] ?? 'HIVE.OS'));
        $safeFooter = e((string) ($branding['footer_text'] ?? 'Powered by HIVE.OS'));
        $safeTax = e((string) ($branding['company_tax_id'] ?? ''));
        $headerColor = e((string) ($branding['document_header_color'] ?? '#1E293B'));
        $logoUrl = !empty($branding['logo_url']) ? e((string) $branding['logo_url']) : null;

        $headerCells = collect($headings)
            ->map(fn (string $heading): string => '<th>' . e($heading) . '</th>')
            ->implode('');

        $rowMarkup = collect($rows)
            ->map(function (array $row): string {
                $cells = collect($row)
                    ->map(fn ($value): string => '<td>' . e((string) $value) . '</td>')
                    ->implode('');

                return "<tr>{$cells}</tr>";
            })
            ->implode('');

        return <<<HTML
<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8" />
    <style>
      body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #334155; }
      .header { width: 100%; border-bottom: 2px solid {$headerColor}; margin-bottom: 14px; padding-bottom: 10px; }
      .header td { vertical-align: middle; }
      .logo { height: 36px; max-width: 140px; }
      .title { font-size: 14px; font-weight: bold; color: {$headerColor}; margin: 0 0 4px 0; text-transform: uppercase; }
      .meta { font-size: 9px; color: #64748b; text-align: right; }
      .data { width: 100%; border-collapse: collapse; }
      .data th { background: {$headerColor}; color: #fff; padding: 8px; text-align: left; border: 1px solid {$headerColor}; }
      .data td { border: 1px solid #e2e8f0; padding: 7px; }
      .data tbody tr:nth-child(even) { background: #f8fafc; }
      .footer { margin-top: 16px; border-top: 1px solid #e2e8f0; padding-top: 8px; color: #94a3b8; font-size: 8px; }
    </style>
  </head>
  <body>
    <table class="header">
      <tr>
        <td style="width: 25%;">{$this->renderLogoCell($logoUrl)}</td>
        <td style="width: 45%;">
          <p class="title">{$safeTitle}</p>
          <div>{$safeApp}</div>
        </td>
        <td class="meta" style="width: 30%;">
          <div><strong>Generated:</strong> {$this->escapeForPdf(now()->format('Y-m-d H:i:s'))}</div>
          <div><strong>Rows:</strong> {$this->escapeForPdf((string) count($rows))}</div>
          <div>{$safeTax}</div>
        </td>
      </tr>
    </table>
    <table class="data">
      <thead><tr>{$headerCells}</tr></thead>
      <tbody>{$rowMarkup}</tbody>
    </table>
    <div class="footer">{$safeFooter}</div>
  </body>
</html>
HTML;
    }

    protected function renderLogoCell(?string $logoUrl): string
    {
        if (!$logoUrl) {
            return '';
        }

        return '<img src="' . $logoUrl . '" alt="Logo" class="logo" />';
    }

    protected function escapeForPdf(string $value): string
    {
        return e($value);
    }
}

