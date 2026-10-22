<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Category;
use App\Models\Product;
use App\Models\StorefrontCollection;
use App\Filament\Resources\StorefrontCollectionResource;
use App\Jobs\ApplyProductMarginChunkJob;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\Pages\Page;
use Filament\Support\Contracts\TranslatableContentDriver;
use App\Services\Storefront\HomeBuilderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Livewire\WithPagination;

class StorefrontCollectionProductPicker extends Page
{
    use WithPagination;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.storefront-collection-product-picker';

    public int $collectionId;

    public string $search = '';

    public ?int $categoryId = null;

    public ?string $isActive = null;

    public ?string $isFeatured = null;

    public ?float $minPrice = null;

    public ?float $maxPrice = null;

    public ?float $minRating = null;

    public ?float $minMargin = null;

    public bool $activateOnAttach = true;

    public bool $featureOnAttach = false;

    public bool $applyMarginOnAttach = false;

    public float $attachMarginPercent = 30.0;

    public int $perPage = 24;

    /** @var array<int> */
    public array $selectedProductIds = [];

    public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
    {
        return null;
    }

    private function maybeProxyCjUrl(string $url): string
    {
        if (str_starts_with($url, 'https://cf.cjdropshipping.com/')) {
            return url('/media/proxy?url=' . urlencode($url));
        }

        return $url;
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return 'legacy/storefront-collections/{collection}/products/pick';
    }

    public function mount(int|string $collection): void
    {
        $record = StorefrontCollection::query()
            ->when(
                is_numeric($collection),
                fn ($query) => $query->whereKey((int) $collection),
                fn ($query) => $query->where('slug', (string) $collection)
            )
            ->firstOrFail();

        $this->redirect(StorefrontCollectionResource::getUrl('pick-products', ['record' => $record]));
    }

    public function updated($name): void
    {
        $this->resetPage();
    }

    public function toggleSelected(int $productId): void
    {
        if (in_array($productId, $this->selectedProductIds, true)) {
            $this->selectedProductIds = array_values(array_diff($this->selectedProductIds, [$productId]));
            return;
        }

        $this->selectedProductIds = array_values(array_unique(array_merge($this->selectedProductIds, [$productId])));
    }

    public function getTitle(): string
    {
        $collection = StorefrontCollection::query()->find($this->collectionId);
        return $collection ? ('Pick products: ' . $collection->title) : 'Pick products';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('attachSelected')
                ->label('Attach selected')
                ->requiresConfirmation()
                ->modalHeading('Attach selected products')
                ->form([
                    \Filament\Forms\Components\Toggle::make('activate')
                        ->label('Activate products')
                        ->default(fn () => $this->activateOnAttach),
                    \Filament\Forms\Components\Toggle::make('feature')
                        ->label('Mark as featured')
                        ->default(fn () => $this->featureOnAttach),
                    \Filament\Forms\Components\Toggle::make('apply_margin')
                        ->label('Apply margin (same as Products bulk margin)')
                        ->default(fn () => $this->applyMarginOnAttach),
                    \Filament\Forms\Components\TextInput::make('margin_percent')
                        ->label('Margin %')
                        ->numeric()
                        ->minValue(0)
                        ->default(fn () => $this->attachMarginPercent)
                        ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => (bool) $get('apply_margin')),
                ])
                ->action(function (): void {
                    $ids = array_values(array_unique(array_filter(array_map('intval', $this->selectedProductIds))));
                    if ($ids === []) {
                        Notification::make()->title('No products selected')->warning()->send();
                        return;
                    }

                    $collection = StorefrontCollection::query()->find($this->collectionId);
                    if (! $collection) {
                        Notification::make()->title('Collection not found')->danger()->send();
                        return;
                    }

                    $existing = DB::table('storefront_collection_products')
                        ->where('storefront_collection_id', $collection->id)
                        ->pluck('product_id')
                        ->map(fn ($id) => (int) $id)
                        ->all();

                    $toInsert = array_values(array_diff($ids, $existing));
                    if ($toInsert === []) {
                        Notification::make()->title('All selected products are already attached')->info()->send();
                        return;
                    }

                    $maxPosition = (int) (DB::table('storefront_collection_products')
                        ->where('storefront_collection_id', $collection->id)
                        ->max('position') ?? 0);

                    $now = now();
                    $rows = [];
                    foreach ($toInsert as $i => $productId) {
                        $rows[] = [
                            'storefront_collection_id' => $collection->id,
                            'product_id' => (int) $productId,
                            'position' => $maxPosition + $i + 1,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    DB::table('storefront_collection_products')->insert($rows);

                    // Optional post-attach actions
                    $activate = (bool) ($this->mountedActionsData[0]['activate'] ?? $this->activateOnAttach);
                    $feature = (bool) ($this->mountedActionsData[0]['feature'] ?? $this->featureOnAttach);
                    $applyMargin = (bool) ($this->mountedActionsData[0]['apply_margin'] ?? $this->applyMarginOnAttach);
                    $marginPercent = (float) ($this->mountedActionsData[0]['margin_percent'] ?? $this->attachMarginPercent);

                    if ($activate || $feature) {
                        $updates = [];
                        if ($activate) {
                            $updates['is_active'] = true;
                            $updates['status'] = 'active';
                        }
                        if ($feature) {
                            $updates['is_featured'] = true;
                        }

                        if ($updates !== []) {
                            Product::query()->whereIn('id', $toInsert)->update($updates);
                        }
                    }

                    if ($applyMargin) {
                        $marginPercent = max(0.0, $marginPercent);
                        foreach (array_chunk($toInsert, 200) as $chunk) {
                            ApplyProductMarginChunkJob::dispatch(
                                productIds: array_values(array_map('intval', $chunk)),
                                margin: $marginPercent,
                                applyVariants: true,
                            );
                        }
                    }

                    $this->selectedProductIds = [];

                    Notification::make()
                        ->title('Products attached')
                        ->body('Attached ' . count($rows) . ' product(s).')
                        ->success()
                        ->send();

                    $this->redirect(StorefrontCollectionResource::getUrl('edit', ['record' => $collection]));
                }),
        ];
    }

