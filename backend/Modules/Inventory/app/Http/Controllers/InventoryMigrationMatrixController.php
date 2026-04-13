<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Inventory\Support\InventoryBlueprint;

class InventoryMigrationMatrixController extends Controller
{
    public function __invoke()
    {
        return response()->json([
            'rows' => InventoryBlueprint::migrationMatrix(),
        ]);
    }
}

