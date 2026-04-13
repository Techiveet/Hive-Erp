<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Inventory\Models\InventoryDocument;
use Modules\Inventory\Support\InventoryBlueprint;
use Modules\Inventory\Support\InventoryDocumentWorkflowService;

class InventoryDocumentController extends Controller
{
    public function __construct(
        protected InventoryDocumentWorkflowService $workflow
    ) {
    }

    public function index(Request $request)
    {
        $query = InventoryDocument::query()
            ->with([
                'items.inventoryItem:id,sku,name,unit,current_stock',
                'assets',
                'createdBy:id,name,email',
                'approvedBy:id,name,email',
            ]);

        if ($request->filled('type')) {
            $query->where('type', (string) $request->input('type'));
        }

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

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', (string) $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', (string) $request->input('to'));
        }

        return response()->json(
            $query
                ->latest()
                ->paginate((int) $request->integer('per_page', 50))
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(InventoryBlueprint::allowedWorkflowTypes())],
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

        if (!empty($validated['status'])) {
            $workflow = InventoryBlueprint::workflowDefinition((string) $validated['type']);
            $states = is_array($workflow['states'] ?? null) ? $workflow['states'] : [];
            if ($states && !in_array($validated['status'], $states, true)) {
                throw ValidationException::withMessages([
                    'status' => "Status '{$validated['status']}' is not valid for document type '{$validated['type']}'.",
                ]);
            }
        }

        $document = $this->workflow->createDocument($validated, $validated['items']);
        return response()->json($document, 201);
    }

    public function show($id)
    {
        return response()->json(
            InventoryDocument::query()
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

    public function update(Request $request, $id)
    {
        $document = InventoryDocument::query()->findOrFail($id);

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

    public function action(Request $request, $id, $action)
    {
        $document = InventoryDocument::query()->findOrFail($id);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:5000'],
            'signature' => ['nullable', 'string'],
            'signature_label' => ['nullable', 'string', 'max:120'],
            'signature_metadata' => ['nullable', 'array'],
        ]);

        $updated = $this->workflow->transition($document, (string) $action, $validated);
        return response()->json($updated);
    }

    public function storeAsset(Request $request, $id)
    {
        $document = InventoryDocument::query()->findOrFail($id);

        $validated = $request->validate([
            'kind' => ['required', Rule::in(['signature', 'attachment', 'verification'])],
            'label' => ['nullable', 'string', 'max:120'],
            'path' => ['nullable', 'string', 'max:1024'],
            'signed_payload' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        $asset = $this->workflow->attachAsset($document, $validated);
        return response()->json($asset, 201);
    }
}
