<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderAuditLog;
use App\Domain\Observability\EventLogger;
use App\Domain\Payments\PaymentService;
use App\Events\Orders\RefundProcessed;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Infrastructure\Payments\Paystack\PaystackRefundService;
use App\Services\Api\ApiException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Js;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('copyShipping')
                ->label('Copy Shipping Info')
                ->color('gray')
                ->icon('heroicon-o-clipboard')
                ->alpineClickHandler(function (Order $record): string {
                    $text = implode(', ', array_filter([
                        $record->shippingAddress?->name,
                        $record->shippingAddress?->line1,
                        $record->shippingAddress?->line2,
                        $record->shippingAddress?->city,
                        $record->shippingAddress?->state,
                        $record->shippingAddress?->postal_code,
                        $record->shippingAddress?->country,
                        $record->shippingAddress?->phone,
                    ]));

                    return 'navigator.clipboard.writeText(' . Js::from($text)->toHtml() . ')';
                }),
            Actions\Action::make('overrideStatus')
                ->label('Manual Status Override')
                ->icon('heroicon-o-adjustments-vertical')
                ->color('warning')
                ->schema([
                    Select::make('status')->label('Order Status')->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'fulfilling' => 'Fulfilling',
                        'fulfilled' => 'Fulfilled',
                        'cancelled' => 'Cancelled',
                        'refunded' => 'Refunded',
                    ])->required(),
                    Select::make('payment_status')->label('Payment Status')->options([
                        'unpaid' => 'Unpaid',
                        'paid' => 'Paid',
                        'refunded' => 'Refunded',
                    ])->required(),
                    Textarea::make('note')->rows(3)->required(),
                ])
                ->action(function (Order $record, array $data): void {
                    $record->update([
                        'status' => $data['status'],
                        'payment_status' => $data['payment_status'],
                    ]);
                    OrderAuditLog::create([
                        'order_id' => $record->id,
                        'user_id' => auth()->id(),
                        'action' => 'order_override',
                        'note' => $data['note'],
                        'payload' => $data,
                    ]);
                    app(EventLogger::class)->order(
                        $record,
                        'override',
                        $data['status'],
                        $data['note'],
                        ['payment_status' => $data['payment_status']]
                    );
                }),
            Actions\Action::make('refund')
                ->label('Initiate Refund')
                ->icon('heroicon-o-banknotes')
                ->color('danger')
                ->schema([
                    TextInput::make('amount')->numeric()->required(),
                    TextInput::make('currency')->default(fn (Order $record) => $record->currency)->required(),
                    Textarea::make('reason')->rows(3)->required(),
                    Toggle::make('force')->label('Force after delivery')->default(false),
                ])
                ->action(function (Order $record, array $data): void {
                    $delivered = $record->orderItems()->whereHas('shipments', fn ($q) => $q->whereNotNull('delivered_at'))->exists();
                    if ($delivered && ! $data['force']) {
                        Notification::make()
                            ->title('Cannot refund after delivery without force.')
                            ->danger()
                            ->send();
                        return;
                    }
                    DB::transaction(function () use ($record, $data) {
                        $record->payments()->update(['status' => 'refunded']);
                        $record->update(['payment_status' => 'refunded', 'status' => 'refunded']);

                        OrderAuditLog::create([
                            'order_id' => $record->id,
                            'user_id' => auth()->id(),
                            'action' => 'refund_initiated',
                            'note' => $data['reason'],
                            'payload' => ['amount' => $data['amount'], 'force' => $data['force']],
                        ]);

                        app(EventLogger::class)->order(
                            $record,
                            'refund',
                            'refunded',
                            $data['reason'],
                            ['amount' => $data['amount'], 'force' => $data['force']]
                        );
                    });

                    Event::dispatch(new RefundProcessed($record, (float) $data['amount'], $data['currency'], $data['reason']));
                }),
            Actions\Action::make('processPaystackRefund')
                ->label('Process Paystack Refund')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->visible(function (Order $record) {
                    $payment = $record->payments()
                        ->where('provider', 'paystack')
                        ->where('status', 'paid')
                        ->first();
                    return $payment && ($payment->refunded_amount < $payment->amount);
                })
                ->requiresConfirmation()
                ->modalHeading('Process Paystack Refund')
                ->modalDescription('This will process a real refund through Paystack API.')
                ->schema([
                    TextInput::make('amount')
                        ->label('Refund Amount')
                        ->numeric()
                        ->required()
                        ->prefix(function (Order $record): string { return $record->currency; })
                        ->default(function (Order $record): float {
                            $payment = $record->payments()->where('provider', 'paystack')->where('status', 'paid')->first();
                            return $payment ? ($payment->amount - $payment->refunded_amount) : 0;
                        })
                        ->helperText(function (Order $record): string {
                            $payment = $record->payments()->where('provider', 'paystack')->where('status', 'paid')->first();
                            if ($payment) {
                                $available = $payment->amount - $payment->refunded_amount;
                                return "Available to refund: {$available} {$payment->currency}";
                            }
                            return '';
                        }),
                    Textarea::make('reason')
                        ->label('Refund Reason')
                        ->required()
                        ->rows(3)
                        ->placeholder('Customer request, damaged goods, etc.'),
                ])
                ->action(function (Order $record, array $data): void {
                    try {
                        $payment = $record->payments()
                            ->where('provider', 'paystack')
                            ->where('status', 'paid')
                            ->first();
                        
                        if (!$payment) {
                            throw new \RuntimeException('No Paystack payment found for this order.');
                        }

                        $refundService = app(PaystackRefundService::class);
                        $refundService->refund(
                            $payment,
                            (float) $data['amount'],
                            $data['reason'],
                            auth()->id()
                        );

                        OrderAuditLog::create([
                            'order_id' => $record->id,
                            'user_id' => auth()->id(),
                            'action' => 'paystack_refund_processed',
                            'note' => $data['reason'],
                            'payload' => [
                                'amount' => $data['amount'],
                                'payment_id' => $payment->id,
                            ],
                        ]);

                        app(EventLogger::class)->order(
                            $record,
                            'refund',
                            'refunded',
                            $data['reason'],
                            ['amount' => $data['amount'], 'provider' => 'paystack']
                        );

                        Notification::make()
                            ->success()
                            ->title('Refund Processed')
                            ->body("Paystack refund of {$data['amount']} {$payment->currency} processed successfully.")
                            ->send();
                            
                    } catch (\Exception $e) {
                        Notification::make()
                            ->danger()
                            ->title('Refund Failed')
                            ->body('Error: ' . $e->getMessage())
                            ->send();
                    }
                }),
            Actions\Action::make('markPaid')
                ->label('Mark as Paid')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (Order $record) => $record->payment_status !== 'paid')
                ->schema([
                    TextInput::make('provider_reference')
                        ->label('Provider reference')
                        ->required(),
                    Textarea::make('note')->rows(2)->required(),
                ])
                ->action(function (Order $record, array $data): void {
                    DB::transaction(function () use ($record, $data) {
                        $payment = $record->payments()->latest()->first();

                        if (! $payment) {
                            $payment = $record->payments()->create([
                                'provider' => 'manual',
                                'status' => 'pending',
                                'provider_reference' => $data['provider_reference'],
                                'amount' => $record->grand_total,
                                'currency' => $record->currency,
                                'meta' => ['type' => 'manual_capture'],
                            ]);
                        } else {
                            $payment->update([
                                'provider_reference' => $data['provider_reference'],
                                'provider' => $payment->provider ?: 'manual',
                            ]);
                        }

                        app(PaymentService::class)->markAsPaid($payment);

                        OrderAuditLog::create([
                            'order_id' => $record->id,
                            'user_id' => auth()->id(),
                            'action' => 'marked_paid',
                            'note' => $data['note'],
                            'payload' => ['provider_reference' => $data['provider_reference']],
                        ]);
                    });
                }),
            Actions\Action::make('cancel')
                ->label('Cancel Order')
                ->icon('heroicon-o-x-circle')
                ->color('gray')
                ->requiresConfirmation()
                ->schema([
                    Textarea::make('reason')->rows(3)->required(),
                ])
                ->action(function (Order $record, array $data): void {
                    DB::transaction(function () use ($record, $data) {
                        $record->orderItems()->update(['fulfillment_status' => 'cancelled']);
                        $record->update(['status' => 'cancelled']);

                        OrderAuditLog::create([
                            'order_id' => $record->id,
                            'user_id' => auth()->id(),
                            'action' => 'order_cancelled',
                            'note' => $data['reason'],
                            'payload' => [],
                        ]);

                        app(EventLogger::class)->order(
                            $record,
                            'cancelled',
                            'cancelled',
                            $data['reason'],
                            []
                        );
                    });
                }),
            Actions\Action::make('checkCjStock')
                ->label('Check CJ Stock')
                ->icon('heroicon-o-signal')
                ->color('gray')
                ->action(function (Order $record): void {
                    $client = app(CJDropshippingClient::class);
                    $lines = [];

                    foreach ($record->orderItems as $item) {
                        $vid = data_get($item->productVariant?->metadata, 'cj_vid') ?? data_get($item->meta, 'cj_vid');
                        $sku = $item->productVariant?->sku ?? $item->source_sku;
                        $pid = data_get($item->productVariant?->product?->attributes, 'cj_pid') ?? data_get($item->meta, 'cj_pid');

                        try {
                            if ($vid) {
                                $resp = $client->getStockByVid($vid);
                            } elseif ($sku) {
                                $resp = $client->getStockBySku($sku);
                            } elseif ($pid) {
                                $resp = $client->getStockByPid($pid);
                            } else {
                                $lines[] = "Item {$item->id}: no CJ identifier (pid/vid/sku)";
                                continue;
                            }

                            $total = $this->sumStorage($resp->data ?? null);
                            $lines[] = "Item {$item->id} ({$sku}): stock {$total}";
                        } catch (ApiException $e) {
                            $lines[] = "Item {$item->id}: CJ error {$e->getMessage()}";
                        } catch (\Throwable $e) {
                            $lines[] = "Item {$item->id}: error {$e->getMessage()}";
                        }
                    }

                    Notification::make()
                        ->title('CJ stock check')
                        ->body($lines ? implode("\n", $lines) : 'No items to check.')
                        ->send();
                }),
        ];
    }

    private function sumStorage(mixed $payload): int
    {
        $total = 0;

        $add = function ($value) use (&$total) {
            if (is_numeric($value)) {
                $total += (int) $value;
            }
        };

        if (is_numeric($payload)) {
            $add($payload);
            return $total;
        }

        if (is_array($payload)) {
            if (array_key_exists('storageNum', $payload)) {
                $add($payload['storageNum']);
            }

            foreach ($payload as $entry) {
                if (is_array($entry) && array_key_exists('storageNum', $entry)) {
                    $add($entry['storageNum']);
                } elseif (is_array($entry)) {
                    foreach ($entry as $deep) {
                        if (is_array($deep) && array_key_exists('storageNum', $deep)) {
                            $add($deep['storageNum']);
                        }
                    }
                }
            }
        }

        return $total;
    }
}
