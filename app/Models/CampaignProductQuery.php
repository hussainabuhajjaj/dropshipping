<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignProductQuery extends Model
{
    protected $fillable = [
        'storefront_campaign_id',
        'keywords',
        'cj_category_id',
        'min_price',
        'max_price',
        'max_products',
        'margin_percent',
        'auto_activate',
        'sort_by',
        'status',
        'error_message',
        'sourced_at',
    ];

    protected $casts = [
        'min_price' => 'decimal:2',
        'max_price' => 'decimal:2',
        'max_products' => 'integer',
        'margin_percent' => 'integer',
        'auto_activate' => 'boolean',
        'sourced_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(StorefrontCampaign::class, 'storefront_campaign_id');
    }

    public function markAsSourced(): void
    {
        $this->update(['status' => 'completed', 'sourced_at' => now()]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update(['status' => 'failed', 'error_message' => $error]);
    }
}