    protected function productsQuery(): Builder
    {
        $query = Product::query();

        $query
            ->with(['category', 'latestMarginLog'])
            ->withAvg('reviews', 'rating')
            ->addSelect([
                // Add product images from product_images table
                'first_image_url' => DB::table('product_images')
                    ->select('url')
                    ->whereColumn('product_images.product_id', 'products.id')
                    ->orderBy('product_images.position', 'asc')
                    ->limit(1),
                'variants_min_price' => DB::table('product_variants')
                    ->selectRaw('MIN(price)')
                    ->whereColumn('product_variants.product_id', 'products.id'),
                'variants_max_price' => DB::table('product_variants')
                    ->selectRaw('MAX(price)')
                    ->whereColumn('product_variants.product_id', 'products.id'),
            ])
            ->whereNotIn('products.id', function ($sub) {
                $sub->select('product_id')
                    ->from('storefront_collection_products')
                    ->where('storefront_collection_id', $this->collectionId);
            });
        if ($this->categoryId) {
            $query->where('products.category_id', $this->categoryId);
        }

        if ($this->isActive !== null && $this->isActive !== '') {
            $query->where('products.is_active', $this->isActive === '1');
        }

        if ($this->isFeatured !== null && $this->isFeatured !== '') {
            $query->where('products.is_featured', $this->isFeatured === '1');
        }

        if ($this->minPrice !== null || $this->maxPrice !== null) {
            $query->priceRange($this->minPrice, $this->maxPrice);
        }

        if ($this->minRating !== null) {
            $query->having('reviews_avg_rating', '>=', $this->minRating);
        }

        if ($this->minMargin !== null) {
            $query->whereHas('latestMarginLog', fn (Builder $q) => $q->where('new_margin_percent', '>=', $this->minMargin));
        }
        return $query;
    }

    /** @return LengthAwarePaginator<Product> */
    public function getProductsProperty(): LengthAwarePaginator
    {
        return $this->productsQuery()
            ->orderByDesc('products.id')
            ->paginate($this->perPage);
    }

    public function productThumbUrl(Product $product): ?string
    {
        $homeBuilder = app(HomeBuilderService::class);

        $url = $homeBuilder->normalizeImage(data_get($product, 'first_image_url'));
        if ($url) {
            return $this->maybeProxyCjUrl($url);
        }

        $image = $product->relationLoaded('images') ? $product->images->first() : null;
        $url = $homeBuilder->normalizeImage($image?->url);
        if ($url) {
            return $this->maybeProxyCjUrl($url);
        }
        
        $marketing = is_array($product->marketing_metadata ?? null) ? $product->marketing_metadata : [];
        $candidate = Arr::get($marketing, 'image')
            ?? Arr::get($marketing, 'thumbnail')
            ?? Arr::get($marketing, 'hero_image');
        $url = $homeBuilder->normalizeImage(is_string($candidate) ? $candidate : null);
        if ($url) {
            return $this->maybeProxyCjUrl($url);
        }

        $cj = is_array($product->cj_last_payload ?? null) ? $product->cj_last_payload : [];
        foreach ([
            'bigImage',
            'bigImg',
            'big_image',
            'productImage',
            'productImageUrl',
            'mainImage',
            'mainImg',
            'data.bigImage',
            'data.bigImg',
            'data.big_image',
            'data.productImage',
            'data.productImageUrl',
            'data.mainImage',
            'data.mainImg',
            'data.url',
            'data.image',
            'productImageSet[0].bigImage',
            'productImageSet[0].bigImg',
            'productImageSet[0].url',
            'data.productImageSet[0].bigImage',
            'data.productImageSet[0].bigImg',
            'data.productImageSet[0].url',
        ] as $key) {
            $candidate = Arr::get($cj, $key);
            $url = $homeBuilder->normalizeImage(is_string($candidate) ? $candidate : null);
            if ($url) {
                return $this->maybeProxyCjUrl($url);
            }
        }

        return null;
    }

    /** @return array<int, string> */
    public function getCategoryOptionsProperty(): array
    {
        return Category::query()->orderBy('name')->pluck('name', 'id')->all();
    }
}
