<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Products\Models\Category;
use App\Domain\Products\Services\AliExpressProductImportService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

trait InteractsWithAliExpressImportReview
{
    public function importSelectedProducts(): void
    {
        if (! $this->ensureAliExpressToken()) {
            return;
        }

        $idsToImport = array_values(array_filter(
            $this->selectedProductIds,
            fn ($id) => ! $this->getImportedAliIds()->contains($id)
        ));

        if ($idsToImport === []) {
            $this->notify('warning', 'No selection', 'Select one not-yet-imported product to review.');
            return;
        }

        if (count($idsToImport) > 1) {
            $this->notify('warning', 'Review one product at a time', 'The AliExpress pre-import editor currently supports a single product per confirmation.');
            return;
        }

        $record = collect($this->searchResults)->first(fn ($item) => $this->getRecordId((array) $item) === $idsToImport[0]);
        if (! is_array($record)) {
            $this->notify('warning', 'Preview missing', 'Reload the preview list and try again.');
            return;
        }

        $this->openImportPreview($record);
    }

    public function selectCurrentPage(): void
    {
        $records = $this->getCurrentPageRecords();
        $ids = collect($records)
            ->map(fn ($r) => $this->getRecordId($r))
            ->filter()
            ->values()
            ->all();

        $added = $this->addSelectedIds($ids);
        $this->notify('success', 'Selection updated', 'Added ' . $added . ' items from this page.');
    }

    public function selectAllLoaded(): void
    {
        $ids = collect($this->searchResults)
            ->map(fn ($r) => $this->getRecordId((array) $r))
            ->filter()
            ->values()
            ->all();

        $added = $this->addSelectedIds($ids);
        $this->notify('success', 'Selection updated', 'Selected ' . $added . ' additional loaded results.');
    }

    public function selectOnlyNotImported(): void
    {
        $ids = collect($this->searchResults)
            ->filter(fn ($r) => ! $this->isImportedRecord((array) $r))
            ->map(fn ($r) => $this->getRecordId((array) $r))
            ->filter()
            ->values()
            ->all();

        $added = $this->addSelectedIds($ids);
        $this->notify('success', 'Selection updated', 'Selected ' . $added . ' not-imported items.');
    }

    public function clearSelection(): void
    {
        $this->selectedProductIds = [];
        $this->notify('info', 'Selection cleared');
    }

    protected function notify(string $status, string $title, ?string $body = null, bool $persistent = false): void
    {
        $notification = \Filament\Notifications\Notification::make()->title($title)->{$status}();

        if ($body !== null && $body !== '') {
            $notification->body($body);
        }

        if ($persistent) {
            $notification->persistent();
        }

        $notification->send();
    }

    protected function addSelectedIds(array $ids): int
    {
        $normalized = collect($ids)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();

        $before = count($this->selectedProductIds);
        $this->selectedProductIds = array_values(array_unique([
            ...$this->selectedProductIds,
            ...$normalized,
        ]));

        return count($this->selectedProductIds) - $before;
    }

    protected function removeSelectedId(string $id): void
    {
        $this->selectedProductIds = array_values(array_filter(
            $this->selectedProductIds,
            fn ($value) => $value !== $id
        ));
    }

    protected function isSelectedRecord(array $record): bool
    {
        $id = $this->getRecordId($record);

        return $id !== '' && in_array($id, $this->selectedProductIds, true);
    }

    protected function toggleSelectionFromRecord(array $record): void
    {
        $id = $this->getRecordId($record);

        if ($id === '') {
            return;
        }

        if ($this->isImportedRecord($record)) {
            $this->notify('warning', 'Already imported', "Item {$id} exists.");
            return;
        }

        if ($this->isSelectedRecord($record)) {
            $this->removeSelectedId($id);
            $this->notify('info', 'Selection updated', "Item {$id} removed.");
            return;
        }

        $this->addSelectedIds([$id]);
        $this->notify('success', 'Selected', "Item {$id} added.");
    }

