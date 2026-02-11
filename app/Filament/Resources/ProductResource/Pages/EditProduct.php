<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductResource\Pages;

use App\Domain\Products\Services\ProductActivationValidator;
use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function beforeSave(): void
    {
        $shouldBeActive = (bool) ($this->data['is_active'] ?? false);
        if (! $shouldBeActive) {
            return;
        }

        $validator = app(ProductActivationValidator::class);
        $errors = $validator->errorsForActivation($this->getRecord(), $this->data);

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'is_active' => implode(' ', $errors),
            ]);
        }
    }
}
