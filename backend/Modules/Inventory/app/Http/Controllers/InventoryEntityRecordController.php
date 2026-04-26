<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Inventory\Models\InventoryEntityLog;
use Modules\Inventory\Models\InventoryEntityRecord;
use Modules\Inventory\Support\InventoryEntityCatalog;

class InventoryEntityRecordController extends Controller
{
    public function index(Request $request, string $resource)
    {
        $entityType = $this->resolveEntityType($resource);

        $query = InventoryEntityRecord::query()
            ->where('entity_type', $entityType)
            ->with(['parent:id,name,code', 'createdBy:id,name,email,avatar_path', 'updatedBy:id,name,email,avatar_path']);

        if ($request->filled('search')) {
            $term = '%' . trim((string) $request->input('search')) . '%';
            $query->where(function ($builder) use ($term): void {
                $builder
                    ->where('name', 'like', $term)
                    ->orWhere('code', 'like', $term);
            });
        }

        if ($request->filled('parent_id')) {
            $query->where('parent_id', (int) $request->input('parent_id'));
        }

        if ($request->boolean('active_only', false)) {
            $query->where('is_active', true);
        }

        $sortableColumns = ['created_at', 'updated_at', 'name', 'code', 'is_active'];
        $sortCol = (string) ($request->input('sortCol') ?? $request->input('sort_col') ?? 'name');
        if (!in_array($sortCol, $sortableColumns, true)) {
            $sortCol = 'name';
        }
        $sortDir = strtolower((string) ($request->input('sortDir') ?? $request->input('sort_dir') ?? 'asc')) === 'desc'
            ? 'desc'
            : 'asc';

        return response()->json(
            $query->orderBy($sortCol, $sortDir)
                ->paginate((int) $request->integer('per_page', 100))
        );
    }

