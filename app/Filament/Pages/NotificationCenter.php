<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Products\Models\Product as DomainProduct;
use App\Models\Category;
use App\Models\Customer;
use App\Models\NewsletterSubscriber;
use App\Models\Promotion;
use App\Models\User;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\System\ManualNotification;
use App\Services\NewsletterCampaignService;
use BackedEnum;
use Filament\Notifications\Notification;
use App\Filament\Pages\BasePage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use UnitEnum;
use YieldStudio\LaravelExpoNotifier\Models\ExpoToken;

class NotificationCenter extends BasePage
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-paper-airplane';
    protected static UnitEnum|string|null $navigationGroup = 'Operations';
    protected static ?int $navigationSort = 96;

    protected string $view = 'filament.pages.notification-center';

    public string $notificationTitle = '';
    public string $body = '';
    public ?string $actionUrl = null;
    public ?string $actionLabel = null;
    public string $audience = 'customers';
    public bool $sendToAll = true;
    public string $recipientEmails = '';
    public bool $sendDatabase = true;
    public bool $sendPush = true;
    public bool $sendMail = false;
    public bool $sendWhatsApp = false;
    public string $targetType = 'custom';
    public ?string $targetIdentifier = null;
    public ?string $imageUrl = null;

    public function send(): void
    {
        $this->validate([
            'notificationTitle' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:1000'],
            'actionUrl' => ['nullable', 'url', 'max:500'],
            'actionLabel' => ['nullable', 'string', 'max:80'],
            'audience' => ['required', 'in:customers,admins,both,newsletter'],
            'recipientEmails' => ['nullable', 'string', 'max:2000'],
            'sendDatabase' => ['boolean'],
            'sendPush' => ['boolean'],
            'sendMail' => ['boolean'],
            'sendWhatsApp' => ['boolean'],
            'targetType' => ['required', 'in:custom,product,promotion,category'],
            'targetIdentifier' => ['nullable', 'string', 'max:255'],
            'imageUrl' => ['nullable', 'url', 'max:500'],
        ]);

        if ($this->audience === 'newsletter') {
            $this->sendNewsletterCampaign();
            return;
        }

        $channels = $this->buildChannels();
        if (empty($channels)) {
            Notification::make()
                ->title('Choose at least one channel')
                ->danger()
                ->send();
            return;
        }

        $recipients = $this->resolveRecipients();
        if ($recipients->isEmpty()) {
            Notification::make()
                ->title('No recipients found')
                ->warning()
                ->send();
            return;
        }

        [$resolvedActionUrl, $targetPayload] = $this->resolveTargetMetadata();

        $notification = new ManualNotification(
            title: $this->notificationTitle,
            body: $this->body,
            actionUrl: $resolvedActionUrl,
            actionLabel: $this->actionLabel,
            payload: $targetPayload,
            channels: $channels,
        );

        $recipients->chunk(200)->each(function (Collection $chunk) use ($notification) {
            NotificationFacade::send($chunk, $notification);
        });

        $recipientCount = $recipients->count();
        $customerIds = $recipients
            ->filter(fn ($recipient): bool => $recipient instanceof Customer)
            ->pluck('id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
        $pushTokenCustomers = $this->sendPush && $customerIds !== []
            ? ExpoToken::query()
                ->where('owner_type', app(Customer::class)->getMorphClass())
                ->whereIn('owner_id', $customerIds)
                ->distinct('owner_id')
                ->count('owner_id')
            : 0;

        $this->reset([
            'notificationTitle',
            'body',
            'actionUrl',
            'actionLabel',
            'recipientEmails',
            'targetIdentifier',
            'imageUrl',
        ]);
        $this->targetType = 'custom';

        Notification::make()
            ->title('Notification sent')
            ->body("Queued for {$recipientCount} recipients" . ($this->sendPush ? " · Push-capable customers: {$pushTokenCustomers}" : '') . '.')
            ->success()
            ->send();
    }

    /**
     * @return array<int, string|class-string>
     */
    private function buildChannels(): array
    {
        $channels = [];

        if ($this->sendDatabase) {
            $channels[] = 'database';
        }

        if ($this->sendPush) {
            $channels[] = 'push';
        }

        if ($this->sendMail) {
            $channels[] = 'mail';
        }

        if ($this->sendWhatsApp) {
            $channels[] = WhatsAppChannel::class;
        }

        return $channels;
    }

    /**
     * @return Collection<int, \Illuminate\Contracts\Auth\Authenticatable>
     */
    private function resolveRecipients(): Collection
    {
        $emails = collect(preg_split('/[\s,]+/', $this->recipientEmails ?? '', -1, PREG_SPLIT_NO_EMPTY))
            ->filter()
            ->unique()
            ->values();

        $recipients = collect();

        if ($this->audience === 'customers' || $this->audience === 'both') {
            if ($this->sendToAll && $emails->isEmpty()) {
                $recipients = $recipients->merge(Customer::query()->get());
            } elseif ($emails->isNotEmpty()) {
                $recipients = $recipients->merge(Customer::query()->whereIn('email', $emails)->get());
            }
        }

        if ($this->audience === 'admins' || $this->audience === 'both') {
            $adminQuery = User::query()->whereIn('role', ['admin', 'staff']);
            if ($emails->isNotEmpty()) {
                $adminQuery->whereIn('email', $emails);
            }
            $recipients = $recipients->merge($adminQuery->get());
        }

        return $recipients->unique('email')->values();
    }

    private function resolveTargetMetadata(): array
    {
        $actionUrl = $this->actionUrl;
        $payload = [
            'target_type' => $this->targetType,
            'target_identifier' => $this->targetIdentifier,
        ];

        if ($this->imageUrl) {
            $payload['image_url'] = $this->imageUrl;
        }

        $base = $this->frontendBaseUrl();

        if ($this->targetType === 'product' && $this->targetIdentifier) {
            $query = DomainProduct::query()
                ->with('images')
                ->where('slug', $this->targetIdentifier);
            if (ctype_digit($this->targetIdentifier)) {
                $query->orWhere('id', (int) $this->targetIdentifier);
            }
            $product = $query->first();
            if ($product) {
                $payload['target_id'] = $product->id;
                $payload['target_slug'] = $product->slug;
                $payload['image_url'] = $payload['image_url'] ?? optional($product->images?->first())->url;
                $resolved = "{$base}/products/{$product->slug}";
                $actionUrl = $actionUrl ?: $resolved;
            }
        } elseif ($this->targetType === 'promotion' && $this->targetIdentifier) {
            $query = Promotion::query()->where('slug', $this->targetIdentifier);
            if (ctype_digit($this->targetIdentifier)) {
                $query->orWhere('id', (int) $this->targetIdentifier);
            }
            $promotion = $query->first();
            if ($promotion) {
                $payload['target_id'] = $promotion->id;
                $payload['target_slug'] = $promotion->slug;
                $payload['image_url'] = $payload['image_url'] ?? $promotion->hero_image;
                $resolved = "{$base}/shop?promotion={$promotion->slug}";
                $actionUrl = $actionUrl ?: $resolved;
            }
        } elseif ($this->targetType === 'category' && $this->targetIdentifier) {
            $query = Category::query()->where('slug', $this->targetIdentifier);
            if (ctype_digit($this->targetIdentifier)) {
                $query->orWhere('id', (int) $this->targetIdentifier);
            }
            $category = $query->first();
            if ($category) {
                $payload['target_id'] = $category->id;
                $payload['target_slug'] = $category->slug;
                $resolved = "{$base}/shop?category={$category->slug}";
                $actionUrl = $actionUrl ?: $resolved;
            }
        }

        return [$actionUrl, array_filter($payload, fn ($value) => $value !== null && $value !== '')];
    }

    public function targetIdentifierLabel(): string
    {
        return match ($this->targetType) {
            'product' => 'Product slug or ID',
            'promotion' => 'Promotion slug or ID',
            'category' => 'Category slug or ID',
            default => 'Target identifier (optional)',
        };
    }

    public function targetIdentifierPlaceholder(): string
    {
        return match ($this->targetType) {
            'product' => 'e.g. summer-dress or 1258',
            'promotion' => 'e.g. valentines-sale or 42',
            'category' => 'e.g. womens-fashion or 11',
            default => 'Leave empty to rely on custom URL',
        };
    }

    public function targetIdentifierHint(): string
    {
        return match ($this->targetType) {
            'custom' => 'Optional when you type a custom action URL.',
            'product' => 'Selecting a product auto-populates the action URL and payload.',
            'promotion' => 'Slugs can include letters, and IDs allow fallback if slug is missing.',
            'category' => 'Use the slug shown in the Categories list (or the numeric ID).',
            default => '',
        };
    }

    private function frontendBaseUrl(): string
    {
        return rtrim((string) config('app.frontend_url', config('app.url')), '/');
    }

    private function sendNewsletterCampaign(): void
    {
        if (! $this->sendMail) {
            Notification::make()
                ->title('Newsletter requires Email channel')
                ->warning()
                ->send();
            return;
        }

        $emails = collect(preg_split('/[\s,]+/', $this->recipientEmails ?? '', -1, PREG_SPLIT_NO_EMPTY))
            ->filter()
            ->unique()
            ->values();

        $query = NewsletterSubscriber::query()
            ->whereNotNull('email')
            ->whereNull('unsubscribed_at');

        if ($emails->isNotEmpty()) {
            $query->whereIn('email', $emails);
        } elseif (! $this->sendToAll) {
            Notification::make()
                ->title('No recipients selected')
                ->warning()
                ->send();
            return;
        }

        if (! $query->exists()) {
            Notification::make()
                ->title('No newsletter subscribers found')
                ->warning()
                ->send();
            return;
        }

        $campaign = app(NewsletterCampaignService::class)->createAndQueueCampaign([
            'subject' => $this->notificationTitle,
            'body_markdown' => $this->body,
            'action_url' => $this->actionUrl,
            'action_label' => $this->actionLabel,
        ], $query, auth()->user(), false);

        $this->reset(['notificationTitle', 'body', 'actionUrl', 'actionLabel', 'recipientEmails']);

        Notification::make()
            ->title('Newsletter campaign queued')
            ->body("Queued for {$campaign->total_subscribers} subscribers.")
            ->success()
            ->send();
    }
}
