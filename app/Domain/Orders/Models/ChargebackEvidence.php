<?php

namespace App\Domain\Orders\Models;

use App\Enums\ChargebackEvidenceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChargebackEvidence extends Model
{
    protected $table = 'chargeback_evidence';

    protected $fillable = [
        'chargeback_case_id',
        'type',
        'title',
        'description',
        'file_path',
        'file_mime_type',
        'file_size',
        'content',
        'url',
        'submitted_to_issuer_at',
        'uploaded_by',
    ];

    protected $casts = [
        'type' => ChargebackEvidenceType::class,
        'submitted_to_issuer_at' => 'datetime',
    ];

    public function chargebackCase(): BelongsTo
    {
        return $this->belongsTo(ChargebackCase::class, 'chargeback_case_id');
    }

    public function uploadedByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'uploaded_by');
    }

    public function isFile(): bool
    {
        return !is_null($this->file_path);
    }

    public function isText(): bool
    {
        return !is_null($this->content);
    }

    public function isUrl(): bool
    {
        return !is_null($this->url);
    }

    public function isSubmitted(): bool
    {
        return !is_null($this->submitted_to_issuer_at);
    }

    public function getFileSize(): string
    {
        if (!$this->file_size) {
            return 'N/A';
        }

        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= 1 << (10 * $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    public function getContent(): string
    {
        if ($this->isText()) {
            return $this->content;
        }

        if ($this->isUrl()) {
            return $this->url;
        }

        if ($this->isFile()) {
            return sprintf('[File: %s - %s]', basename($this->file_path), $this->getFileSize());
        }

        return '';
    }
}
