<?php

declare(strict_types=1);

namespace App\Domain\Common\Models;

use Database\Factories\AddressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class Address extends Model
{
    use HasFactory;
    use SoftDeletes {
        bootSoftDeletes as protected bootSoftDeletesTrait;
        initializeSoftDeletes as protected initializeSoftDeletesTrait;
        performDeleteOnModel as protected performDeleteOnModelTrait;
        restore as protected restoreTrait;
        trashed as protected trashedTrait;
    }

    protected static ?bool $softDeletesAvailable = null;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): AddressFactory
    {
        return AddressFactory::new();
    }

    protected $fillable = [
        'user_id',
        'customer_id',
        'name',
        'phone',
        'line1',
        'line2',
        'city',
        'state',
        'postal_code',
        'country',
        'type',
        'metadata',
        'is_default',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_default' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    protected static function bootSoftDeletes(): void
    {
        if (static::softDeletesAvailable()) {
            static::bootSoftDeletesTrait();
        }
    }

    protected function initializeSoftDeletes(): void
    {
        if (static::softDeletesAvailable()) {
            $this->initializeSoftDeletesTrait();
        }
    }

    public static function softDeletesAvailable(): bool
    {
        if (static::$softDeletesAvailable !== null) {
            return static::$softDeletesAvailable;
        }

        try {
            return static::$softDeletesAvailable = Schema::hasColumn((new static())->getTable(), 'deleted_at');
        } catch (\Throwable) {
            return static::$softDeletesAvailable = false;
        }
    }

    protected function performDeleteOnModel()
    {
        if (static::softDeletesAvailable()) {
            $this->performDeleteOnModelTrait();

            return;
        }

        $this->setKeysForSaveQuery($this->newModelQuery())->delete();
        $this->exists = false;
    }

    public function restore()
    {
        if (! static::softDeletesAvailable()) {
            return false;
        }

        return $this->restoreTrait();
    }

    public function trashed()
    {
        return static::softDeletesAvailable()
            ? $this->trashedTrait()
            : false;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Customer::class);
    }
}
