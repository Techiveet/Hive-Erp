<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Inventory\Models\InventoryDocument;
use Modules\Inventory\Support\InventoryDocumentWorkflowService;
use Modules\Inventory\Support\InventoryWorkflowAliasCatalog;

class InventoryWorkflowAliasController extends Controller
{
    public function __construct(
        protected InventoryDocumentWorkflowService $workflow
    ) {
    }

    public function index(Request $request, string $resource)
    {
        $type = $this->resolveDocumentType($resource);

        $query = InventoryDocument::query()
            ->where('type', $type)
            ->with([
                'items.inventoryItem:id,sku,name,unit,current_stock',
                'assets',
                'createdBy:id,name,email',
                'approvedBy:id,name,email',
            ]);

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('search')) {
            $term = '%' . trim((string) $request->input('search')) . '%';
            $query->where(function ($builder) use ($term): void {
                $builder
                    ->where('document_number', 'like', $term)
                    ->orWhere('title', 'like', $term)
                    ->orWhere('notes', 'like', $term);
            });
        }

        return response()->json(
            $query
                ->latest()
                ->paginate((int) $request->integer('per_page', 50))
        );
    }

    public function store(Request $request, string $resource)
    {
        $type = $this->resolveDocumentType($resource);

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'max:80'],
            'document_number' => ['nullable', 'string', 'max:120'],
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'source_document_id' => ['nullable', 'exists:inventory_documents,id'],
            'workflow_meta' => ['nullable', 'array'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_item_id' => ['nullable', 'exists:inventory_items,id'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit' => ['nullable', 'string', 'max:60'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.total_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.metadata' => ['nullable', 'array'],
        ]);

        $document = $this->workflow->createDocument([
            ...$validated,
            'type' => $type,
        ], $validated['items']);

        return response()->json($document, 201);
    }

    public function show(string $resource, $id)
    {
        $type = $this->resolveDocumentType($resource);

        return response()->json(
            InventoryDocument::query()
                ->where('type', $type)
                ->with([
                    'sourceDocument:id,document_number,type,status',
                    'items.inventoryItem:id,sku,name,unit,current_stock',
                    'assets',
                    'createdBy:id,name,email',
                    'approvedBy:id,name,email',
                ])
                ->findOrFail($id)
        );
    }

    public function update(Request $request, string $resource, $id)
    {
        $type = $this->resolveDocumentType($resource);

        $document = InventoryDocument::query()
            ->where('type', $type)
            ->findOrFail($id);

        $validated = $request->validate([
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'source_document_id' => ['sometimes', 'nullable', 'exists:inventory_documents,id'],
            'workflow_meta' => ['sometimes', 'nullable', 'array'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.inventory_item_id' => ['nullable', 'exists:inventory_items,id'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'min:0.001'],
            'items.*.unit' => ['nullable', 'string', 'max:60'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.total_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.metadata' => ['nullable', 'array'],
        ]);

        $updated = $this->workflow->updateDocument(
            $document,
            $validated,
            is_array($validated['items'] ?? null) ? $validated['items'] : null
        );

        return response()->json($updated);
    }

    public function action(Request $request, string $resource, $id, string $action)
    {
        $type = $this->resolveDocumentType($resource);

        $document = InventoryDocument::query()
            ->where('type', $type)
            ->findOrFail($id);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:5000'],
            'signature' => ['nullable', 'string'],
            'signature_label' => ['nullable', 'string', 'max:120'],
            'signature_metadata' => ['nullable', 'array'],
        ]);

        $updated = $this->workflow->transition($document, $action, $validated);
        return response()->json($updated);
    }

    public function pdf(string $resource, $id)
    {
        $type = $this->resolveDocumentType($resource);

        $document = InventoryDocument::query()
            ->where('type', $type)
            ->with(['items.inventoryItem:id,sku,name,unit', 'createdBy:id,name,email', 'approvedBy:id,name,email'])
            ->findOrFail($id);

        $signature = hash_hmac('sha256', "{$resource}:{$document->id}", (string) config('app.key'));

        return response()->json([
            'document' => $document,
            'verify_url' => url("/api/public/inventory/verify/{$resource}/{$document->id}?signature={$signature}"),
            'message' => 'Document payload generated. Attach a PDF renderer service if you need binary output.',
        ]);
    }

    public function assignDeliveryRoutes(Request $request, $id)
    {
        $document = InventoryDocument::query()
            ->where('type', 'delivery_note')
            ->findOrFail($id);

        $validated = $request->validate([
            'route_ids' => ['required', 'array'],
            'route_ids.*' => ['required', 'integer', 'min:1'],
        ]);

        $meta = is_array($document->workflow_meta) ? $document->workflow_meta : [];
        $meta['route_ids'] = array_values(array_unique(array_map('intval', $validated['route_ids'])));
        $meta['route_assigned_at'] = now()->toIso8601String();
        $meta['route_assigned_by'] = auth()->id();

        $document->update([
            'workflow_meta' => $meta,
        ]);

        return response()->json($document->fresh());
    }

    protected function resolveDocumentType(string $resource): string
    {
        $type = InventoryWorkflowAliasCatalog::documentTypeFor($resource);
        if ($type) {
            return $type;
        }

        throw ValidationException::withMessages([
            'resource' => "Unsupported workflow resource '{$resource}'.",
        ]);
    }
}
