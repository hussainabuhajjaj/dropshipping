<?php

namespace Tests\Feature\Api\Mobile\V1;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifications_list_and_mark_read(): void
    {
        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        $notification = DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\Orders\\OrderConfirmedNotification',
            'notifiable_id' => $customer->id,
            'notifiable_type' => Customer::class,
            'data' => [
                'order_number' => 'DS-1001',
                'currency' => 'USD',
                'total' => 99.99,
            ],
            'read_at' => null,
        ]);

        $list = $this->getJson('/api/mobile/v1/notifications');
        $list->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    ['id', 'title', 'body', 'created_at'],
                ],
                'meta' => ['currentPage', 'lastPage', 'perPage', 'total', 'unreadCount'],
            ]);

        $mark = $this->postJson('/api/mobile/v1/notifications/mark-read', [
            'id' => $notification->id,
        ]);

        $mark->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ok', true);

        $this->assertNotNull($notification->fresh()->read_at);

        $markAgain = $this->postJson('/api/mobile/v1/notifications/mark-read', [
            'id' => $notification->id,
        ]);

        $markAgain->assertOk()->assertJsonPath('success', true);
    }
}
