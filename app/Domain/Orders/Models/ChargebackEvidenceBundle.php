<?php

namespace App\Domain\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChargebackEvidenceBundle extends Model
{
    protected $table = 'chargeback_evidence_bundles';

    protected $fillable = [
        'chargeback_case_id',
        'format',
        'file_path',
        'summary',
        'submitted_to_issuer_at',
        'created_by',
    ];

    protected $casts = [
        'submitted_to_issuer_at' => 'datetime',
    ];

    public function chargebackCase(): BelongsTo
    {
        return $this->belongsTo(ChargebackCase::class, 'chargeback_case_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function isSubmitted(): bool
    {
        return !is_null($this->submitted_to_issuer_at);
    }
}
