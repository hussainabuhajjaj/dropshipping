<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CJDropshippingController extends Controller
{
    public function __invoke(Request $request, CJWebhookController $webhookController)
    {
        return $webhookController($request);
    }
}
