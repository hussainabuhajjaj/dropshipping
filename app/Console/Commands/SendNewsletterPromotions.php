<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\NewsletterSubscriber;
use App\Services\NewsletterCampaignService;
use Illuminate\Console\Command;

class SendNewsletterPromotions extends Command
{
    protected $signature = 'newsletter:send-promotions
        {--subject= : Email subject}
        {--body= : Email body}
        {--action-url= : Call-to-action URL}
        {--action-label= : Call-to-action label}
        {--limit= : Limit number of subscribers}
        {--dry-run : Show how many subscribers would receive the email}';

    protected $description = 'Send a promotional email to newsletter subscribers.';

    public function handle(): int
    {
        $subject = $this->option('subject') ?: 'Latest offers from ' . config('app.name');
        $body = $this->option('body') ?: 'New promotions are live now. Shop the latest deals and limited-time offers.';
        $actionUrl = $this->option('action-url') ?: rtrim((string) config('app.url'), '/') . '/promotions';
        $actionLabel = $this->option('action-label') ?: 'Shop promotions';
        $limit = $this->option('limit');

        $query = NewsletterSubscriber::query()
            ->whereNotNull('email')
            ->whereNull('unsubscribed_at');

        if ($limit) {
            $query->limit((int) $limit);
        }

        $count = $query->count();
        if ($count === 0) {
            $this->warn('No newsletter subscribers found.');
            return Command::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("Dry run: {$count} subscribers would receive the email.");
            return Command::SUCCESS;
        }

        $campaign = app(NewsletterCampaignService::class)->createAndQueueCampaign([
            'subject' => $subject,
            'body_markdown' => $body,
            'action_url' => $actionUrl,
            'action_label' => $actionLabel,
        ], $query, null, false, $limit ? (int) $limit : null);

        $this->info("Campaign queued for {$campaign->total_subscribers} subscribers.");

        return Command::SUCCESS;
    }
}
