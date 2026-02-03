<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FulfillmentAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'fulfillment_job_id',
        'attempt_no',
        'request_payload',
        'response_payload',
        'status',
        'error_message',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
    ];

    public function fulfillmentJob(): BelongsTo
    {
        return $this->belongsTo(FulfillmentJob::class, 'fulfillment_job_id');
    }
}
