<?php

namespace App\Livewire;

use Filament\Tables\Table;
use Livewire\Component;
use Illuminate\Contracts\View\View;

use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;

use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Schema;

use Filament\Tables\Contracts\TranslatableContentDriver;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;

class AliExpressPreviewTable extends Component implements HasTable, HasSchemas
{
    use InteractsWithTable;
    use InteractsWithSchemas;

    public array $results = [];

    public function mount(array $results = []): void
    {
        $this->results = $results;

    }

    /**
     * Filament v4 Tables requires Schemas support even if you don't use custom schemas here.
     */
    public function getSchemas(): array
    {
        return [];
    }

    public function makeFilamentTranslatableContentDriver(): ?\Filament\Support\Contracts\TranslatableContentDriver
    {
        return null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn () => collect($this->results))
            ->columns([
                TextColumn::make('productTitle')
                    ->label('Title')
                    ->wrap()
                    ->searchable(),

                BadgeColumn::make('salePrice')
                    ->label('Price')
                    ->color('success')
                    ->formatStateUsing(fn ($state) => ($state !== null && $state !== '') ? ('$' . $state) : '—'),

                TextColumn::make('feedbackScore')
                    ->label('Rating')
                    ->sortable(),

                TextColumn::make('productId')
                    ->label('Product ID')
                    ->toggleable(),
            ]);
    }

    public function render(): View
    {
        return view('livewire.ali-express-preview-table');
    }
}
