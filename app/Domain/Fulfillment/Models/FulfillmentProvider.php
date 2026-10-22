<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Models;

use App\Domain\Products\Models\SupplierProduct;
use App\Domain\Fulfillment\Models\SupplierMetric;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class FulfillmentProvider extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'code',
        'driver_class',
        'contact_info',
        'notes',
        'credentials',
        'settings',
        'is_active',
        'is_blacklisted',
        'retry_limit',
        'contact_email',
        'contact_phone',
        'website_url',
    ];

    protected $casts = [
        'credentials' => 'array',
        'settings' => 'array',
        'contact_info' => 'array',
        'is_active' => 'boolean',
        'is_blacklisted' => 'boolean',
    ];

    public function supplierProducts(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    public function fulfillmentJobs(): HasMany
    {
        return $this->hasMany(FulfillmentJob::class);
    }

    public function metrics(): HasOne
    {
        return $this->hasOne(SupplierMetric::class, 'fulfillment_provider_id');
    }
}
