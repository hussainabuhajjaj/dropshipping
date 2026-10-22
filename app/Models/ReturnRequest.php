<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnRequest extends Model
{
    protected $fillable = [
        'order_id',
        'order_item_id',
        'customer_id',
        'status',
        'reason',
        'notes',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'return_label_url',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }

    // Helper methods for state transitions
    public function approve(int $userId, ?string $labelUrl = null): bool
    {
        if ($this->status !== 'requested') {
            return false;
        }

        $result = $this->update([
            'status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => now(),
            'return_label_url' => $labelUrl,
        ]);

        if ($result) {
            event(new \App\Events\Orders\ReturnApproved($this, $labelUrl));
        }

        return $result;
    }

    public function reject(int $userId, string $reason): bool
    {
        if ($this->status !== 'requested') {
            return false;
        }

        $result = $this->update([
            'status' => 'rejected',
            'approved_by' => $userId,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);

        if ($result) {
            event(new \App\Events\Orders\ReturnRejected($this, $reason));
        }

        return $result;
    }

    public function canBeApproved(): bool
    {
        return $this->status === 'requested';
    }

    public function canBeRejected(): bool
    {
        return $this->status === 'requested';
    }
}
