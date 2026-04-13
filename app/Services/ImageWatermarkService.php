<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ImageWatermarkService
{
    private string $logoPath;
    private int $opacity;
    private string $position;
    private int $margin;

    public function __construct()
    {
        $this->logoPath = config('services.watermark.logo_path', 'public/images/category-default.png');
        $this->opacity = config('services.watermark.opacity', 50);
        $this->position = config('services.watermark.position', 'bottom-right');
        $this->margin = config('services.watermark.margin', 20);
    }

    /**
     * Add watermark to image data
     */
    public function addWatermark(string $imageData): string
    {
        try {
            // Check if logo exists in public directory
            $fullLogoPath = public_path(str_replace('public/', '', $this->logoPath));
            if (!file_exists($fullLogoPath)) {
                Log::warning('Watermark logo not found', ['path' => $fullLogoPath]);
                return $imageData;
            }

            if (! function_exists('imagecreatefromstring')) {
                Log::warning('GD not available; skipping watermark');
                return $imageData;
            }

            $image = @imagecreatefromstring($imageData);
            if (! $image) {
                return $imageData;
            }

            $logoBytes = @file_get_contents($fullLogoPath);
            if (! is_string($logoBytes) || $logoBytes === '') {
                imagedestroy($image);
                return $imageData;
            }

            $logo = @imagecreatefromstring($logoBytes);
            if (! $logo) {
                imagedestroy($image);
                return $imageData;
            }

            imagesavealpha($image, true);
            imagealphablending($image, true);
            imagesavealpha($logo, true);
            imagealphablending($logo, true);

            $imageDimensions = $this->getImageDimensions($image);
            $watermarkSize = $this->calculateOptimalWatermarkSize($imageDimensions, $logo);

            $scaled = imagescale($logo, $watermarkSize['width'], $watermarkSize['height'], IMG_BILINEAR_FIXED);
            if ($scaled) {
                imagedestroy($logo);
                $logo = $scaled;
            }

            $pos = $this->calculateIntelligentPosition($imageDimensions, $watermarkSize);

            $this->copyMergePreserveAlpha(
                $image,
                $logo,
                (int) round($pos['x']),
                (int) round($pos['y']),
                max(0, min(100, (int) $this->opacity))
            );

            $encoded = $this->encodeGdImage($image, $imageData);

            imagedestroy($logo);
            imagedestroy($image);

            return $encoded ?? $imageData;

        } catch (\Exception $e) {
            Log::error('Failed to add watermark', [
                'error' => $e->getMessage(),
                'logo_path' => $this->logoPath
            ]);
            return $imageData; // Return original image if watermark fails
        }
    }

    /**
     * Get image dimensions and calculate aspect ratio
     */
    private function getImageDimensions($image): array
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $aspectRatio = $width / $height;
        
        return [
            'width' => $width,
            'height' => $height,
            'aspect_ratio' => $aspectRatio,
            'is_landscape' => $aspectRatio > 1,
            'is_portrait' => $aspectRatio < 1,
            'is_square' => abs($aspectRatio - 1) < 0.1,
            'area' => $width * $height
        ];
    }

    /**
     * Calculate optimal watermark size based on image dimensions
     */
    private function calculateOptimalWatermarkSize(array $imageDimensions, $logo): array
    {
        $imageWidth = $imageDimensions['width'];
        $imageHeight = $imageDimensions['height'];
        $logoWidth = imagesx($logo);
        $logoHeight = imagesy($logo);
        
        // Base size calculations based on image dimensions
        if ($imageDimensions['is_square']) {
            // For square images, use 20% of width/height
            $maxWidth = $imageWidth * 0.20;
            $maxHeight = $imageHeight * 0.20;
        } elseif ($imageDimensions['is_landscape']) {
            // For landscape images, use 15% of width and 25% of height
            $maxWidth = $imageWidth * 0.15;
            $maxHeight = $imageHeight * 0.25;
        } else {
            // For portrait images, use 25% of width and 15% of height
            $maxWidth = $imageWidth * 0.25;
            $maxHeight = $imageHeight * 0.15;
        }
        
        // Apply absolute limits
        $maxWidth = min($maxWidth, 300);
        $maxHeight = min($maxHeight, 300);
        // Minimum 50px (kept for readability on small images).
        $maxWidth = max($maxWidth, 50);
        $maxHeight = max($maxHeight, 50);
        
        // Calculate final size maintaining aspect ratio
        $logoAspectRatio = $logoWidth / $logoHeight;
        
        if ($logoAspectRatio > 1) {
            // Logo is wider than tall
            $finalWidth = min($logoWidth, $maxWidth);
            $finalHeight = $finalWidth / $logoAspectRatio;
        } else {
            // Logo is taller than wide or square
            $finalHeight = min($logoHeight, $maxHeight);
            $finalWidth = $finalHeight * $logoAspectRatio;
        }
        
        return [
            'width' => (int) $finalWidth,
            'height' => (int) $finalHeight
        ];
    }

    /**
     * Calculate intelligent position based on image dimensions and watermark size
     */
    private function calculateIntelligentPosition(array $imageDimensions, array $watermarkSize): array
    {
        $imageWidth = $imageDimensions['width'];
        $imageHeight = $imageDimensions['height'];
        $watermarkWidth = $watermarkSize['width'];
        $watermarkHeight = $watermarkSize['height'];
        
        // Dynamic margin based on image size (1% of image dimension, minimum 10px, maximum 30px)
        $dynamicMargin = max(
            min((int) ($imageWidth * 0.01), 30),
            10
        );
        
        switch ($this->position) {
            case 'top-left':
                return [
                    'x' => $dynamicMargin,
                    'y' => $dynamicMargin
                ];

            case 'top-right':
                return [
                    'x' => $imageWidth - $watermarkWidth - $dynamicMargin,
                    'y' => $dynamicMargin
                ];

            case 'bottom-left':
                return [
                    'x' => $dynamicMargin,
                    'y' => $imageHeight - $watermarkHeight - $dynamicMargin
                ];

            case 'bottom-right':
            default:
                return [
                    'x' => $imageWidth - $watermarkWidth - $dynamicMargin,
                    'y' => $imageHeight - $watermarkHeight - $dynamicMargin
                ];

            case 'center':
                return [
                    'x' => ($imageWidth - $watermarkWidth) / 2,
                    'y' => ($imageHeight - $watermarkHeight) / 2
                ];
        }
    }

    /**
     * Calculate watermark position (legacy method for backward compatibility)
     */
    private function calculatePosition($image, $logo): array
    {
        $imageDimensions = $this->getImageDimensions($image);
        $watermarkSize = [
            'width' => imagesx($logo),
            'height' => imagesy($logo),
        ];
        
        return $this->calculateIntelligentPosition($imageDimensions, $watermarkSize);
    }

    /**
     * Check if watermark is available
     */
    public function isWatermarkAvailable(): bool
    {
        $fullLogoPath = public_path(str_replace('public/', '', $this->logoPath));
        return file_exists($fullLogoPath);
    }

    /**
     * Get watermark configuration info
     */
    public function getWatermarkInfo(): array
    {
        return [
            'logo_path' => $this->logoPath,
            'opacity' => $this->opacity,
            'position' => $this->position,
            'margin' => $this->margin,
        ];
    }

    private function encodeGdImage($image, string $originalBytes): ?string
    {
        $info = @getimagesizefromstring($originalBytes);
        $mime = is_array($info) ? ($info['mime'] ?? null) : null;

        ob_start();
        try {
            if ($mime === 'image/png') {
                imagesavealpha($image, true);
                imagepng($image);
            } elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
                imagewebp($image, null, 90);
            } else {
                imagejpeg($image, null, 90);
            }

            $out = ob_get_clean();
            return is_string($out) && $out !== '' ? $out : null;
        } catch (\Throwable) {
            ob_end_clean();
            return null;
        }
    }

    private function copyMergePreserveAlpha($dst, $src, int $dstX, int $dstY, int $opacity): void
    {
        $opacity = max(0, min(100, $opacity));
        $w = imagesx($src);
        $h = imagesy($src);

        if ($opacity >= 100) {
            imagecopy($dst, $src, $dstX, $dstY, 0, 0, $w, $h);
            return;
        }

        $tmp = imagecreatetruecolor($w, $h);
        if (! $tmp) {
            imagecopy($dst, $src, $dstX, $dstY, 0, 0, $w, $h);
            return;
        }

        imagealphablending($tmp, false);
        imagesavealpha($tmp, true);
        $transparent = imagecolorallocatealpha($tmp, 0, 0, 0, 127);
        imagefilledrectangle($tmp, 0, 0, $w, $h, $transparent);

        imagecopy($tmp, $dst, 0, 0, $dstX, $dstY, $w, $h);
        imagecopy($tmp, $src, 0, 0, 0, 0, $w, $h);

        imagecopymerge($dst, $tmp, $dstX, $dstY, 0, 0, $w, $h, $opacity);
        imagedestroy($tmp);
    }
}
