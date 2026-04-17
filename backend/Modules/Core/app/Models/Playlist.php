<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Playlist extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        "user_id",
        "name",
        "description"
    ];

    /**
     * The files belonging to the playlist.
     */
    public function fileEntries(): BelongsToMany
    {
        return $this->belongsToMany(FileEntry::class, "playlist_file_entry")
                    ->withPivot("sort_order")
                    ->withTimestamps();
    }
}
