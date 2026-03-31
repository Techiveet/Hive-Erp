<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Core\Models\SystemAlert; // 🚀 FIXED: Points to the Module, not App

class SystemAlertController extends Controller
{
    public function index()
    {
        $isTenant = function_exists('tenant') && tenant('id');
        $query = SystemAlert::orderBy('created_at', 'desc');

        // Lock Tenants to their own alerts. Central sees EVERYTHING.
        if ($isTenant) {
            $query->where('tenant_id', tenant('id'));
        }

        $alerts = $query->get()->map(function($alert) {
            return [
                'id'          => $alert->id,
                'title'       => $alert->title,
                'description' => $alert->description,
                'level'       => $alert->level,
                'time_ago'    => $alert->created_at->diffForHumans()
            ];
        });

        return response()->json(['data' => $alerts]);
    }

    public function destroy($id)
    {
        $isTenant = function_exists('tenant') && tenant('id');
        $query = SystemAlert::where('id', $id);

        if ($isTenant) {
            $query->where('tenant_id', tenant('id'));
        }

        $alert = $query->firstOrFail();
        $alert->delete();

        return response()->json(['message' => 'Alert dismissed successfully.']);
    }

    public function clearAll()
    {
        $isTenant = function_exists('tenant') && tenant('id');
        $query = SystemAlert::query();

        if ($isTenant) {
            $query->where('tenant_id', tenant('id'));
        }

        $query->delete();

        return response()->json(['message' => 'All alerts cleared successfully.']);
    }
}