    public function openImportPreview(array $record): void
    {
        if (! $this->ensureAliExpressToken()) {
            return;
        }

        $id = $this->getRecordId($record);

        if ($id === '') {
            $this->notify('warning', 'Invalid record', 'Missing AliExpress ID.');
            return;
        }

        if ($this->isImportedRecord($record)) {
            $this->notify('info', 'Already imported', "Item {$id} exists.");
            return;
        }

        try {
            $service = app(AliExpressProductImportService::class);
            $requestOptions = $this->buildImportRequestOptions();
            $preview = $service->buildImportPreviewById($id, $requestOptions);

            if (! is_array($preview)) {
                $this->notify('danger', 'Preview unavailable', "Item {$id} could not be previewed.");
                return;
            }

            $variantDeliverability = $service->resolveVariantDeliverabilityById($id, $requestOptions);
            $deliverability = $service->resolveDeliverabilityById($id, $requestOptions);
            $preview['deliverability'] = [
                ...$deliverability,
                'variants' => $variantDeliverability,
            ];

            $preview['variants'] = array_map(function (array $variant) use ($variantDeliverability): array {
                $skuId = (string) ($variant['sku_id'] ?? '');

                return [
                    ...$variant,
                    'deliverability' => is_array($variantDeliverability[$skuId] ?? null)
                        ? $variantDeliverability[$skuId]
                        : null,
                ];
            }, $preview['variants'] ?? []);

            $validation = is_array($preview['validation'] ?? null) ? $preview['validation'] : [];
            $validation['errors'] = is_array($validation['errors'] ?? null) ? $validation['errors'] : [];
            $validation['warnings'] = is_array($validation['warnings'] ?? null) ? $validation['warnings'] : [];
            $deliverableSkuIds = collect($variantDeliverability)
                ->filter(fn ($status) => $status['is_deliverable'] ?? false)
                ->keys()
                ->values()
                ->all();

            if (! ($deliverability['is_deliverable'] ?? false)) {
                $message = (string) ($deliverability['reason'] ?? 'This product is not currently deliverable to the selected destination.');
                $validation['errors'][] = $message;
                $validation['is_valid'] = false;

                if ($this->deliverable_only) {
                    $this->notify('warning', 'Not deliverable', $message);
                    return;
                }
            }
            if (($deliverability['variants_count'] ?? 0) > 0 && count($deliverableSkuIds) < (int) ($deliverability['variants_count'] ?? 0)) {
                $validation['warnings'][] = sprintf(
                    '%d of %d variant(s) are deliverable to the selected destination.',
                    count($deliverableSkuIds),
                    (int) ($deliverability['variants_count'] ?? 0)
                );
            }

            $preview['validation'] = $validation;
            $preview['selected_variant_ids'] = array_values(array_intersect(
                (array) ($preview['selected_variant_ids'] ?? []),
                $deliverableSkuIds
            ));

            $this->importPreview = $preview;
            $this->importForm = [
                'ali_item_id' => $id,
                'title' => (string) ($preview['title'] ?? ''),
                'description' => (string) ($preview['description'] ?? ''),
                'category_id' => $preview['category_id'] ?? null,
                'enabled_variant_ids' => $preview['selected_variant_ids'] ?? [],
                ...$requestOptions,
            ];

            $this->dispatch('open-modal', id: $this->getImportPreviewModalId());
        } catch (\Exception $e) {
            Log::error('AliExpress import preview failed', ['item_id' => $id, 'error' => $e->getMessage()]);
            $this->notify('danger', 'Preview failed', $e->getMessage());
        }
    }

    public function closeImportPreview(): void
    {
        $this->importPreview = null;
        $this->importForm = [];
    }

