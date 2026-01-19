<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrderResource\Pages;

use App\Domain\Orders\Models\Order;
use App\Events\Orders\RefundProcessed;
use App\Enums\RefundReasonEnum;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Event;

class ProcessRefund extends Page implements HasForms
{
    use InteractsWithForms;
    use InteractsWithRecord;

    protected static string $resource = \App\Filament\Resources\OrderResource::class;

    protected string $view = 'filament.pages.process-refund';

    protected ?string $heading = 'Process Refund';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Process Refund';

    protected Width|string|null $maxContentWidth = Width::FiveExtraLarge;

    public ?array $data = [];

    public function mount(Order $record): void
    {
        $this->record = $record;

        if (! $record->canBeRefunded()) {
            Notification::make()
                ->danger()
                ->title('Cannot refund this order')
                ->body('This order cannot be refunded. It may already be refunded or in a non-refundable state.')
                ->send();

            $this->redirect(route('filament.admin.resources.orders.index'));

            return;
        }

        $this->form->fill([
            'refund_reason' => null,
            'refund_notes' => '',
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Order Details')
                    ->columns(3)
                    ->disabled()
                    ->schema([
                        \Filament\Forms\Components\TextInput::make('order_number')
                            ->label('Order Number')
                            ->default($this->record->order_number)
                            ->readonly(),

                        \Filament\Forms\Components\TextInput::make('customer_email')
                            ->label('Customer Email')
                            ->default($this->record->customer?->email)
                            ->readonly(),

                        \Filament\Forms\Components\TextInput::make('total')
                            ->label('Order Total')
                            ->default('$' . number_format($this->record->total / 100, 2))
                            ->readonly(),
                    ]),

                Section::make('Refund Decision')
                    ->columns(2)
                    ->schema([
                        Select::make('refund_reason')
                            ->label('Refund Reason')
                            ->required()
                            ->options(collect(RefundReasonEnum::cases())
                                ->mapWithKeys(fn (RefundReasonEnum $reason) => [
                                    $reason->value => "{$reason->label()} - {$reason->description()}",
                                ])
                                ->toArray())
                            ->reactive()
                            ->helperText('Select the reason for this refund')
                            ->columnSpan('full'),

                        \Filament\Forms\Components\TextInput::make('refund_percentage')
                            ->label('Refund Percentage')
                            ->disabled()
                            ->default('100%')
                            ->helperText('Auto-calculated based on refund reason')
                            ->reactive(),

                        \Filament\Forms\Components\TextInput::make('refund_amount')
                            ->label('Refund Amount')
                            ->disabled()
                            ->default(function () {
                                $reason = $this->data['refund_reason'] ?? null;
                                if (! $reason) {
                                    return '$0.00';
                                }

                                try {
                                    $enum = RefundReasonEnum::from($reason);
                                    $percentage = $enum->refundPercentage();
                                    $amount = (int) ($this->record->total * $percentage / 100);

                                    return '$' . number_format($amount / 100, 2);
                                } catch (\Throwable $e) {
                                    return '$0.00';
                                }
                            })
                            ->reactive()
                            ->helperText('Total refund amount to be credited'),

                        Textarea::make('refund_notes')
                            ->label('Admin Notes')
                            ->placeholder('Add any notes about this refund...')
                            ->rows(4)
                            ->columnSpan('full'),
                    ]),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        if (! $data['refund_reason']) {
            Notification::make()
                ->danger()
                ->title('Validation Error')
                ->body('Please select a refund reason.')
                ->send();

            return;
        }

        try {
            $reason = RefundReasonEnum::from($data['refund_reason']);
            $percentage = $reason->refundPercentage();
            $refundAmount = (int) ($this->record->total * $percentage / 100);

            $this->record->markRefunded(
                $reason,
                $refundAmount,
                $data['refund_notes'] ?? ''
            );

            Event::dispatch(new RefundProcessed($this->record, (float) $refundAmount, $this->record->currency, $data['refund_notes'] ?? null));

            Notification::make()
                ->success()
                ->title('Refund Processed')
                ->body("Refunded \${refundAmount} ({$percentage}%) to customer.")
                ->send();

            $this->redirect(route('filament.admin.resources.orders.view', $this->record));
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Refund Failed')
                ->body('Error: ' . $e->getMessage())
                ->send();
        }
    }
}
