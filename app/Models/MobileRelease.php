<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MobileRelease extends Model
{
    protected $fillable = [
        'version',
        'platform',
        'file_path',
        'disk',
        'original_name',
        'file_size',
        'release_notes',
        'checksum',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'file_size' => 'integer',
    ];

    public function url(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        return Storage::disk($this->disk)->url($this->file_path);
    }

    public function markInactive(): void
    {
        $this->update(['is_active' => false]);
    }
}
