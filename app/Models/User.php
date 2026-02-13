<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Domain\Support\Models\SupportConversation;
use App\Domain\Support\Models\SupportMessage;
use App\Notifications\AdminResetPasswordNotification;
use CWSPS154\UsersRolesPermissions\Models\HasRole;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Filament\Support\Concerns\HasMediaFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens , Panel\Concerns\HasAvatars;

    protected static ?bool $hasIsActiveColumn = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'role',
        'two_factor_enabled',
        'two_factor_secret',
        'password',
        'role_id',
        'last_seen',
        'is_active'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',

    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_enabled' => 'boolean',
            'last_seen' => 'datetime',
            'is_active' => 'boolean',

        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return in_array($this->role, ['admin', 'staff'], true);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new AdminResetPasswordNotification($token));
    }

    public function assignedSupportConversations(): HasMany
    {
        return $this->hasMany(SupportConversation::class, 'assigned_user_id');
    }

    public function supportMessages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'sender_user_id');
    }

    public function scopeSupportAgents(Builder $query): Builder
    {
        $query->whereIn('role', ['admin', 'staff']);

        if (self::hasIsActiveColumn()) {
            $query->where('is_active', true);
        }

        return $query;
    }

    protected static function hasIsActiveColumn(): bool
    {
        if (self::$hasIsActiveColumn !== null) {
            return self::$hasIsActiveColumn;
        }

        try {
            self::$hasIsActiveColumn = Schema::hasColumn((new static)->getTable(), 'is_active');
        } catch (\Throwable) {
            self::$hasIsActiveColumn = false;
        }

        return self::$hasIsActiveColumn;
    }
}
