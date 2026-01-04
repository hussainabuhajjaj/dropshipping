<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Fulfillment\Models\FulfillmentProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'default_fulfillment_provider_id',
        'support_email',
        'support_whatsapp',
        'support_phone',
        'support_hours',
        'site_name',
        'site_description',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'logo_path',
        'favicon_path',
        'timezone',
        'primary_color',
        'secondary_color',
        'accent_color',
        'delivery_window',
        'shipping_message',
        'customs_message',
        'tax_label',
        'tax_rate',
        'tax_included',
        'shipping_handling_fee',
        'free_shipping_threshold',
        'shipping_policy',
        'refund_policy',
        'privacy_policy',
        'terms_of_service',
        'customs_disclaimer',
        'about_page_html',
        'auto_approve_reviews',
        'auto_approve_review_days',
        'cj_last_sync_at',
        'cj_last_sync_summary',
        'cj_auto_approve_delay_hours',
    ];

    protected $casts = [
        'auto_approve_reviews' => 'boolean',
        'auto_approve_review_days' => 'integer',
        'tax_rate' => 'float',
        'tax_included' => 'boolean',
        'shipping_handling_fee' => 'float',
        'free_shipping_threshold' => 'float',
        'cj_last_sync_at' => 'datetime',
        'support_hours' => 'string',
        'about_page_html' => 'string',
        'logo_path' => 'array',
    ];

    // Accessor/mutator for CJ auto-approve delay (hours)
    public function getCjAutoApproveDelayHoursAttribute(): int
    {
        return (int) ($this->attributes['cj_auto_approve_delay_hours'] ?? 24);
    }

    public function setCjAutoApproveDelayHoursAttribute($value): void
    {
        $this->attributes['cj_auto_approve_delay_hours'] = (int) $value;
    }

    public function defaultFulfillmentProvider(): BelongsTo
    {
        return $this->belongsTo(FulfillmentProvider::class, 'default_fulfillment_provider_id');
    }
}
