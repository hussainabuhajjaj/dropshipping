<?php

declare(strict_types=1);

namespace App\Domain\Products\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'cj_id',
        'cj_payload',
        'is_active',
        'view_count',
        'name',
        'slug',
        'description',
        'hero_title',
        'hero_subtitle',
        'hero_image',
        'hero_cta_label',
        'hero_cta_link',
        'meta_title',
        'meta_description',
        'parent_id',
    ];

    protected $casts = [
        'cj_payload' => 'array',
        'is_active' => 'boolean',
        'view_count' => 'int',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }
       public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
