<?php
 
namespace App\Domain\Affiliates\Models;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateCommission extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory;

    protected $fillable = [
        'affiliate_id',
        'order_id',
        'order_item_id',
        'commission_rate',
        'commission_amount',
        'status',
        'approved_at',
        'rejected_at',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:4',
        'commission_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
