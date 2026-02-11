<?php

declare(strict_types=1);

namespace App\Support\Marketing;

use App\Models\Category;
use App\Models\MarketingContentDraft;
use App\Models\Product;
use App\Services\AI\DeepSeekClient;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class MarketingDraftGenerator
{
    public static function generateDraftFromContext(string $targetType = 'campaign', $targetId = null): ?MarketingContentDraft
    {
        if (empty(config('services.deepseek.key'))) {
            return null;
        }

        $categories = Category::query()
            ->select(['id', 'name'])
            ->withCount(['products as products_count' => function ($q) {
                $q->where('is_active', true);
            }])
            ->orderByDesc('products_count')
            ->limit(5)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'products' => $c->products_count,
            ])
            ->values()
            ->all();

        $products = Product::query()
            ->select(['id', 'name', 'selling_price', 'cost_price', 'currency', 'category_id'])
            ->where('is_active', true)
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => $p->selling_price,
                'cost_price' => $p->cost_price,
                'currency' => $p->currency,
                'category_id' => $p->category_id,
            ])
            ->values()
            ->all();

        $calendar = self::ivoryCoastSeasonalContext();

        $client = app(DeepSeekClient::class);

        try {
            $response = $client->chat([
                ['role' => 'system', 'content' => self::deepseekSystemPrompt()],
                ['role' => 'user', 'content' => json_encode([
                    'categories' => $categories,
                    'products' => $products,
                    'calendar' => $calendar,
                    'target_type' => $targetType,
                    'target_id' => $targetId,
                    'tone_defaults' => [
                        'market' => 'Côte d’Ivoire',
                        'city_focus' => 'Abidjan',
                        'currency_fr' => 'FCFA',
                        'brand_style' => 'trendy, modern, concise',
                        'no_emojis' => true,
                    ],
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)],
            ], 0.6);
        } catch (\Throwable $e) {
            logger()->error('DeepSeek generation failed', ['error' => $e->getMessage()]);
            return null;
        }

        $decoded = self::decodeResponse($response);
        $decoded = self::validateAndCoerceDraftJson($decoded);

        if (! is_array($decoded)) {
            logger()->warning('DeepSeek response not valid draft JSON', ['response' => $response]);
            return null;
        }

        $draft = MarketingContentDraft::create([
            'target_type' => $targetType,
            'target_id' => $targetId,
            'locale' => 'fr',
            'channel' => 'web',
            'generated_fields' => Arr::only($decoded, [
                'title_fr', 'hero_kicker_fr', 'hero_subtitle_fr', 'content_fr',
                'title_en', 'hero_kicker_en', 'hero_subtitle_en', 'content_en',
                'recommendations_fr', 'recommendations_en',
                'suggestions_fr', 'suggestions_en',
            ]),
            'prompt_context' => [
                'categories' => $categories,
                'products' => $products,
                'calendar' => $calendar,
                'target_type' => $targetType,
                'target_id' => $targetId,
            ],
            'status' => 'pending_review',
            'requested_by' => Auth::id(),
        ]);

        MarketingContentDraft::create([
            'target_type' => $targetType,
            'target_id' => $targetId,
            'locale' => 'en',
            'channel' => 'web',
            'generated_fields' => Arr::only($decoded, [
                'title_en', 'hero_kicker_en', 'hero_subtitle_en', 'content_en',
                'recommendations_en', 'suggestions_en',
            ]),
            'prompt_context' => [
                'categories' => $categories,
                'products' => $products,
                'calendar' => $calendar,
                'target_type' => $targetType,
                'target_id' => $targetId,
            ],
            'status' => 'pending_review',
            'requested_by' => Auth::id(),
        ]);

        return $draft;
    }

    private static function deepseekSystemPrompt(): string
    {
        return <<<SYS
You are a bilingual (French + English) e-commerce marketing specialist focused on Côte d’Ivoire (Abidjan-first).
Generate high-converting copy for a dropshipping store based on the provided context.

HARD RULES:
- Output MUST be ONLY valid JSON. No markdown, no code fences, no commentary.
- Use the EXACT JSON keys listed below. Do not add/remove keys.
- Values must be strings (except arrays). Arrays contain strings only.
- French must be localized to Côte d’Ivoire and FCFA. Use format "12 900 FCFA". Never use € or $ in FR.
- English must be clear, modern, and direct.
- Do not invent fake claims (no "official", "certified", "best in CI", etc.). No medical claims.
- Keep it concise. Avoid long paragraphs. No emojis unless explicitly requested (assume not requested).
- Always include a clear CTA inside content fields.

INPUT CONTEXT:
You will receive JSON with: categories, products, calendar, target_type, target_id, tone_defaults.
Use these to propose realistic offers and angles. If prices/currency are present, respect them.

SECTION RULES:
- target_type indicates the section format:
  campaign, banner, promotion, coupon, newsletter, popup, storefront, category_offer, flash_sale
- The copy must match the section type.
  - banner/popup: punchy, short, immediate CTA
  - newsletter: slightly longer with value + CTA
  - coupon/promotion: clear rules + benefit + urgency

OUTPUT JSON KEYS (MUST match exactly):
title_fr, hero_kicker_fr, hero_subtitle_fr, content_fr,
title_en, hero_kicker_en, hero_subtitle_en, content_en,
recommendations_fr, recommendations_en, suggestions_fr, suggestions_en

LENGTH LIMITS:
- title_*: max 8 words
- hero_kicker_*: 3–6 words
- hero_subtitle_*: max 14 words
- content_*: 2–5 short lines separated with "\\n", must include ONE CTA line
- recommendations_*: 3–6 items (array of strings)
- suggestions_*: 3–8 items (array of strings)

LOCAL TRUST ELEMENTS YOU MAY USE (only if relevant):
- "Livraison à Abidjan", "Retours faciles", "Support client rapide"
- "Paiement à la livraison" ONLY if the store supports it (do not assume).

Now produce the JSON only.
SYS;
    }

    private static function decodeResponse(string $content): ?array
    {
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $stripped = preg_replace('/^```[a-zA-Z]*\s*|```\s*$/m', '', $content);
        $decoded = json_decode($stripped ?? '', true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $slice = substr($content, $start, $end - $start + 1);
            $decoded = json_decode($slice, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private static function validateAndCoerceDraftJson(?array $decoded): ?array
    {
        if (! is_array($decoded)) {
            return null;
        }

        $requiredStringKeys = [
            'title_fr', 'hero_kicker_fr', 'hero_subtitle_fr', 'content_fr',
            'title_en', 'hero_kicker_en', 'hero_subtitle_en', 'content_en',
        ];

        $requiredArrayKeys = [
            'recommendations_fr', 'recommendations_en',
            'suggestions_fr', 'suggestions_en',
        ];

        foreach ($requiredStringKeys as $k) {
            if (! array_key_exists($k, $decoded)) {
                return null;
            }
            if (! is_string($decoded[$k])) {
                $decoded[$k] = (string) ($decoded[$k] ?? '');
            }
            $decoded[$k] = trim((string) $decoded[$k]);
        }

        foreach ($requiredArrayKeys as $k) {
            if (! array_key_exists($k, $decoded)) {
                return null;
            }
            if (! is_array($decoded[$k])) {
                $decoded[$k] = [];
            }
            $decoded[$k] = array_values(array_map(
                fn ($v) => is_string($v) ? trim($v) : trim((string) $v),
                $decoded[$k]
            ));
        }

        $decoded['recommendations_fr'] = array_slice($decoded['recommendations_fr'], 0, 6);
        $decoded['recommendations_en'] = array_slice($decoded['recommendations_en'], 0, 6);
        $decoded['suggestions_fr'] = array_slice($decoded['suggestions_fr'], 0, 8);
        $decoded['suggestions_en'] = array_slice($decoded['suggestions_en'], 0, 8);

        return $decoded;
    }

    private static function ivoryCoastSeasonalContext(): array
    {
        $now = now()->timezone('Africa/Abidjan');
        $month = (int) $now->format('n');
        $season = in_array($month, [12, 1, 2, 3, 4], true) ? 'dry' : 'rainy';

        $events = [
            1 => ['Nouvel an'],
            2 => ['Saint Valentin'],
            3 => ['Journée de la Femme'],
            4 => ['Pâques'],
            5 => ['Fête du Travail'],
            6 => ['Fête de la Musique'],
            7 => ['Vacances scolaires'],
            8 => ['Préparation rentrée scolaire'],
            9 => ['Rentrée scolaire'],
            10 => ['Toussaint'],
            11 => ['Singles Day', 'Black Friday'],
            12 => ['Noël', 'Saint-Sylvestre'],
        ];

        return [
            'month' => $now->format('F'),
            'season' => $season,
            'events' => $events[$month] ?? [],
            'currency' => 'FCFA',
        ];
    }
}
