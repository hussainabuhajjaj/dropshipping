<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductResource\Pages;

use App\Domain\Products\Services\ProductActivationValidator;
use App\Filament\Resources\ProductResource;
use App\Models\Product;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function beforeCreate(): void
    {
        $shouldBeActive = (bool) ($this->data['is_active'] ?? false);
        if (! $shouldBeActive) {
            return;
        }

        $draft = new Product($this->data);
        $validator = app(ProductActivationValidator::class);
        $errors = $validator->errorsForActivation($draft, $this->data);

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'is_active' => implode(' ', $errors),
            ]);
        }
    }
}
