<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Inventory\Models\InventoryDocument;
use Modules\Inventory\Models\InventoryEntityRecord;
use Modules\Inventory\Models\InventoryTransaction;

class InventoryReportController extends Controller
{
    public function index(Request $request)
    {
        $documentSummary = InventoryDocument::query()
            ->selectRaw('type, status, COUNT(*) as total')
            ->groupBy('type', 'status')
            ->orderBy('type')
            ->get();

        $entitySummary = InventoryEntityRecord::query()
            ->selectRaw('entity_type, COUNT(*) as total')
            ->groupBy('entity_type')
            ->orderBy('entity_type')
            ->get();

        $transactionSummary = InventoryTransaction::query()
            ->selectRaw('type, direction, COUNT(*) as total, COALESCE(SUM(quantity),0) as quantity_total')
            ->groupBy('type', 'direction')
            ->orderBy('type')
            ->get();

        return response()->json([
            'documents' => $documentSummary,
            'entities' => $entitySummary,
            'transactions' => $transactionSummary,
        ]);
    }

    public function show(string $report)
    {
        if ($report === 'documents') {
            return response()->json(
                InventoryDocument::query()
                    ->with(['items.inventoryItem:id,sku,name,unit', 'createdBy:id,name,email', 'approvedBy:id,name,email'])
                    ->latest()
                    ->paginate(100)
            );
        }

        if ($report === 'stock-ledger') {
            return response()->json(
                InventoryTransaction::query()
                    ->with(['item:id,sku,name,unit', 'performedBy:id,name,email'])
                    ->latest()
                    ->paginate(100)
            );
        }

        if ($report === 'entities') {
            return response()->json(
                InventoryEntityRecord::query()
                    ->with(['createdBy:id,name,email', 'updatedBy:id,name,email'])
                    ->latest()
                    ->paginate(100)
            );
        }

        return response()->json([
            'message' => "Unknown report '{$report}'. Supported reports: documents, stock-ledger, entities.",
        ], 404);
    }
}

