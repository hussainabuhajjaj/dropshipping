<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id', 'provider', 'card_data_encrypted',
        'brand', 'last4', 'exp_month', 'exp_year', 'nickname',
        'is_default', 'meta',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'meta' => 'array',
    ];

    public function setCardDataAttribute($value)
    {
        $this->attributes['card_data_encrypted'] = Crypt::encryptString(json_encode($value));
    }

    // Decrypt card data when accessing
    public function getCardDataAttribute()
    {
        if (empty($this->card_data_encrypted)) {
            return null;
        }

        try {
            return json_decode(Crypt::decryptString($this->card_data_encrypted), true);
        } catch (\Exception $e) {
            return null;
        }
    }

    // Get masked card number
    public function getMaskedNumberAttribute()
    {
        $cardData = $this->card_data;
        if ($cardData && isset($cardData['number'])) {
            $last4 = substr($cardData['number'], -4);
            return '**** **** **** ' . $last4;
        }
        return '**** **** **** ' . $this->last4;
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function getFormattedExpiryAttribute(): string
    {
        if (!$this->exp_month || !$this->exp_year) {
            return '';
        }

        $month = str_pad($this->exp_month, 2, '0', STR_PAD_LEFT);
        $year = substr($this->exp_year, -2);

        return "{$month}/{$year}";
    }

}
