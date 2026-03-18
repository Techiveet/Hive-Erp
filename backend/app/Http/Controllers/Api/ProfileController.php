<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ProfileController extends Controller
{
    /**
     * Update the authenticated user's profile information.
     */
   public function updateProfile(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'avatar_path' => 'nullable|string|max:2048',
        ]);

        try {
            // Force manual assignment to bypass any $fillable issues
            $user->name = $validated['name'];
            $user->email = $validated['email'];
            if ($request->has('avatar_path')) {
                $user->avatar_path = $validated['avatar_path'];
            }
            $user->save();

            return response()->json(['message' => 'Identity Synced.', 'user' => $user->fresh()], 200);
        } catch (\Exception $e) {
            Log::error('Profile Update Error: ' . $e->getMessage());
            return response()->json(['message' => 'Sync Failed.'], 500);
        }
    }

    public function getAvatar(Request $request)
    {
        $user = $request->user();
        if (empty($user->avatar_path)) return response()->json(['message' => 'No avatar set.'], 404);

        $dbPath = $user->avatar_path;

        // Strategy 1: Smart ID Extraction (Handles "32/file.jpg" OR "/storage/32/file.jpg")
        $mediaId = null;
        if (preg_match('/(\d+)\//', $dbPath, $matches)) {
            $mediaId = $matches[1];
        }

        if ($mediaId) {
            $media = Media::find($mediaId);
            if ($media && file_exists($media->getPath())) {
                return response()->file($media->getPath(), ['Content-Type' => $media->mime_type]);
            }
        }

        // Strategy 2: Absolute Global Fallback
        $relativePath = ltrim(str_replace('/storage/', '', $dbPath), '/');
        $fullPath = base_path('storage/app/public/' . $relativePath);

        if (file_exists($fullPath)) {
            return response()->file($fullPath, ['Content-Type' => mime_content_type($fullPath)]);
        }

        return response()->json(['message' => 'File not found.', 'path' => $fullPath], 404);
    }
    /**
     * Helper function to return the file response
     */
    private function streamFile($path, $mime = null)
    {
        if (!$mime) {
            $mime = mime_content_type($path);
        }

        // Failsafe in case mime type isn't detected properly
        if (!$mime || !str_starts_with($mime, 'image/')) {
            $mime = 'image/png';
        }

        return response()->file($path, [
            'Content-Type' => $mime,
            'Cache-Control' => 'max-age=86400, public', // Cache in browser for 24 hours
            'Access-Control-Allow-Origin' => '*',
        ]);
    }
}