    public function store(Request $request, string $resource)
    {
        $entityType = $this->resolveEntityType($resource);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:120'],
            'parent_id' => ['nullable', 'exists:central.inventory_entity_records,id'],
            'is_active' => ['sometimes', 'boolean'],
            'payload' => ['nullable', 'array'],
        ]);

        $record = InventoryEntityRecord::query()->create([
            'entity_type' => $entityType,
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'parent_id' => $validated['parent_id'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'payload' => (array) ($validated['payload'] ?? []),
            'created_by_id' => auth()->id(),
            'updated_by_id' => auth()->id(),
        ]);

        return response()->json(
            $record->load(['parent:id,name,code', 'createdBy:id,name,email,avatar_path', 'updatedBy:id,name,email,avatar_path']),
            201
        );
    }

    public function show($resource, $id = null)
    {
        // Parameter normalization: ensure $id is numeric and $resource is the name
        if (!is_numeric($id) && is_numeric($resource)) {
            $tmp = $id;
            $id = $resource;
            $resource = $tmp ?? request()->route('resource');
        }

        if ($id === null && is_numeric($resource)) {
            $id = $resource;
            $resource = request()->route('resource') ?? 'warehouses';
        }

        $entityType = $this->resolveEntityType((string) $resource);
        $id = (int) $id;

        return response()->json(
            InventoryEntityRecord::query()
                ->where('entity_type', $entityType)
                ->with([
                    'parent:id,name,code',
                    'children:id,entity_type,name,code,is_active,parent_id',
                    'logs.createdBy:id,name,email,avatar_path',
                    'createdBy:id,name,email,avatar_path',
                    'updatedBy:id,name,email,avatar_path',
                ])
                ->findOrFail($id)
        );
    }

    public function update(Request $request, $resource, $id = null)
    {
        // 1. Super-Smart Parameter Recovery
        if ($id === null && is_numeric($resource)) {
            $id = $resource;
            $resource = null;
        }

        // Search route parameters for a valid resource name if we don't have one
        if ($resource === null || is_numeric($resource)) {
            foreach ($request->route()->parameters() as $value) {
                if (is_string($value) && InventoryEntityCatalog::entityTypeFor($value)) {
                    $resource = $value;
                    break;
                }
            }
        }

        // If still numeric/null, look at URL segments
        if ($resource === null || is_numeric($resource)) {
             $segments = $request->segments();
             foreach ($segments as $segment) {
                 if (InventoryEntityCatalog::entityTypeFor($segment)) {
                     $resource = $segment;
                     break;
                 }
             }
        }

        // Final swap check
        if (!is_numeric($id) && is_numeric($resource)) {
            $tmp = $id;
            $id = $resource;
            $resource = $tmp;
        }

        $resource = $resource ?? 'warehouses'; // Fallback
        $entityType = $this->resolveEntityType((string) $resource);
        $id = (int) $id;
        $record = InventoryEntityRecord::query()
            ->where('entity_type', $entityType)
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:120'],
            'parent_id' => ['sometimes', 'nullable', 'exists:central.inventory_entity_records,id'],
            'is_active' => ['sometimes', 'boolean'],
            'payload' => ['sometimes', 'nullable', 'array'],
        ]);

        $record->update([
            ...$validated,
            'updated_by_id' => auth()->id(),
        ]);

        return response()->json(
            $record->fresh()->load(['parent:id,name,code', 'createdBy:id,name,email,avatar_path', 'updatedBy:id,name,email,avatar_path'])
        );
    }

    public function destroy($resource, $id = null)
    {
        // Parameter normalization
        if (!is_numeric($id) && is_numeric($resource)) {
            $tmp = $id;
            $id = $resource;
            $resource = $tmp ?? request()->route('resource');
        }

        if ($id === null && is_numeric($resource)) {
            $id = $resource;
            $resource = request()->route('resource') ?? 'warehouses';
        }

        $entityType = $this->resolveEntityType((string) $resource);
        $id = (int) $id;

        $record = InventoryEntityRecord::query()
            ->where('entity_type', $entityType)
            ->findOrFail($id);

        $record->delete();
        return response()->json(null, 204);
    }

    public function deactivate(string $resource, $id)
    {
        $entityType = $this->resolveEntityType($resource);
        $record = InventoryEntityRecord::query()
            ->where('entity_type', $entityType)
            ->findOrFail($id);

        $record->update([
            'is_active' => false,
            'updated_by_id' => auth()->id(),
        ]);

        return response()->json(
            $record->fresh()->load(['parent:id,name,code', 'createdBy:id,name,email,avatar_path', 'updatedBy:id,name,email,avatar_path'])
        );
    }

    public function attachRelation(Request $request, string $resource, $id, string $relation)
    {
        $entityType = $this->resolveEntityType($resource);
        $record = InventoryEntityRecord::query()
            ->where('entity_type', $entityType)
            ->findOrFail($id);

        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'integer', 'min:1'],
        ]);

        $payload = is_array($record->payload) ? $record->payload : [];
        $payload[$relation] = array_values(array_unique(array_map('intval', $validated['ids'])));

        $record->update([
            'payload' => $payload,
            'updated_by_id' => auth()->id(),
        ]);

        return response()->json($record->fresh());
    }

    public function assignShelfBox(Request $request, $id)
    {
        $record = InventoryEntityRecord::query()
            ->where('entity_type', 'shelf_boxes')
            ->findOrFail($id);

        $validated = $request->validate([
            'storable_type' => ['required', 'string', 'max:120'],
            'storable_id' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $payload = is_array($record->payload) ? $record->payload : [];
        $payload['assignment'] = [
            'storable_type' => $validated['storable_type'],
            'storable_id' => (int) $validated['storable_id'],
            'notes' => $validated['notes'] ?? null,
            'assigned_at' => now()->toIso8601String(),
            'assigned_by' => auth()->id(),
        ];

        $record->update([
            'payload' => $payload,
            'updated_by_id' => auth()->id(),
        ]);

        return response()->json($record->fresh());
    }

    public function optimizeRoute($id)
    {
        $record = InventoryEntityRecord::query()
            ->where('entity_type', 'routes')
            ->findOrFail($id);

        $payload = is_array($record->payload) ? $record->payload : [];
        $deliveryNotes = array_values($payload['delivery_notes'] ?? []);
        sort($deliveryNotes);

        $payload['delivery_notes'] = $deliveryNotes;
        $payload['optimized_at'] = now()->toIso8601String();
        $payload['optimized_by'] = auth()->id();

        $record->update([
            'payload' => $payload,
            'updated_by_id' => auth()->id(),
        ]);

        return response()->json([
            'route' => $record->fresh(),
            'message' => 'Route delivery order optimized using default deterministic sort.',
        ]);
    }

    public function appendLog(Request $request, $resource, $id = null)
    {
        // Parameter normalization
        if (!is_numeric($id) && is_numeric($resource)) {
            $tmp = $id;
            $id = $resource;
            $resource = $tmp ?? $request->route('resource');
        }

        if ($id === null && is_numeric($resource)) {
            $id = $resource;
            $resource = $request->route('resource') ?? 'warehouses';
        }

        $entityType = $this->resolveEntityType((string) $resource);
        $id = (int) $id;

        $record = InventoryEntityRecord::query()
            ->where('entity_type', $entityType)
            ->findOrFail($id);

        $validated = $request->validate([
            'log_type' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:5000'],
            'payload' => ['nullable', 'array'],
        ]);

        $log = InventoryEntityLog::query()->create([
            'entity_record_id' => $record->id,
            'log_type' => $validated['log_type'] ?? 'note',
            'description' => $validated['description'] ?? null,
            'payload' => $validated['payload'] ?? null,
            'created_by_id' => auth()->id(),
        ]);

        return response()->json($log->load('createdBy:id,name,email,avatar_path'), 201);
    }

    public function logs(Request $request, $resource, $id = null)
    {
        // Parameter normalization
        if (!is_numeric($id) && is_numeric($resource)) {
            $tmp = $id;
            $id = $resource;
            $resource = $tmp ?? $request->route('resource');
        }

        if ($id === null && is_numeric($resource)) {
            $id = $resource;
            $resource = $request->route('resource') ?? 'warehouses';
        }

        $entityType = $this->resolveEntityType((string) $resource);
        $id = (int) $id;

        $record = InventoryEntityRecord::query()
            ->where('entity_type', $entityType)
            ->findOrFail($id);

        return response()->json(
            $record->logs()
                ->with('createdBy:id,name,email,avatar_path')
                ->latest()
                ->paginate((int) $request->integer('per_page', 100))
        );
    }

    protected function resolveEntityType(string $resource): string
    {
        // SELF-HEALING: If $resource is numeric, scan the request context
        if (is_numeric($resource)) {
            foreach (request()->route()->parameters() as $value) {
                if (is_string($value) && InventoryEntityCatalog::entityTypeFor($value)) {
                    $resource = $value;
                    break;
                }
            }
            
            if (is_numeric($resource)) {
                $segments = request()->segments();
                foreach ($segments as $segment) {
                    if (InventoryEntityCatalog::entityTypeFor($segment)) {
                        $resource = $segment;
                        break;
                    }
                }
            }
        }

        $entityType = InventoryEntityCatalog::entityTypeFor($resource);
        if ($entityType) {
            return $entityType;
        }

        throw ValidationException::withMessages([
            'resource' => "Unsupported inventory entity resource '{$resource}'.",
        ]);
    }
}
