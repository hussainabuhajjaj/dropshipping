<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification
{
    use Queueable;

    /**
     * @param array<int, array{
     *   type:string,
     *   id:int,
     *   product_id:int,
     *   product_name:string,
     *   variant_title:?string,
     *   sku:?string,
     *   stock:int,
     *   threshold:int,
     *   action_url:string
     * }> $items
     */
    public function __construct(private readonly array $items)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        $count = count($this->items);
        $first = $this->items[0] ?? null;

        return [
            'title' => 'Low stock alert',
            'body' => $count === 1
                ? $this->formatItemLine($first)
                : "{$count} products or variants are below their stock threshold.",
            'count' => $count,
            'items' => $this->items,
            'action_url' => $first['action_url'] ?? url('/admin/products'),
            'action_label' => $count === 1 ? 'View product' : 'Review low stock',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = count($this->items);

        $message = (new MailMessage())
            ->subject($count === 1 ? 'Low stock alert' : "Low stock alert ({$count} items)")
            ->line($count === 1
                ? 'One product or variant is below its stock threshold.'
                : "{$count} products or variants are below their stock threshold.");

        foreach (array_slice($this->items, 0, 10) as $item) {
            $message->line($this->formatItemLine($item));
        }

        if ($count > 10) {
            $message->line('Additional low-stock items were omitted from this email.');
        }

        $message->action('Open products admin', url('/admin/products'))
            ->line('Review inventory and replenish or disable affected items as needed.');

        return $message;
    }

    /**
     * @param array{
     *   type:string,
     *   id:int,
     *   product_id:int,
     *   product_name:string,
     *   variant_title:?string,
     *   sku:?string,
     *   stock:int,
     *   threshold:int,
     *   action_url:string
     * }|null $item
     */
    private function formatItemLine(?array $item): string
    {
        if ($item === null) {
            return 'Low stock item';
        }

        $name = $item['product_name'];
        if (($item['variant_title'] ?? null) !== null && trim((string) $item['variant_title']) !== '') {
            $name .= ' / ' . trim((string) $item['variant_title']);
        }

        $sku = trim((string) ($item['sku'] ?? ''));

        return sprintf(
            '%s%s - stock %d / threshold %d',
            $name,
            $sku !== '' ? " (SKU: {$sku})" : '',
            (int) $item['stock'],
            (int) $item['threshold']
        );
    }
}
