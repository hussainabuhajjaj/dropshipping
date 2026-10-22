<?php

declare(strict_types=1);

namespace App\Domain\Products\Services;

use App\Domain\Products\Models\Product;

class CjProductMediaService
{
    public function cleanDescription(?string $description): ?string
    {
        if ($description === null || $description === '') {
            return null;
        }

        $text = preg_replace('/<\s*img[^>]*>/i', ' ', $description);
        $text = preg_replace('/<\s*br\s*\/?>/i', "\n", $text);
        $text = preg_replace('/<\/p>/i', "\n", $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{2,}/', "\n", $text);
        $text = trim($text);

        return $text === '' ? null : $text;
    }

    public function syncImages(Product $product, array $productData, ?array $variants = null): bool
    {
        $urls = $this->collectImageUrls($productData, $variants);

        if ($urls === []) {
            return false;
        }

        $product->images()->delete();

        foreach ($urls as $index => $url) {
            $product->images()->create([
                'url' => $url,
                'position' => $index + 1,
            ]);
        }

        return true;
    }

    public function extractImageUrls(array $productData, ?array $variants = null): array
    {
        return $this->collectImageUrls($productData, $variants);
    }

    public function syncVideos(Product $product, array $productData, ?array $variants = null): bool
    {
        $urls = $this->collectVideoUrls($productData, $variants);

        if ($urls === []) {
            return false;
        }

        $product->update([
            'cj_video_urls' => $urls,
        ]);

        return true;
    }

    public function extractVideoUrls(array $productData, ?array $variants = null): array
    {
        return $this->collectVideoUrls($productData, $variants);
    }

    private function collectImageUrls(array $productData, ?array $variants = null): array
    {
        $urls = [];
        $seen = [];

        $addUrl = function (string $url) use (&$urls, &$seen): void {
            $normalized = $this->normalizeUrl($url);
            if ($normalized === null || isset($seen[$normalized])) {
                return;
            }

            $seen[$normalized] = true;
            $urls[] = $normalized;
        };

        $extract = function ($value) use (&$extract, $addUrl): void {
            if (is_string($value)) {
                $this->addFromString($value, $addUrl);
                return;
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    $extract($item);
                }
            }
        };

        $scan = function (array $payload) use (&$scan, $extract): void {
            foreach ($payload as $key => $value) {
                if (! is_string($key)) {
                    if (is_array($value)) {
                        $scan($value);
                    }
                    continue;
                }

                $lower = strtolower($key);
                $isImageKey = str_contains($lower, 'image') || str_starts_with($lower, 'img');
                $isDescriptionKey = str_contains($lower, 'description') || str_contains($lower, 'desc');
                if (! $isImageKey && ! $isDescriptionKey) {
                    if (is_array($value)) {
                        $scan($value);
                    }
                    continue;
                }

                $extract($value);

                if (is_array($value)) {
                    $scan($value);
                }
            }
        };

        $priorityKeys = [
            'productImage',
            'productImageList',
            'productImageSet',
            'productImageSetList',
            'productImages',
            'productImg',
            'productDescription',
            'productDescriptionEn',
            'image',
            'images',
            'imageList',
            'description',
            'descriptionEn',
        ];

        foreach ($priorityKeys as $key) {
            if (array_key_exists($key, $productData)) {
                $extract($productData[$key]);
            }
        }

        $scan($productData);

        if (is_array($variants)) {
            foreach ($variants as $variant) {
                if (is_array($variant)) {
                    $scan($variant);
                }
            }
        }

        return $urls;
    }

    private function collectVideoUrls(array $productData, ?array $variants = null): array
    {
        $urls = [];
        $seen = [];

        $addUrl = function (string $url) use (&$urls, &$seen): void {
            $normalized = $this->normalizeUrl($url);
            if ($normalized === null || isset($seen[$normalized])) {
                return;
            }

            $seen[$normalized] = true;
            $urls[] = $normalized;
        };

        $extract = function ($value) use (&$extract, $addUrl): void {
            if (is_string($value)) {
                $this->addVideoFromString($value, $addUrl);
                return;
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    $extract($item);
                }
            }
        };

        $scan = function (array $payload) use (&$scan, $extract): void {
            foreach ($payload as $key => $value) {
                if (! is_string($key)) {
                    if (is_array($value)) {
                        $scan($value);
                    }
                    continue;
                }

                $lower = strtolower($key);
                $isVideoKey = str_contains($lower, 'video');

                if ($isVideoKey) {
                    $extract($value);
                } elseif (is_string($value) && $this->stringLooksLikeVideo($value)) {
                    $extract($value);
                }

                if (is_array($value)) {
                    $scan($value);
                }
            }
        };

        $priorityKeys = [
            'productVideo',
            'productVideoUrl',
            'productVideoList',
            'productVideos',
            'video',
            'videos',
            'videoUrl',
            'videoUrls',
        ];

        foreach ($priorityKeys as $key) {
            if (array_key_exists($key, $productData)) {
                $extract($productData[$key]);
            }
        }

        $scan($productData);

        if (is_array($variants)) {
            foreach ($variants as $variant) {
                if (is_array($variant)) {
                    $scan($variant);
                }
            }
        }

        return $urls;
    }

    private function addFromString(string $value, callable $addUrl): void
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return;
        }

        if (str_contains($trimmed, '<img')) {
            preg_match_all('/<img[^>]+src=["\\\']([^"\\\']+)["\\\']/i', $trimmed, $matches);
            foreach ($matches[1] ?? [] as $src) {
                $addUrl($src);
            }
            return;
        }

        $parts = preg_split('/\\s*,\\s*/', $trimmed);
        if (! $parts) {
            return;
        }

        foreach ($parts as $part) {
            $addUrl($part);
        }
    }

    private function addVideoFromString(string $value, callable $addUrl): void
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return;
        }

        if (str_contains($trimmed, '<video') || str_contains($trimmed, '<source')) {
            preg_match_all('/<(?:video|source)[^>]+src=["\\\']([^"\\\']+)["\\\']/i', $trimmed, $matches);
            foreach ($matches[1] ?? [] as $src) {
                $addUrl($src);
            }
            return;
        }

        $parts = preg_split('/\\s*,\\s*/', $trimmed);
        if (! $parts) {
            return;
        }

        foreach ($parts as $part) {
            $addUrl($part);
        }
    }

    private function stringLooksLikeVideo(string $value): bool
    {
        $lower = strtolower($value);

        if (str_contains($lower, '<video') || str_contains($lower, '<source')) {
            return true;
        }

        return (bool) preg_match('/\\.(mp4|mov|webm|m4v)(\\?|$)/i', $lower);
    }

    private function normalizeUrl(string $value): ?string
    {
        $url = trim($value);

        if ($url === '') {
            return null;
        }

        if (str_contains($url, '<')) {
            return null;
        }

        if (str_starts_with($url, '//')) {
            $url = 'https:' . $url;
        }

        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            return null;
        }

        return $url;
    }
}
