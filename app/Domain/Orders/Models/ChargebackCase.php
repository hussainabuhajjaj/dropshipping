<?php

namespace App\Domain\Orders\Models;

use App\Enums\ChargebackStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChargebackCase extends Model
{
    protected $table = 'chargeback_cases';

    protected $fillable = [
        'order_id',
        'payment_reference',
        'case_number',
        'status',
        'reason_code',
        'reason_description',
        'amount',
        'card_last_four',
        'transaction_date',
        'chargeback_date',
        'due_date',
        'customer_statement',
        'merchant_response',
        'resolution_notes',
        'handled_by',
        'resolved_at',
    ];

    protected $casts = [
        'status' => ChargebackStatus::class,
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
        'chargeback_date' => 'date',
        'due_date' => 'date',
        'opened_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function handledByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'handled_by');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(ChargebackEvidence::class, 'chargeback_case_id');
    }

    public function evidenceBundles(): HasMany
    {
        return $this->hasMany(ChargebackEvidenceBundle::class, 'chargeback_case_id');
    }

    public function isResolved(): bool
    {
        return $this->status->isResolved();
    }

    public function daysUntilDue(): ?int
    {
        if (!$this->due_date) {
            return null;
        }

        $now = now()->startOfDay();
        $due = $this->due_date->startOfDay();

        if ($due < $now) {
            return -$due->diffInDays($now);
        }

        return $due->diffInDays($now);
    }

    public function isOverdue(): bool
    {
        return $this->daysUntilDue() !== null && $this->daysUntilDue() < 0;
    }

    public function getEvidenceCount(): int
    {
        return $this->evidence()->count();
    }

    public function getEvidenceByType(string $type): \Illuminate\Database\Eloquent\Collection
    {
        return $this->evidence()
            ->where('type', $type)
            ->orderByDesc('created_at')
            ->get();
    }

    public function getTotalEvidenceSize(): int
    {
        return $this->evidence()
            ->whereNotNull('file_size')
            ->sum('file_size');
    }
}
