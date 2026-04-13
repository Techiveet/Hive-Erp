<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Inventory\Models\InventoryDocument;
use Modules\Inventory\Support\InventoryWorkflowAliasCatalog;

class InventoryPublicVerificationController extends Controller
{
    public function verify(Request $request, string $resource, $id)
    {
        $type = InventoryWorkflowAliasCatalog::documentTypeFor($resource);
        if (!$type) {
            return response()->json([
                'valid' => false,
                'message' => "Unsupported verification resource '{$resource}'.",
            ], 404);
        }

        $expected = hash_hmac('sha256', "{$resource}:{$id}", (string) config('app.key'));
        $provided = (string) $request->query('signature', '');

        if (!hash_equals($expected, $provided)) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid document signature.',
            ], 403);
        }

        $document = InventoryDocument::query()
            ->where('type', $type)
            ->with(['items.inventoryItem:id,sku,name,unit', 'createdBy:id,name,email', 'approvedBy:id,name,email'])
            ->findOrFail($id);

        return response()->json([
            'valid' => true,
            'resource' => $resource,
            'document' => $document,
        ]);
    }
}

