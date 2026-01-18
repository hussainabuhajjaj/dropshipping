<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Products\Models\Category;
use App\Domain\Products\Models\Product;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use Illuminate\Support\Str;

class NewsletterCampaignRenderer
{
    public function renderPreview(string $markdown, string $unsubscribeUrl = '#'): string
    {
        $body = $this->normalizeBody($markdown);
        $body = $this->injectProductBlocks($body);
        $body = $this->injectCategoryBlocks($body);
        $body .= "\n\n---\n\n[Unsubscribe]({$unsubscribeUrl})";

        return Str::markdown($body, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    public function renderPreviewHtml(
        string $subject,
        string $markdown,
        ?string $actionUrl = null,
        ?string $actionLabel = null
    ): string {
        $bodyHtml = $this->renderPreview($markdown);

        return view('emails.base', [
            'title' => $subject ?: config('app.name'),
            'preheader' => Str::limit(trim(strip_tags($bodyHtml)), 120),
            'bodyHtml' => $bodyHtml,
            'actionUrl' => $actionUrl,
            'actionLabel' => $actionLabel,
        ])->render();
    }

    public function render(NewsletterCampaign $campaign, NewsletterSubscriber $subscriber): string
    {
        $body = $this->normalizeBody($campaign->body_markdown);
        $body = $this->injectProductBlocks($body);
        $body = $this->injectCategoryBlocks($body);

        $unsubscribeUrl = route('newsletter.unsubscribe', ['token' => $subscriber->unsubscribe_token]);
        $body .= "\n\n---\n\n[Unsubscribe]({$unsubscribeUrl})";

        return Str::markdown($body, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    public function renderWithTracking(
        NewsletterCampaign $campaign,
        NewsletterSubscriber $subscriber,
        string $openTrackingUrl
    ): string {
        $html = $this->render($campaign, $subscriber);

        $pixel = '<img src="' . e($openTrackingUrl) . '" width="1" height="1" style="display:block; border:0; outline:none; text-decoration:none;" alt="" />';

        return $html . $pixel;
    }

    /**
     * @param mixed $body
     */
    private function normalizeBody($body): string
    {
        if (is_string($body)) {
            return $body;
        }

        if (is_array($body)) {
            return $this->extractTextFromRichContent($body);
        }

        if (is_object($body)) {
            return json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        return (string) $body;
    }

    private function extractTextFromRichContent(array $node): string
    {
        $text = '';

        if (isset($node['text']) && is_string($node['text'])) {
            $text .= $node['text'];
        }

        if (! empty($node['content']) && is_array($node['content'])) {
            foreach ($node['content'] as $child) {
                if (is_array($child)) {
                    $text .= $this->extractTextFromRichContent($child);
                }
            }
            $text .= "\n";
        }

        if ($text === '' && $node) {
            $text = json_encode($node, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        return trim($text);
    }

    private function injectProductBlocks(string $body): string
    {
        return preg_replace_callback('/\{\{\s*product:(\d+)\s*\}\}/i', function ($matches) {
            $productId = (int) $matches[1];
            $product = Product::query()->with(['images'])->find($productId);
            if (! $product) {
                return '';
            }

            $image = $product->images->sortBy('position')->first()?->url;
            $url = url('/products/' . $product->slug);

            $lines = ["### {$product->name}"];
            if ($image) {
                $lines[] = '';
                $lines[] = "![]({$image})";
            }
            $lines[] = '';
            $lines[] = "[View product]({$url})";

            return implode("\n", $lines);
        }, $body) ?? $body;
    }

    private function injectCategoryBlocks(string $body): string
    {
        return preg_replace_callback('/\{\{\s*category:(\d+)\s*\}\}/i', function ($matches) {
            $categoryId = (int) $matches[1];
            $category = Category::query()->find($categoryId);
            if (! $category) {
                return '';
            }

            $image = $category->hero_image;
            if (! $image) {
                $image = $category->products()->with('images')->first()?->images?->sortBy('position')->first()?->url;
            }

            $url = url('/categories/' . $category->slug);

            $lines = ["### {$category->name}"];
            if ($image) {
                $lines[] = '';
                $lines[] = "![]({$image})";
            }
            $lines[] = '';
            $lines[] = "[Shop category]({$url})";

            return implode("\n", $lines);
        }, $body) ?? $body;
    }
}
