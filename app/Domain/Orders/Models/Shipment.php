<?php

declare(strict_types=1);

namespace App\Domain\Orders\Models;

use App\Enums\ShipmentExceptionCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_item_id',
        'cj_order_id',
        'shipment_order_id',
        'tracking_number',
        'carrier',
        'logistic_name',
        'tracking_url',
        'postage_amount',
        'currency',
        'shipped_at',
        'delivered_at',
        'raw_events',
        'exception_code',
        'exception_reason',
        'exception_at',
        'resolved_at',
        'admin_notes',
        'is_at_risk',
    ];

    protected $casts = [
        'raw_events' => 'array',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'exception_at' => 'datetime',
        'resolved_at' => 'datetime',
        'postage_amount' => 'decimal:2',
        'exception_code' => ShipmentExceptionCode::class,
        'is_at_risk' => 'boolean',
    ];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(TrackingEvent::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(ShipmentException::class);
    }

    public function latestException()
    {
        return $this->hasOne(ShipmentException::class)
            ->latestOfMany('occurred_at');
    }

    public function exceptionResolutions(): HasMany
    {
        return $this->hasMany(ShipmentExceptionResolution::class);
    }

    public function messageLogs(): HasMany
    {
        return $this->hasMany(\App\Domain\Messaging\Models\MessageLog::class);
    }

    // ==================== Helper Methods ====================

    public function hasException(): bool
    {
        return ! is_null($this->exception_code);
    }

    public function isResolved(): bool
    {
        return ! is_null($this->resolved_at);
    }

    public function isAtRisk(): bool
    {
        return $this->is_at_risk === true;
    }

    public function markAsAtRisk(string $reason = 'No tracking updates'): void
    {
        $this->update([
            'is_at_risk' => true,
            'exception_code' => ShipmentExceptionCode::TRACKING_NO_UPDATES,
            'exception_reason' => $reason,
            'exception_at' => now(),
        ]);

        // Create exception record
        $this->exceptions()->create([
            'exception_code' => ShipmentExceptionCode::TRACKING_NO_UPDATES,
            'exception_reason' => $reason,
            'occurred_at' => now(),
            'source' => 'system',
        ]);
    }

    public function markExceptionResolved(string $resolutionCode, string $adminNotes, ?\App\Models\User $user = null): void
    {
        $user = $user ?? auth()->user();

        $this->update([
            'resolved_at' => now(),
            'admin_notes' => $adminNotes,
            'is_at_risk' => false,
        ]);

        // Get latest exception
        $exception = $this->latestException;
        if ($exception) {
            $exception->resolutions()->create([
                'shipment_id' => $this->id,
                'resolution_code' => $resolutionCode,
                'admin_notes' => $adminNotes,
                'resolved_at' => now(),
                'resolved_by' => $user?->id,
            ]);
        }
    }

    public function scopeAtRisk($query)
    {
        return $query->where('is_at_risk', true);
    }

}

