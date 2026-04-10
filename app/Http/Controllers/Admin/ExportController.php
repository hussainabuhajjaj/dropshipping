<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Products\Models\Product;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class ExportController extends Controller
{
    public function products(): StreamedResponse
    {
        $filename = 'products-' . now()->format('Ymd-His') . '.csv';
        $columns = [
            'id',
            'name',
            'slug',
            'description',
            'selling_price',
            'cost_price',
            'stock_on_hand',
            'status',
            'is_active',
            'category_id',
            'cj_pid',
            'supplier_id',
        ];

        return response()->streamDownload(function () use ($columns) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);

            Product::query()
                ->orderBy('id')
                ->chunk(500, function ($products) use ($handle, $columns) {
                    foreach ($products as $product) {
                        fputcsv($handle, [
                            $product->id,
                            $product->name,
                            $product->slug,
                            $product->description,
                            $product->selling_price,
                            $product->cost_price,
                            $product->stock_on_hand,
                            $product->status,
                            $product->is_active ? 1 : 0,
                            $product->category_id,
                            $product->cj_pid,
                            $product->supplier_id,
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function customers(): StreamedResponse
    {
        $filename = 'customers-' . now()->format('Ymd-His') . '.csv';
        $columns = [
            'id',
            'first_name',
            'last_name',
            'email',
            'phone',
            'country_code',
            'city',
            'region',
            'address_line1',
            'address_line2',
            'postal_code',
        ];

        return response()->streamDownload(function () use ($columns) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);

            Customer::query()
                ->orderBy('id')
                ->chunk(500, function ($customers) use ($handle) {
                    foreach ($customers as $customer) {
                        fputcsv($handle, [
                            $customer->id,
                            $customer->first_name,
                            $customer->last_name,
                            $customer->email,
                            $customer->phone,
                            $customer->country_code,
                            $customer->city,
                            $customer->region,
                            $customer->address_line1,
                            $customer->address_line2,
                            $customer->postal_code,
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function productImages(Request $request, Product $product)
    {
        $idsParam = trim((string) $request->query('ids', ''));
        $ids = collect($idsParam !== '' ? explode(',', $idsParam) : [])
            ->map(fn ($id) => (int) trim((string) $id))
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $imagesQuery = $product->images()->orderBy('position');
        if ($ids !== []) {
            $imagesQuery->whereIn('id', $ids);
        }

        $images = $imagesQuery->get(['id', 'url', 'position']);
        if ($images->isEmpty()) {
            abort(404, 'No images found for this product.');
        }

        $slug = Str::slug($product->name ?: 'product', '-');
        $downloadName = $slug . '-images-' . now()->format('Ymd-His') . '.zip';

        $tmp = tempnam(sys_get_temp_dir(), 'prodimg_');
        if (! is_string($tmp) || $tmp === '') {
            abort(500, 'Failed to allocate temp file.');
        }

        $zipPath = $tmp . '.zip';
        @rename($tmp, $zipPath);

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($zipPath);
            abort(500, 'Failed to create zip.');
        }

        $errors = [];
        $usedNames = [];
        $i = 0;

        foreach ($images as $image) {
            $i++;
            $url = trim((string) $image->url);
            if ($url === '') {
                $errors[] = "Image {$image->id}: missing url";
                continue;
            }

            try {
                $resp = Http::timeout(25)->retry(2, 250)->get($url);

                if (! $resp->successful()) {
                    $errors[] = "Image {$image->id}: HTTP {$resp->status()}";
                    continue;
                }

                $bytes = $resp->body();
                if (! is_string($bytes) || $bytes === '') {
                    $errors[] = "Image {$image->id}: empty response body";
                    continue;
                }

                $path = parse_url($url, PHP_URL_PATH);
                $base = is_string($path) ? basename($path) : '';
                $base = $base !== '' ? $base : ('image-' . $image->id . '.jpg');

                $name = sprintf('%02d-', $i) . $base;
                if (isset($usedNames[$name])) {
                    $name = sprintf('%02d-', $i) . $image->id . '-' . $base;
                }
                $usedNames[$name] = true;

                $zip->addFromString($name, $bytes);
            } catch (\Throwable $e) {
                $errors[] = "Image {$image->id}: {$e->getMessage()}";
            }
        }

        if ($errors !== []) {
            $zip->addFromString('errors.txt', implode("\n", $errors) . "\n");
        }

        $zip->close();

        return response()
            ->download($zipPath, $downloadName, ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }
}
