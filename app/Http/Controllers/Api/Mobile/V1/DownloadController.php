<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Models\MobileRelease;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadController
{
    public function latestAndroid(Request $request): RedirectResponse|StreamedResponse
    {
        $release = MobileRelease::query()
            ->where('platform', 'android')
            ->where('is_active', true)
            ->latest()
            ->first();

        if (! $release || ! $release->file_path) {
            abort(404, 'No Android release available');
        }

        if (! Storage::disk($release->disk)->exists($release->file_path)) {
            abort(404, 'Release file not found on storage');
        }

        return Storage::disk($release->disk)->download(
            $release->file_path,
            $release->original_name ?? "simbazu-android-v{$release->version}.apk"
        );
    }
}
