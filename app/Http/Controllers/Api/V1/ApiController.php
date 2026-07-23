<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController as BaseApiController;

abstract class ApiController extends BaseApiController
{
    // All response helpers now live in App\Http\Controllers\Api\ApiController.
    // This class exists for backward-compatibility with admin V1 controllers.
}
