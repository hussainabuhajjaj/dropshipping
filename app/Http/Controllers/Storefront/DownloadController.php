<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;

class DownloadController extends Controller
{
    public function index()
    {
        $appConfig = config('app-download');
        $apkPath = public_path('apk/' . $appConfig['android']['filename']);

        return Inertia::render('Download', [
            'android' => array_merge($appConfig['android'], [
                'file_exists' => file_exists($apkPath),
            ]),
            'ios' => $appConfig['ios'],
            'features' => $appConfig['features'],
        ]);
    }

    public function downloadApk(Request $request)
    {
        $filename = config('app-download.android.filename');
        $path = public_path("apk/{$filename}");

        if (! file_exists($path)) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'APK not available yet'], 404);
            }

            return redirect()->route('download')->with('notice', 'The APK is being prepared. Check back soon.');
        }

        return Response::download($path, $filename, [
            'Content-Type' => 'application/vnd.android.package-archive',
            'Content-Description' => 'Simbazu Android App',
        ]);
    }

    public function latestApkInfo()
    {
        $android = config('app-download.android');

        return response()->json([
            'version_name' => $android['version_name'],
            'version_code' => (int) $android['version_code'],
            'filename' => $android['filename'],
            'size_mb' => (float) $android['size_mb'],
            'min_sdk' => (int) $android['min_sdk'],
            'updated_at' => $android['updated_at'],
            'changelog' => $android['changelog'],
            'download_url' => $android['download_url'],
            'package_name' => $android['package_name'],
        ]);
    }
}
