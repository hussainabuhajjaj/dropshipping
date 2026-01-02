<?php

namespace App\Enums;

enum MessageChannel: string
{
    case EMAIL = 'email';
    case WHATSAPP = 'whatsapp';
    case SMS = 'sms';
    case MANUAL = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::EMAIL => 'Email',
            self::WHATSAPP => 'WhatsApp',
            self::SMS => 'SMS',
            self::MANUAL => 'Manual',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::EMAIL => 'heroicon-o-envelope',
            self::WHATSAPP => 'heroicon-o-chat-bubble-left-right',
            self::SMS => 'heroicon-o-phone',
            self::MANUAL => 'heroicon-o-hand-raised',
        };
    }

    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }
}
