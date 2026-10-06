<?php

declare(strict_types=1);

namespace App\Domain\WooCommerce\Filament;

use App\Domain\Products\Models\Category;
use App\Domain\WooCommerce\Contracts\WooCommerceClientContract;
use App\Domain\WooCommerce\DTOs\WooCommerceProductData;
use App\Domain\WooCommerce\Jobs\ImportWooCommerceProductJob;
use App\Domain\WooCommerce\Models\WooCommerceProductMap;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class WooCommerceProductImportPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-down-on-square-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Integrations';

    protected static ?string $navigationLabel = 'Import from WooCommerce';

    protected static ?string $title = 'Import Products';

    protected string $view = 'filament.pages.woocommerce-product-import';

    protected static ?int $navigationSort = 82;

    public ?array $data = [];

    public int $total = 0;

    public function mount(): void
    {
        $this->form->fill([
            'category_id' => null,
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Select::make('category_id')
                    ->label('Assign Category')
                    ->placeholder('All categories')
                    ->options(fn () => Category::with('parent')
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (Category $cat) => [
                            $cat->id => $cat->parent
                                ? "{$cat->parent->name} → {$cat->name}"
                                : $cat->name,
                        ])
                        ->toArray())
                    ->searchable(),
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(function (?string $search, int $page, int $recordsPerPage): LengthAwarePaginator {
                $client = app(WooCommerceClientContract::class);
                $result = $client->getProductsPage($page, $recordsPerPage, $search);

                $this->total = $result['total'];

                $existing = WooCommerceProductMap::whereIn(
                    'woocommerce_product_id',
                    array_map(fn (WooCommerceProductData $p) => $p->woocommerceId, $result['products']),
                )->pluck('woocommerce_product_id')->toArray();

                $items = array_map(
                    fn (WooCommerceProductData $p) => $this->productToRow($p, in_array($p->woocommerceId, $existing, true)),
                    $result['products'],
                );

                return new LengthAwarePaginator(
                    items: $items,
                    total: $result['total'],
                    perPage: $recordsPerPage,
                    currentPage: $page,
                );
            })
            ->columns([
                ImageColumn::make('thumbnail')
                    ->label('')
                    ->state(fn (array $record): ?string => $record['thumbnail'])
                    ->extraImgAttributes(['class' => 'w-10 h-10 object-cover rounded-lg border border-gray-200 dark:border-gray-700', 'loading' => 'lazy']),
                TextColumn::make('name')
                    ->label('Product')
                    ->searchable()
                    ->html()
                    ->state(fn (array $record): string => $record['name_html']),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->html()
                    ->state(fn (array $record): string => $record['sku_html']),
                TextColumn::make('regularPrice')
                    ->label('Price')
                    ->html()
                    ->state(fn (array $record): string => $record['price_html'])
                    ->sortable(),
                TextColumn::make('stockQuantity')
                    ->label('Stock')
                    ->html()
                    ->state(fn (array $record): string => $record['stock_html'])
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Type')
                    ->html()
                    ->state(fn (array $record): string => $record['type_html']),
                TextColumn::make('status')
                    ->label('Status')
                    ->html()
                    ->state(fn (array $record): string => $record['status_html']),
                TextColumn::make('imported')
                    ->label('Imported')
                    ->html()
                    ->state(fn (array $record): string => $record['imported_html']),
            ])
            ->filters([])
            ->recordAction(null)
            ->recordClasses(fn (array $record): ?string => $record['imported'] ? 'opacity-60' : null)
            ->defaultPaginationPageOption(20)
            ->paginated([20, 50, 100])
            ->extremePaginationLinks()
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('import')
                        ->label('Import Selected')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function (Collection $records): void {
                            $categoryId = $this->data['category_id'] ?? null;
                            $count = $records->count();

                            foreach ($records as $record) {
                                if ($record['imported']) {
                                    continue;
                                }

                                dispatch(new ImportWooCommerceProductJob(
                                    wooProductId: (int) $record['woocommerceId'],
                                    categoryId: $categoryId ? (int) $categoryId : null,
                                ));
                            }

                            Notification::make()
                                ->title("{$count} product(s) queued for import")
                                ->body('Check the WooCommerce sync logs for progress.')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    private function productToRow(WooCommerceProductData $p, bool $alreadyImported): array
    {
        $img = $p->images[0]['src'] ?? $p->images[0]['url'] ?? null;

        $displayName = $p->englishTitleCandidate() ?? $p->importName();
        $nameHtml = e($displayName);
        if ($p->hasNonEnglishName()) {
            $nameHtml .= '<span class="text-xs text-gray-400 dark:text-gray-500 mt-0.5 block">Original title saved in Woo metadata</span>';
        }
        if ($p->type === 'variable' && $p->variations !== []) {
            $nameHtml .= '<span class="text-xs text-gray-400 dark:text-gray-500 mt-0.5 block">' . count($p->variations) . ' variations</span>';
        }

        $skuHtml = $p->sku
            ? '<code class="text-xs font-mono text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded">' . e($p->sku) . '</code>'
            : '<span class="text-xs text-gray-300 dark:text-gray-600">&mdash;</span>';

        $activePrice = $p->activePrice();
        if ($activePrice && $activePrice > 0) {
            $decimals = (int) (config("currency.decimals.{$p->currency}") ?? 2);
            $priceHtml = '<span class="font-mono text-sm font-medium text-gray-900 dark:text-white">'
                . e($p->currency) . ' ' . number_format($activePrice, $decimals) . '</span>';
            if ($p->compareAtPrice() && $p->compareAtPrice() > 0) {
                $priceHtml .= ' <span class="text-xs text-gray-400 line-through font-mono">'
                    . e($p->currency) . ' ' . number_format($p->compareAtPrice(), $decimals) . '</span>';
            }
        } elseif ($formattedPrice = $this->formattedWooPrice($p)) {
            $priceHtml = '<span class="text-sm font-medium text-gray-900 dark:text-white">'
                . e($formattedPrice) . '</span>';
        } else {
            $priceHtml = '<span class="text-xs text-gray-300 dark:text-gray-600">&mdash;</span>';
        }

        $stockValue = $p->stockQuantity ?? 0;
        $stockColor = $stockValue > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-400 dark:text-red-500';
        $stockHtml = '<span class="font-mono text-sm ' . $stockColor . '">' . $stockValue . '</span>';

        $typeHtml = '<span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium '
            . ($p->type === 'variable'
                ? 'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-300'
                : 'bg-gray-50 text-gray-600 dark:bg-gray-700 dark:text-gray-300')
            . '">'
            . ($p->type === 'variable' ? '<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2 1 3 3 3h10c2 0 3-1 3-3V7c0-2-1-3-3-3H7c-2 0-3 1-3 3z"/></svg>' : '')
            . e($p->type) . '</span>';

        $statusHtml = '<span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium '
            . ($p->status === 'publish'
                ? 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-300'
                : 'bg-gray-50 text-gray-600 dark:bg-gray-700 dark:text-gray-300')
            . '">'
            . ($p->status === 'publish'
                ? '<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>'
                : '<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"/></svg>')
            . e($p->status === 'publish' ? 'Published' : $p->status) . '</span>';

        $importedHtml = $alreadyImported
            ? '<span class="inline-flex items-center gap-1 rounded-full bg-green-50 dark:bg-green-900/20 px-2.5 py-0.5 text-xs font-medium text-green-700 dark:text-green-300"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Synced</span>'
            : '<span class="text-xs text-gray-300 dark:text-gray-600">&mdash;</span>';

        return [
            'woocommerceId' => $p->woocommerceId,
            'name' => $p->name,
            'sku' => $p->sku,
            'regularPrice' => $p->regularPrice,
            'stockQuantity' => $p->stockQuantity,
            'type' => $p->type,
            'status' => $p->status,
            'imported' => $alreadyImported,
            'thumbnail' => $img,
            'name_html' => $nameHtml,
            'sku_html' => $skuHtml,
            'price_html' => $priceHtml,
            'stock_html' => $stockHtml,
            'type_html' => $typeHtml,
            'status_html' => $statusHtml,
            'imported_html' => $importedHtml,
        ];
    }

    private function formattedWooPrice(WooCommerceProductData $product): ?string
    {
        $priceHtml = $product->rawData['price_html'] ?? null;

        if (! is_string($priceHtml) || trim($priceHtml) === '') {
            return null;
        }

        $text = html_entity_decode(strip_tags($priceHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim((string) $text);

        return $text !== '' ? $text : null;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return config('woocommerce.enabled', false);
    }
}
