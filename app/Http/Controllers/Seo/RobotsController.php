<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seo;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RobotsController
{
    public function __invoke(Request $request): Response
    {
        $baseUrl = rtrim(config('app.url') ?: $request->getSchemeAndHttpHost(), '/');
        $body = implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Sitemap: ' . $baseUrl . '/sitemap.xml',
        ]);

        return response($body, 200)->header('Content-Type', 'text/plain');
    }
}
