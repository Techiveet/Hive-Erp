<?php

namespace Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Core\Support\OwnedMediaPathResolver;

class ProfileController extends Controller
{
    public function __construct(
        private readonly OwnedMediaPathResolver $ownedMediaPathResolver
    ) {
    }

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
            $user->name = $validated['name'];
            $user->email = $validated['email'];

            if ($request->has('avatar_path')) {
                $avatarPath = trim((string) ($validated['avatar_path'] ?? ''));

                if (
                    $avatarPath !== ''
                    && !$this->ownedMediaPathResolver->isOwnedMediaPath($avatarPath, (int) $user->id)
                ) {
                    return response()->json(['message' => 'Invalid avatar reference.'], 422);
                }

                $user->avatar_path = $avatarPath !== '' ? $avatarPath : null;
            }

            $user->save();

            return response()->json(['message' => 'Identity Synced.', 'user' => $user->fresh()], 200);
        } catch (\Exception $e) {
            Log::error('Profile Update Error: ' . $e->getMessage());

            return response()->json(['message' => 'Sync Failed.'], 500);
        }
    }

    /**
     * Mark the welcome tour as completed for the authenticated operator.
     */
    public function completeWelcomeTour(Request $request)
    {
        $user = $request->user();
        $user->has_completed_welcome_tour = true;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Onboarding sequence finalized.',
            'has_completed_welcome_tour' => true,
        ]);
    }

    public function getAvatar(Request $request)
    {
        $user = $request->user();

        if (empty($user->avatar_path)) {
            return response()->json(['message' => 'No avatar set.'], 404);
        }

        $media = $this->ownedMediaPathResolver->resolveOwnedMediaFromPath($user->avatar_path, (int) $user->id);

        if (!$media || !file_exists($media->getPath())) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        return response()->file($media->getPath(), ['Content-Type' => $media->mime_type]);
    }
}
