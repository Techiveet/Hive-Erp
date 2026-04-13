<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Inventory\Support\InventoryBlueprint;

class InventoryBlueprintController extends Controller
{
    public function __invoke()
    {
        return response()->json([
            'domains' => InventoryBlueprint::domains(),
            'workflows' => InventoryBlueprint::workflows(),
            'movement_types' => InventoryBlueprint::movementTypes(),
            'permission_matrix' => InventoryBlueprint::permissionMatrix(),
            'build_checklist' => InventoryBlueprint::buildChecklist(),
        ]);
    }
}

