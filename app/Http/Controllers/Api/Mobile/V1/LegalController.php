<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LegalController extends ApiController
{
    public function show(Request $request, string $slug): JsonResponse
    {
        $locale = $request->header('X-Locale') ?? $request->header('Accept-Language') ?? 'en';
        $settings = SiteSetting::first();

        if (!$settings) {
            return $this->notFound('Legal content not available');
        }

        $content = match ($slug) {
            'privacy' => [
                'title' => __('Privacy Policy'),
                'content' => $settings->localizedValue('privacy_policy', $locale) ?? '',
                'slug' => 'privacy',
            ],
            'terms' => [
                'title' => __('Terms of Service'),
                'content' => $settings->localizedValue('terms_of_service', $locale) ?? '',
                'slug' => 'terms',
            ],
            'about' => [
                'title' => __('About Us'),
                'content' => $settings->localizedValue('about_page_html', $locale) ?? '',
                'slug' => 'about',
            ],
            'shipping' => [
                'title' => __('Shipping Policy'),
                'content' => $settings->localizedValue('shipping_policy', $locale) ?? '',
                'slug' => 'shipping',
            ],
            'refund' => [
                'title' => __('Refund Policy'),
                'content' => $settings->localizedValue('refund_policy', $locale) ?? '',
                'slug' => 'refund',
            ],
            'customs' => [
                'title' => __('Customs Disclaimer'),
                'content' => $settings->localizedValue('customs_disclaimer', $locale) ?? '',
                'slug' => 'customs',
            ],
            default => null,
        };

        if (!$content) {
            return $this->notFound('Legal page not found');
        }

        // If content is empty, provide a fallback message
        if (empty($content['content'])) {
            $content['content'] = __('This content is not yet available. Please check back later or contact support.');
        }

        return $this->success($content);
    }

    public function index(Request $request): JsonResponse
    {
        $locale = $request->header('X-Locale') ?? $request->header('Accept-Language') ?? 'en';
        $settings = SiteSetting::first();

        if (!$settings) {
            return $this->success([
                'pages' => [],
            ]);
        }

        $pages = [
            [
                'slug' => 'privacy',
                'title' => __('Privacy Policy'),
                'has_content' => !empty($settings->localizedValue('privacy_policy', $locale)),
            ],
            [
                'slug' => 'terms',
                'title' => __('Terms of Service'),
                'has_content' => !empty($settings->localizedValue('terms_of_service', $locale)),
            ],
            [
                'slug' => 'about',
                'title' => __('About Us'),
                'has_content' => !empty($settings->localizedValue('about_page_html', $locale)),
            ],
            [
                'slug' => 'shipping',
                'title' => __('Shipping Policy'),
                'has_content' => !empty($settings->localizedValue('shipping_policy', $locale)),
            ],
            [
                'slug' => 'refund',
                'title' => __('Refund Policy'),
                'has_content' => !empty($settings->localizedValue('refund_policy', $locale)),
            ],
            [
                'slug' => 'customs',
                'title' => __('Customs Disclaimer'),
                'has_content' => !empty($settings->localizedValue('customs_disclaimer', $locale)),
            ],
        ];

        return $this->success([
            'pages' => $pages,
        ]);
    }
}
