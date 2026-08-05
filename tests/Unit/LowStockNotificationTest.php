<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Notifications\LowStockNotification;
use stdClass;
use Tests\TestCase;

class LowStockNotificationTest extends TestCase
{
    public function test_broadcast_payload_stays_under_pusher_limit_with_many_items(): void
    {
        $items = [];
        for ($i = 0; $i < 100; $i++) {
            $items[] = [
                'type' => 'variant',
                'id' => $i,
                'product_id' => $i,
                'product_name' => 'Long product name number '.$i,
                'variant_title' => 'Very long variant title for item '.$i,
                'sku' => 'SKU-'.$i.'-with-a-rather-long-suffix',
                'stock' => 1,
                'threshold' => 5,
                'action_url' => '/admin/products/'.$i,
            ];
        }

        $notification = new LowStockNotification($items);

        $broadcast = $notification->toBroadcast(new stdClass());

        $this->assertLessThan(10240, strlen(json_encode($broadcast)));
        $this->assertArrayNotHasKey('items', $broadcast);
        $this->assertSame(100, $broadcast['count']);
        $this->assertSame('Low stock alert', $broadcast['title']);

        $database = $notification->toArray(new stdClass());
        $this->assertCount(100, $database['items']);
        $this->assertSame(100, $database['count']);
    }

    public function test_broadcast_payload_for_single_item(): void
    {
        $notification = new LowStockNotification([
            [
                'type' => 'product',
                'id' => 1,
                'product_id' => 1,
                'product_name' => 'Single Product',
                'variant_title' => null,
                'sku' => 'SKU-1',
                'stock' => 2,
                'threshold' => 5,
                'action_url' => '/admin/products/1',
            ],
        ]);

        $broadcast = $notification->toBroadcast(new stdClass());

        $this->assertSame(1, $broadcast['count']);
        $this->assertStringContainsString('stock 2 / threshold 5', $broadcast['body']);
        $this->assertSame('View product', $broadcast['action_label']);
    }
}
