<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Products\Models\Category;
use App\Domain\Products\Models\Product;
use App\Models\Customer;
use App\Models\DataImportLog;
use BackedEnum;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Livewire\WithFileUploads;
use UnitEnum;

class DataManager extends BasePage
{
    use WithFileUploads;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static UnitEnum|string|null $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 80;
    protected static bool $adminOnly = true;

    protected string $view = 'filament.pages.data-manager';

    public $productImport;
    public $customerImport;

    public array $productImportSummary = [];
    public array $customerImportSummary = [];

    public function importProducts(): void
    {
        $this->validate([
            'productImport' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        [$stats, $summary] = $this->processProductImport($this->productImport->getRealPath());
        $this->productImportSummary = $summary;
        $this->logImport('products', $stats, $summary);
        $this->reset('productImport');

        Notification::make()
            ->title('Product import complete')
            ->body("Created {$stats['created']} · Updated {$stats['updated']} · Skipped {$stats['skipped']}")
            ->success()
            ->send();
    }

    public function importCustomers(): void
    {
        $this->validate([
            'customerImport' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        [$stats, $summary] = $this->processCustomerImport($this->customerImport->getRealPath());
        $this->customerImportSummary = $summary;
        $this->logImport('customers', $stats, $summary);
        $this->reset('customerImport');

        Notification::make()
            ->title('Customer import complete')
            ->body("Created {$stats['created']} · Updated {$stats['updated']} · Skipped {$stats['skipped']}")
            ->success()
            ->send();
    }

    /**
     * @return array{0:array{total:int,created:int,updated:int,skipped:int},1:array<string,mixed>}
     */
    private function processProductImport(string $path): array
    {
        [$rows, $headers] = $this->readCsv($path);
        $stats = ['total' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0];
        $summary = ['headers' => $headers];

        foreach ($rows as $row) {
            $stats['total']++;
            $data = $this->mapRow($headers, $row);

            $name = trim((string) ($data['name'] ?? ''));
            if ($name === '') {
                $stats['skipped']++;
                continue;
            }

            $slug = trim((string) ($data['slug'] ?? ''));
            if ($slug === '') {
                $slug = Str::slug($name);
            }

            $payload = array_filter([
                'name' => $name,
                'slug' => $slug,
                'description' => $data['description'] ?? null,
                'selling_price' => $this->toFloat($data['selling_price'] ?? null),
                'cost_price' => $this->toFloat($data['cost_price'] ?? null),
                'stock_on_hand' => $this->toInt($data['stock_on_hand'] ?? null),
                'status' => $data['status'] ?? null,
                'is_active' => $this->toBool($data['is_active'] ?? null),
                'cj_pid' => $data['cj_pid'] ?? null,
                'supplier_id' => $this->toInt($data['supplier_id'] ?? null),
            ], fn ($value) => $value !== null && $value !== '');

            $categoryId = $this->resolveCategoryId($data);
            if ($categoryId) {
                $payload['category_id'] = $categoryId;
            }

            $product = $this->findProduct($data, $slug);
            if ($product) {
                $product->fill($payload);
                if ($product->isDirty()) {
                    $product->save();
                    $stats['updated']++;
                } else {
                    $stats['skipped']++;
                }
                continue;
            }

            Product::create($payload);
            $stats['created']++;
        }

        $summary['rows'] = $stats['total'];

        return [$stats, $summary];
    }

    /**
     * @return array{0:array{total:int,created:int,updated:int,skipped:int},1:array<string,mixed>}
     */
    private function processCustomerImport(string $path): array
    {
        [$rows, $headers] = $this->readCsv($path);
        $stats = ['total' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0];
        $summary = ['headers' => $headers];

        foreach ($rows as $row) {
            $stats['total']++;
            $data = $this->mapRow($headers, $row);

            $email = strtolower(trim((string) ($data['email'] ?? '')));
            if ($email === '') {
                $stats['skipped']++;
                continue;
            }

            $payload = array_filter([
                'email' => $email,
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'country_code' => $data['country_code'] ?? null,
                'city' => $data['city'] ?? null,
                'region' => $data['region'] ?? null,
                'address_line1' => $data['address_line1'] ?? null,
                'address_line2' => $data['address_line2'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
            ], fn ($value) => $value !== null && $value !== '');

            $password = trim((string) ($data['password'] ?? ''));

            $customer = Customer::query()->where('email', $email)->first();
            if ($customer) {
                if ($password !== '') {
                    $payload['password'] = $password;
                }
                $customer->fill($payload);
                if ($customer->isDirty()) {
                    $customer->save();
                    $stats['updated']++;
                } else {
                    $stats['skipped']++;
                }
                continue;
            }

            if ($password === '') {
                $password = Str::random(12);
            }

            $payload['password'] = $password;
            Customer::create($payload);
            $stats['created']++;
        }

        $summary['rows'] = $stats['total'];

        return [$stats, $summary];
    }

    /**
     * @return array{0:array<int, array<int, string>>,1:array<int, string>}
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            return [[], []];
        }

        $headers = fgetcsv($handle);
        if (! $headers) {
            fclose($handle);
            return [[], []];
        }

        $headers = array_map(fn ($header) => $this->normalizeHeader((string) $header), $headers);
        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || $row === false) {
                continue;
            }
            $rows[] = $row;
        }

        fclose($handle);

        return [$rows, $headers];
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, string> $row
     * @return array<string, string>
     */
    private function mapRow(array $headers, array $row): array
    {
        $mapped = [];
        foreach ($headers as $index => $header) {
            $mapped[$header] = $row[$index] ?? '';
        }

        return $mapped;
    }

    private function normalizeHeader(string $header): string
    {
        $header = strtolower(trim($header));
        $header = str_replace([' ', '-'], '_', $header);

        return $header;
    }

    private function resolveCategoryId(array $data): ?int
    {
        if (! empty($data['category_id'])) {
            return $this->toInt($data['category_id']);
        }

        $slug = trim((string) ($data['category_slug'] ?? ''));
        if ($slug !== '') {
            return Category::query()->where('slug', $slug)->value('id');
        }

        $name = trim((string) ($data['category_name'] ?? ''));
        if ($name !== '') {
            return Category::query()->where('name', $name)->value('id');
        }

        return null;
    }

    private function findProduct(array $data, string $slug): ?Product
    {
        if (! empty($data['id'])) {
            return Product::query()->find($data['id']);
        }

        if (! empty($data['cj_pid'])) {
            return Product::query()->where('cj_pid', $data['cj_pid'])->first();
        }

        return Product::query()->where('slug', $slug)->first();
    }

    private function toBool(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = strtolower(trim((string) $value));
        if (in_array($value, ['1', 'true', 'yes', 'y'], true)) {
            return true;
        }

        if (in_array($value, ['0', 'false', 'no', 'n'], true)) {
            return false;
        }

        return null;
    }

    private function toInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function logImport(string $type, array $stats, array $summary): void
    {
        DataImportLog::create([
            'user_id' => auth(config('filament.auth.guard', 'admin'))->id(),
            'type' => $type,
            'total_rows' => $stats['total'],
            'created_count' => $stats['created'],
            'updated_count' => $stats['updated'],
            'skipped_count' => $stats['skipped'],
            'summary' => $summary,
        ]);
    }
}
