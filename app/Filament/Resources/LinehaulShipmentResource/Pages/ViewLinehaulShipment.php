<?php

declare(strict_types=1);

namespace App\Filament\Resources\LinehaulShipmentResource\Pages;

use App\Filament\Resources\LinehaulShipmentResource;
use App\Filament\Resources\OrderResource;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Domain\Orders\Models\LinehaulShipment;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Throwable;

class ViewLinehaulShipment extends ViewRecord
{
    protected static string $resource = LinehaulShipmentResource::class;

    protected function getHeaderActions(): array
    {
        /** @var LinehaulShipment|null $record */
        $record = $this->record;

        return [
            Action::make('viewOrder')
                ->label('View order')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url($record && $record->order_id
                    ? OrderResource::getUrl('view', ['record' => $record->order_id])
                    : null)
                ->openUrlInNewTab()
                ->visible((bool) $record?->order_id),
            Action::make('markDispatched')
                ->label('Mark dispatched')
                ->icon('heroicon-o-truck')
                ->requiresConfirmation()
                ->visible(! $record?->dispatched_at)
                ->action(function () use ($record): void {
                    if (! $record) {
                        return;
                    }
                    $record->update([
                        'dispatched_at' => $record->dispatched_at ?? now(),
                    ]);
                }),
            Action::make('markArrived')
                ->label('Mark arrived')
                ->icon('heroicon-o-check-badge')
                ->requiresConfirmation()
                ->visible(! $record?->arrived_at)
                ->action(function () use ($record): void {
                    if (! $record) {
                        return;
                    }
                    $record->update([
                        'dispatched_at' => $record->dispatched_at ?? now(),
                        'arrived_at' => $record->arrived_at ?? now(),
                    ]);
                }),
            Action::make('syncCjOrder')
                ->label('Sync CJ snapshot')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->visible((bool) $record?->order?->cj_order_id)
                ->action(function () use ($record): void {
                    if (! $record) {
                        return;
                    }

                    try {
                        $client = app(CJDropshippingClient::class);
                        $cjOrderId = $record->order?->cj_order_id;
                        $response = $client->getOrderList([
                            'pageNum' => 1,
                            'pageSize' => 1,
                            'orderIds' => [$cjOrderId],
                        ]);

                        $data = is_array($response->data) ? $response->data : [];
                        $cjOrder = $data['list'][0] ?? null;
                        if (! is_array($cjOrder)) {
                            Notification::make()
                                ->title('No CJ order found')
                                ->warning()
                                ->send();
                            return;
                        }

                        $record->applyCjOrder($cjOrder);
                        $record->save();

                        Notification::make()
                            ->title('CJ snapshot synced')
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('CJ sync failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
