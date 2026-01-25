<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Products\Models\Product;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
}
