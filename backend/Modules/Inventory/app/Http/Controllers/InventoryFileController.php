<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InventoryFileController extends Controller
{
    public function preview(Request $request)
    {
        $relativePath = $this->resolvePath($request->query('path'));

        if (!Storage::disk('local')->exists($relativePath)) {
            return response()->json([
                'message' => 'File not found.',
            ], 404);
        }

        $mime = Storage::disk('local')->mimeType($relativePath) ?: 'application/octet-stream';
        $content = Storage::disk('local')->get($relativePath);

        return response($content, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . basename($relativePath) . '"',
        ]);
    }

    public function download(Request $request)
    {
        $relativePath = $this->resolvePath($request->query('path'));

        if (!Storage::disk('local')->exists($relativePath)) {
            return response()->json([
                'message' => 'File not found.',
            ], 404);
        }

        return Storage::disk('local')->download($relativePath, basename($relativePath));
    }

    protected function resolvePath(mixed $path): string
    {
        $value = trim((string) $path);
        $value = ltrim(str_replace('\\', '/', $value), '/');

        // Keep file access scoped to inventory storage area.
        if (!str_starts_with($value, 'inventory/')) {
            $value = 'inventory/' . $value;
        }

        return $value;
    }
}