    public function confirmImportPreview(): void
    {
        if (! $this->ensureAliExpressToken()) {
            return;
        }

        $preview = $this->importPreview;
        $itemId = (string) ($this->importForm['ali_item_id'] ?? '');
        $categoryId = $this->importForm['category_id'] ?? null;
        $enabledVariantIds = array_values(array_filter(array_map(
            fn ($value) => trim((string) $value),
            (array) ($this->importForm['enabled_variant_ids'] ?? [])
        )));

        if (! is_array($preview) || $itemId === '') {
            $this->notify('warning', 'Preview missing', 'Open a product preview before importing.');
            return;
        }

        if (! $categoryId) {
            $this->notify('warning', 'Category required', 'Select a local category before importing.');
            return;
        }

        if ($enabledVariantIds === []) {
            $this->notify('warning', 'No valid variants', 'Enable at least one valid variant before importing.');
            return;
        }

        $service = app(AliExpressProductImportService::class);
        $requestOptions = $this->buildImportRequestOptions($this->importForm);
        $variantDeliverability = is_array(data_get($preview, 'deliverability.variants'))
            ? data_get($preview, 'deliverability.variants')
            : $service->resolveVariantDeliverabilityById($itemId, $requestOptions);
        $deliverability = $service->resolveDeliverabilityById($itemId, $requestOptions);
        if (! ($deliverability['is_deliverable'] ?? false)) {
            $this->notify('warning', 'Not deliverable', (string) ($deliverability['reason'] ?? 'This product is not currently deliverable to the selected destination.'));
            return;
        }

        $undeliverableSelected = array_values(array_filter(
            $enabledVariantIds,
            fn (string $skuId): bool => ! (data_get($variantDeliverability, "{$skuId}.is_deliverable", false))
        ));

        if ($undeliverableSelected !== []) {
            $this->notify(
                'warning',
                'Undeliverable variants selected',
                'Remove variants that are not deliverable to the selected destination before importing.'
            );
            return;
        }

        $product = $service->importById($itemId, [
            ...$requestOptions,
            'title' => (string) ($this->importForm['title'] ?? ''),
            'description' => (string) ($this->importForm['description'] ?? ''),
            'category_id' => (int) $categoryId,
            'enabled_variant_ids' => $enabledVariantIds,
        ]);

        if (! $product) {
            $this->notify('danger', 'Import failed', "Item {$itemId} could not be imported.");
            return;
        }

        $this->dispatch('close-modal', id: $this->getImportPreviewModalId());
        $this->closeImportPreview();
        $this->refreshImportedAliIds();
        $this->removeSelectedId($itemId);
        $this->notify('success', 'Imported', "Item {$itemId} imported successfully.");
    }

    public function getImportPreviewModalId(): string
    {
        return 'aliexpress-import-preview-modal';
    }

    public function getImportCategoryOptions(): array
    {
        return Category::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->map(fn ($name) => (string) $name)
            ->all();
    }

    public function getAliExpressLanguageOptions(): array
    {
        return [
            'en_US' => 'English (en_US)',
            'en' => 'English (en)',
            'de' => 'German (de)',
            'ru' => 'Russian (ru)',
            'pt' => 'Portuguese (pt)',
            'ko' => 'Korean (ko)',
            'it' => 'Italian (it)',
            'fr' => 'French (fr)',
            'zh' => 'Chinese (zh)',
            'es' => 'Spanish (es)',
            'iw' => 'Hebrew legacy (iw)',
            'he' => 'Hebrew (he)',
            'ar' => 'Arabic (ar)',
            'vi' => 'Vietnamese (vi)',
            'th' => 'Thai (th)',
            'uk' => 'Ukrainian (uk)',
            'ja' => 'Japanese (ja)',
            'id' => 'Indonesian (id)',
            'pl' => 'Polish (pl)',
            'nl' => 'Dutch (nl)',
            'tr' => 'Turkish (tr)',
            'ko_KR' => 'Korean (ko_KR)',
        ];
    }

    protected function buildImportRequestOptions(?array $source = null): array
    {
        $source ??= $this->getFormState();

        return array_filter([
            'ship_to_country' => $this->normalizeAliExpressScalar($source['ship_to_country'] ?? $this->ship_to_country ?? self::DEFAULT_SHIP_TO_COUNTRY),
            'target_currency' => $this->normalizeAliExpressScalar($source['target_currency'] ?? $this->target_currency ?? self::DEFAULT_TARGET_CURRENCY),
            'target_language' => $this->normalizeAliExpressScalar($source['target_language'] ?? $this->target_language ?? self::DEFAULT_TARGET_LANGUAGE),
            'remove_personal_benefit' => isset($source['remove_personal_benefit'])
                ? (bool) $source['remove_personal_benefit']
                : $this->remove_personal_benefit,
            'biz_model' => $this->normalizeAliExpressScalar($source['biz_model'] ?? $this->biz_model),
            'province_code' => $this->normalizeAliExpressScalar($source['province_code'] ?? $this->province_code ?? self::DEFAULT_PROVINCE),
            'city_code' => $this->normalizeAliExpressScalar($source['city_code'] ?? $this->city_code ?? self::DEFAULT_CITY),
        ], fn ($value) => is_bool($value) || ($value !== null && $value !== ''));
    }

    protected function normalizeAliExpressScalar(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value !== '' ? $value : null;
    }

    public function getImportPreviewVariantRows(): Collection
    {
        return collect($this->importPreview['variants'] ?? [])
            ->filter(fn ($variant) => is_array($variant))
            ->values();
    }

    public function getImportPreviewAttributeRows(): Collection
    {
        return collect($this->importPreview['attributes'] ?? [])
            ->filter(fn ($attribute) => is_array($attribute))
            ->values();
    }
}
