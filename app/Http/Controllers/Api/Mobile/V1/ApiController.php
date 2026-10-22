<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Api\ApiController as BaseApiController;

class ApiController extends BaseApiController
{
    // All response helpers now live in App\Http\Controllers\Api\ApiController.
    // This class exists for backward-compatibility with Mobile V1 controllers.
    // Response envelope: { success, message, data, errors, meta, links }
}
