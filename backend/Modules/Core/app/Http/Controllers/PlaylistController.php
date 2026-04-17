<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Core\Models\Playlist;
use Modules\Core\Models\FileEntry;

class PlaylistController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(Playlist::where('user_id', auth()->id())->with('fileEntries')->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string'
        ]);

        $playlist = Playlist::create([
            'user_id' => auth()->id(),
            'name' => $request->name,
            'description' => $request->description ?? '',
        ]);

        return response()->json($playlist, 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $playlist = Playlist::where('user_id', auth()->id())->findOrFail($id);
        $request->validate([
            'name' => 'string|max:255',
            'description' => 'nullable|string'
        ]);

        $playlist->update($request->only(['name', 'description']));
        return response()->json($playlist);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $playlist = Playlist::where('user_id', auth()->id())->findOrFail($id);
        $playlist->delete();
        return response()->json(['message' => 'Playlist deleted']);
    }

    public function addTrack(Request $request, $id)
    {
        $playlist = Playlist::where('user_id', auth()->id())->findOrFail($id);
        $request->validate([
            'file_id' => 'required|exists:file_entries,id'
        ]);

        $playlist->fileEntries()->syncWithoutDetaching([$request->file_id]);
        return response()->json(['message' => 'Track added to playlist']);
    }

    /**
     * Remove a track from a playlist.
     */
    public function removeTrack(Request $request, $id)
    {
        $playlist = Playlist::where('user_id', auth()->id())->findOrFail($id);
        $request->validate([
            'file_id' => 'required|exists:file_entries,id'
        ]);

        $playlist->fileEntries()->detach($request->file_id);
        return response()->json(['message' => 'Track removed from playlist']);
    }
}
