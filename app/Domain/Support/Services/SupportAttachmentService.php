<?php

declare(strict_types=1);

namespace App\Domain\Support\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupportAttachmentService
{
    /**
     * @return array<string, mixed>
     */
    public function store(UploadedFile $file): array
    {
        $mime = strtolower((string) ($file->getMimeType() ?: 'application/octet-stream'));
        $this->assertAllowedMime($mime);

        if ($this->isCompressibleImage($mime)) {
            $compressed = $this->storeCompressedImage($file, $mime);
            if ($compressed !== null) {
                return $compressed;
            }
        }

        return $this->storeOriginalFile($file, $mime);
    }

    /**
     * @return array<int, string>
     */
    public function allowedMimes(): array
    {
        $configured = config('support_chat.attachments.allowed_mimes', []);
        if (! is_array($configured)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($value): string => strtolower(trim((string) $value)),
            $configured
        )));
    }

    private function assertAllowedMime(string $mime): void
    {
        $allowed = $this->allowedMimes();

        if ($allowed !== [] && ! in_array($mime, $allowed, true)) {
            throw ValidationException::withMessages([
                'file' => ['Unsupported attachment type.'],
            ]);
        }
    }

    private function isCompressibleImage(string $mime): bool
    {
        return in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function storeCompressedImage(UploadedFile $file, string $mime): ?array
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $raw = @file_get_contents($file->getRealPath());
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $source = @imagecreatefromstring($raw);
        if (! $source) {
            return null;
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        if ($sourceWidth <= 0 || $sourceHeight <= 0) {
            imagedestroy($source);

            return null;
        }

        $maxWidth = max(320, (int) config('support_chat.attachments.image_max_width', 1600));
        $targetWidth = $sourceWidth;
        $targetHeight = $sourceHeight;
        if ($sourceWidth > $maxWidth) {
            $targetWidth = $maxWidth;
            $targetHeight = (int) round(($sourceHeight / $sourceWidth) * $targetWidth);
        }

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        if (! $target) {
            imagedestroy($source);

            return null;
        }

        imagealphablending($target, false);
        imagesavealpha($target, true);
        $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
        imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $transparent);

        imagecopyresampled(
            $target,
            $source,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight
        );

        $quality = max(40, min(95, (int) config('support_chat.attachments.image_quality', 82)));
        $encodeAsWebp = (bool) config('support_chat.attachments.image_convert_to_webp', true)
            && function_exists('imagewebp');

        $binary = null;
        $outputMime = $mime;
        $extension = $this->extensionFromMime($mime);

        ob_start();
        if ($encodeAsWebp) {
            $ok = imagewebp($target, null, $quality);
            $outputMime = 'image/webp';
            $extension = 'webp';
        } elseif ($mime === 'image/png') {
            $pngQuality = (int) round((100 - $quality) / 10);
            $ok = imagepng($target, null, max(0, min(9, $pngQuality)));
            $outputMime = 'image/png';
            $extension = 'png';
        } else {
            imagealphablending($target, true);
            $ok = imagejpeg($target, null, $quality);
            $outputMime = 'image/jpeg';
            $extension = 'jpg';
        }
        $binary = ob_get_clean();

        imagedestroy($source);
        imagedestroy($target);

        if (! $ok || ! is_string($binary) || $binary === '') {
            return null;
        }

        $disk = (string) config('support_chat.attachments.disk', 'public');
        $path = sprintf('support-chat/%s/%s.%s', now()->format('Y/m'), (string) Str::uuid(), $extension);
        Storage::disk($disk)->put($path, $binary);

        return [
            'attachment_url' => Storage::disk($disk)->url($path),
            'attachment_path' => $path,
            'attachment_disk' => $disk,
            'attachment_name' => (string) ($file->getClientOriginalName() ?: basename($path)),
            'attachment_mime' => $outputMime,
            'attachment_size' => strlen($binary),
            'attachment_type' => 'image',
            'attachment_width' => $targetWidth,
            'attachment_height' => $targetHeight,
            'attachment_original_mime' => $mime,
            'attachment_compressed' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function storeOriginalFile(UploadedFile $file, string $mime): array
    {
        $disk = (string) config('support_chat.attachments.disk', 'public');
        $extension = $file->getClientOriginalExtension() ?: $this->extensionFromMime($mime);
        $safeExtension = preg_replace('/[^a-zA-Z0-9]+/', '', (string) $extension) ?: 'bin';
        $path = sprintf(
            'support-chat/%s/%s.%s',
            now()->format('Y/m'),
            (string) Str::uuid(),
            strtolower($safeExtension)
        );

        Storage::disk($disk)->putFileAs(dirname($path), $file, basename($path));

        return [
            'attachment_url' => Storage::disk($disk)->url($path),
            'attachment_path' => $path,
            'attachment_disk' => $disk,
            'attachment_name' => (string) ($file->getClientOriginalName() ?: basename($path)),
            'attachment_mime' => $mime,
            'attachment_size' => (int) ($file->getSize() ?? 0),
            'attachment_type' => str_starts_with($mime, 'image/') ? 'image' : 'file',
            'attachment_compressed' => false,
        ];
    }

    private function extensionFromMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
            default => 'bin',
        };
    }
}
