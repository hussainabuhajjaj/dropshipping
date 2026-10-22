<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductReviewResource\Pages;

use App\Filament\Resources\ProductReviewResource;
use Filament\Resources\Pages\EditRecord;

class EditProductReview extends EditRecord
{
    protected static string $resource = ProductReviewResource::class;
}
