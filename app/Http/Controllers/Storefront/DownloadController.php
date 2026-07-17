<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\MobileRelease;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadController extends Controller
{
    public function index()
    {
        $release = MobileRelease::query()
            ->where('platform', 'android')
            ->where('is_active', true)
            ->latest()
            ->first();

        $appConfig = config('app-download');

        $android = $release ? [
            'version_name' => $release->version,
            'version_code' => $this->parseVersionCode($release->version),
            'file_exists' => $release->file_path && Storage::disk($release->disk)->exists($release->file_path),
            'size_mb' => $release->file_size
                ? round($release->file_size / 1024 / 1024, 1)
                : ($release->file_path && Storage::disk($release->disk)->exists($release->file_path)
                    ? round(Storage::disk($release->disk)->size($release->file_path) / 1024 / 1024, 1)
                    : null),
            'size_bytes' => $release->file_size
                ?? ($release->file_path && Storage::disk($release->disk)->exists($release->file_path)
                    ? Storage::disk($release->disk)->size($release->file_path)
                    : null),
            'min_sdk' => $appConfig['android']['min_sdk'] ?? 24,
            'target_sdk' => $appConfig['android']['target_sdk'] ?? 34,
            'updated_at' => $release->created_at?->format('Y-m-d'),
            'changelog' => $release->release_notes ?? $appConfig['android']['changelog'],
            'download_url' => route('download.apk'),
            'package_name' => $appConfig['android']['package_name'],
        ] : [
            'version_name' => $appConfig['android']['version_name'],
            'version_code' => (int) $appConfig['android']['version_code'],
            'file_exists' => false,
            'size_mb' => (float) $appConfig['android']['size_mb'],
            'min_sdk' => (int) $appConfig['android']['min_sdk'],
            'target_sdk' => (int) $appConfig['android']['target_sdk'],
            'updated_at' => $appConfig['android']['updated_at'],
            'changelog' => $appConfig['android']['changelog'],
            'download_url' => $appConfig['android']['download_url'],
            'package_name' => $appConfig['android']['package_name'],
        ];

        return Inertia::render('Download', [
            'android' => $android,
            'ios' => $appConfig['ios'],
            'features' => $appConfig['features'],
        ]);
    }

    public function downloadApk(Request $request): JsonResponse|RedirectResponse|StreamedResponse
    {
        $release = MobileRelease::query()
            ->where('platform', 'android')
            ->where('is_active', true)
            ->latest()
            ->first();

        if (! $release || ! $release->file_path || ! Storage::disk($release->disk)->exists($release->file_path)) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'APK not available yet'], 404);
            }

            return redirect()->route('download')->with('notice', 'The APK is being prepared. Check back soon.');
        }

        return Storage::disk($release->disk)->download(
            $release->file_path,
            $release->original_name ?? "simbazu-android-v{$release->version}.apk",
            [
                'Content-Type' => 'application/vnd.android.package-archive',
                'Content-Description' => 'Simbazu Android App',
            ]
        );
    }

    public function latestApkInfo(): JsonResponse
    {
        $release = MobileRelease::query()
            ->where('platform', 'android')
            ->where('is_active', true)
            ->latest()
            ->first();

        $appConfig = config('app-download');

        if (! $release) {
            return response()->json([
                'version_name' => $appConfig['android']['version_name'],
                'version_code' => (int) $appConfig['android']['version_code'],
                'filename' => $appConfig['android']['filename'],
                'size_mb' => (float) $appConfig['android']['size_mb'],
                'min_sdk' => (int) $appConfig['android']['min_sdk'],
                'updated_at' => $appConfig['android']['updated_at'],
                'changelog' => $appConfig['android']['changelog'],
                'download_url' => route('download.apk'),
                'package_name' => $appConfig['android']['package_name'],
            ]);
        }

        return response()->json([
            'version_name' => $release->version,
            'version_code' => $this->parseVersionCode($release->version),
            'size_mb' => $release->file_size ? round($release->file_size / 1024 / 1024, 1) : null,
            'min_sdk' => $appConfig['android']['min_sdk'] ?? 24,
            'updated_at' => $release->created_at?->format('Y-m-d'),
            'changelog' => $release->release_notes ?? $appConfig['android']['changelog'],
            'download_url' => route('download.apk'),
            'package_name' => $appConfig['android']['package_name'],
        ]);
    }

    private function parseVersionCode(string $version): int
    {
        $parts = explode('.', $version);

        $code = 0;
        foreach ($parts as $i => $part) {
            if (is_numeric($part)) {
                $code += (int) $part * (int) pow(100, 2 - min($i, 2));
            }
        }

        return max($code, 1);
    }
}
