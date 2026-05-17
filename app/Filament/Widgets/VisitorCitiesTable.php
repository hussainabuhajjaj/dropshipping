<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\Analytics\VisitAnalyticsService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;

class VisitorCitiesTable extends TableWidget
{
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Top Visitor Cities')
            ->records(function (): Collection {
                $data = app(VisitAnalyticsService::class)->summary();

                return collect(data_get($data, 'geography.top_cities', []))
                    ->map(function ($item) {
                        return [
                            'city' => $item['city'] ?? 'Unknown',
                            'country' => $item['country'] ?? '—',
                            'sessions' => (int) ($item['sessions'] ?? 0),
                        ];
                    })
                    ->sortByDesc('sessions')
                    ->take(10) // keep UI clean
                    ->values();
            })
            ->columns([
                Tables\Columns\TextColumn::make('city')
                    ->label('City')
                    ->searchable(),

                Tables\Columns\TextColumn::make('country')
                    ->label('Country')
                    ->badge(), // nice UI touch

                Tables\Columns\TextColumn::make('sessions')
                    ->label('Sessions')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => number_format($state)),
            ])
            ->emptyStateHeading('No visitor data')
            ->emptyStateDescription('No city analytics available yet.');
    }
}
