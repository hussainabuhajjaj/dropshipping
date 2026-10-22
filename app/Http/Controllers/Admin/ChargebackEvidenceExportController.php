<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Orders\Models\ChargebackEvidenceBundle;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class ChargebackEvidenceExportController
{
    /**
     * Download evidence bundle file.
     */
    public function download(ChargebackEvidenceBundle $bundle): Response
    {
        // Verify user has permission to view this bundle's case
        $this->authorize('view', $bundle->chargebackCase);

        if (!Storage::disk('local')->exists($bundle->file_path)) {
            abort(404, 'Bundle file not found');
        }

        $content = Storage::disk('local')->get($bundle->file_path);
        $filename = sprintf(
            'chargeback_%s.%s',
            $bundle->chargebackCase->case_number,
            $bundle->format
        );

        return response($content)
            ->header('Content-Type', $this->getMimeType($bundle->format))
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    /**
     * Get MIME type for format.
     */
    private function getMimeType(string $format): string
    {
        return match ($format) {
            'pdf' => 'application/pdf',
            'text' => 'text/plain',
            default => 'application/octet-stream',
        };
    }
}
