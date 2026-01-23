<?php

declare(strict_types=1);

namespace App\Domain\Products\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\CategoryTranslation;

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

    public function translations(): HasMany
    {
        return $this->hasMany(CategoryTranslation::class);
    }

    public function translationForLocale(?string $locale): ?CategoryTranslation
    {
        if (! $locale) {
            return null;
        }

        if ($this->relationLoaded('translations')) {
            return $this->translations->firstWhere('locale', $locale);
        }

        return $this->translations()->where('locale', $locale)->first();
    }

    public function translatedValue(string $field, ?string $locale): ?string
    {
        $value = $locale ? $this->translationForLocale($locale)?->{$field} : null;

        if ($value !== null && $value !== '') {
            return $value;
        }

        return $this->{$field} ?? null;
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
