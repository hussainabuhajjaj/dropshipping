<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile\V1\Concerns;

use Illuminate\Http\Request;

trait WithoutSuccessWrapper
{
    public function with(Request $request): array
    {
        return [];
    }
}
