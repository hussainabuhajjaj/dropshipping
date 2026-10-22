<?php

declare(strict_types=1);

namespace App\Filament\Resources\WhatsAppOrderIntentResource\Pages;

use App\Filament\Resources\WhatsAppOrderIntentResource;
use App\Models\WhatsAppOrderIntent;
use App\Services\Checkout\WhatsAppOrderIntentService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use RuntimeException;

class ViewWhatsAppOrderIntent extends ViewRecord
{
    protected static string $resource = WhatsAppOrderIntentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('expire')
                ->visible(fn (WhatsAppOrderIntent $record) => $record->status === WhatsAppOrderIntent::STATUS_PENDING)
                ->requiresConfirmation()
                ->action(function (WhatsAppOrderIntent $record): void {
                    $record->markExpired('manually_expired');

                    Notification::make()
                        ->title('Intent expired')
                        ->success()
                        ->send();
                }),
            Action::make('convert')
                ->visible(fn (WhatsAppOrderIntent $record) => $record->status === WhatsAppOrderIntent::STATUS_PENDING)
                ->schema([
                    TextInput::make('name')->required()->maxLength(120),
                    TextInput::make('email')->email()->required()->maxLength(190),
                    TextInput::make('phone')->tel()->required()->maxLength(30),
                    TextInput::make('line1')->label('Address line 1')->required()->maxLength(255),
                    TextInput::make('line2')->label('Address line 2')->maxLength(255),
                    TextInput::make('city')->required()->maxLength(120),
                    TextInput::make('state')->maxLength(120),
                    TextInput::make('postal_code')->maxLength(30),
                    TextInput::make('country')->default('CI')->required()->maxLength(2),
                    Textarea::make('delivery_notes')->rows(3),
                ])
                ->fillForm(fn (WhatsAppOrderIntent $record): array => [
                    'name' => $record->customer?->name ?? data_get($record->snapshot, 'customer.name'),
                    'email' => $record->customer?->email ?? data_get($record->snapshot, 'customer.email'),
                    'phone' => $record->phone ?: ($record->customer?->phone ?? data_get($record->snapshot, 'customer.phone')),
                    'country' => 'CI',
                ])
                ->action(function (WhatsAppOrderIntent $record, array $data): void {
                    try {
                        $order = app(WhatsAppOrderIntentService::class)->convert($record, $data, auth()->id());
                    } catch (RuntimeException $exception) {
                        Notification::make()
                            ->title('Conversion failed')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title("Order {$order->number} created")
                        ->success()
                        ->send();

                    $this->redirect(\App\Filament\Resources\OrderResource::getUrl('view', ['record' => $order]));
                }),
        ];
    }
}
