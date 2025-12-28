<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ReviewHelpfulVote;
use App\Models\Shipment;
use App\Notifications\ReviewRequestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReviewSystemTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;
    private Product $product;
    private Order $order;
    private OrderItem $orderItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::factory()->create();
        $this->product = Product::factory()->create();

        $this->order = Order::factory()
            ->for($this->customer)
            ->create(['status' => 'fulfilled']);

        $this->orderItem = OrderItem::factory()
            ->for($this->order)
            ->for($this->product)
            ->create([
                'fulfillment_status' => 'fulfilled',
            ]);

        // Create fulfilled shipment
        Shipment::factory()
            ->for($this->orderItem)
            ->create(['delivered_at' => now()->subDays(10)]);
    }

    public function test_customer_can_submit_review(): void
    {
        $response = $this->actingAs($this->customer, 'customer')
            ->post(route('products.reviews.store', $this->product), [
                'order_item_id' => $this->orderItem->id,
                'rating' => 5,
                'title' => 'Great product!',
                'body' => 'This product exceeded my expectations.',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('product_reviews', [
            'product_id' => $this->product->id,
            'customer_id' => $this->customer->id,
            'rating' => 5,
            'title' => 'Great product!',
            'helpful_count' => 0,
            'verified_purchase' => true,
        ]);
    }

    public function test_helpful_vote_idempotency(): void
    {
        $review = ProductReview::factory()
            ->for($this->product)
            ->for($this->customer)
            ->create();

        // First vote should succeed
        $response1 = $this->actingAs($this->customer, 'customer')
            ->postJson(route('reviews.helpful', $review));

        $response1->assertOk();
        $response1->assertJsonPath('success', true);
        $response1->assertJsonPath('helpful_count', 1);

        $this->assertDatabaseCount('review_helpful_votes', 1);

        // Second vote should fail (already voted)
        $response2 = $this->actingAs($this->customer, 'customer')
            ->postJson(route('reviews.helpful', $review));

        $response2->assertStatus(409);
        $response2->assertJsonPath('error', 'Already voted');

        // Database should still have only 1 vote
        $this->assertDatabaseCount('review_helpful_votes', 1);
        $review->refresh();
        $this->assertEquals(1, $review->helpful_count);
    }

    public function test_guest_can_vote_helpful_by_ip(): void
    {
        $review = ProductReview::factory()
            ->for($this->product)
            ->create();

        // Guest vote (no authentication)
        $response = $this->postJson(route('reviews.helpful', $review));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('helpful_count', 1);

        // Check vote was recorded with IP
        $this->assertDatabaseHas('review_helpful_votes', [
            'review_id' => $review->id,
            'customer_id' => null,
            'ip_address' => '127.0.0.1',
        ]);
    }

    public function test_different_customers_can_vote_separately(): void
    {
        $customer2 = Customer::factory()->create();
        $review = ProductReview::factory()
            ->for($this->product)
            ->create();

        // Customer 1 votes
        $response1 = $this->actingAs($this->customer, 'customer')
            ->postJson(route('reviews.helpful', $review));

        $response1->assertOk();
        $this->assertEquals(1, $response1->json('helpful_count'));

        // Customer 2 votes
        $response2 = $this->actingAs($customer2, 'customer')
            ->postJson(route('reviews.helpful', $review));

        $response2->assertOk();
        $this->assertEquals(2, $response2->json('helpful_count'));

        // Verify 2 votes in database
        $this->assertDatabaseCount('review_helpful_votes', 2);
        $review->refresh();
        $this->assertEquals(2, $review->helpful_count);
    }

    public function test_review_request_notification_sent_after_delay(): void
    {
        Notification::fake();

        // Create an order item delivered 8 days ago
        $oldOrderItem = OrderItem::factory()
            ->for($this->order)
            ->for($this->product)
            ->create(['fulfillment_status' => 'fulfilled']);

        Shipment::factory()
            ->for($oldOrderItem)
            ->create(['delivered_at' => now()->subDays(8)]);

        // Dispatch the job
        \App\Jobs\RequestProductReviewJob::dispatch();

        // Verify notification was sent
        Notification::assertSentTo(
            [$this->customer],
            ReviewRequestNotification::class
        );
    }

    public function test_review_with_auto_approve(): void
    {
        $settings = \App\Models\SiteSetting::first();
        $settings->update(['auto_approve_reviews' => true]);

        $response = $this->actingAs($this->customer, 'customer')
            ->post(route('products.reviews.store', $this->product), [
                'order_item_id' => $this->orderItem->id,
                'rating' => 4,
                'title' => 'Good',
                'body' => 'Very good product.',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('product_reviews', [
            'product_id' => $this->product->id,
            'status' => 'approved',
        ]);
    }

    public function test_cannot_review_unfulfilled_item(): void
    {
        $unfulfilledItem = OrderItem::factory()
            ->for($this->order)
            ->for($this->product)
            ->create(['fulfillment_status' => 'pending']);

        $response = $this->actingAs($this->customer, 'customer')
            ->post(route('products.reviews.store', $this->product), [
                'order_item_id' => $unfulfilledItem->id,
                'rating' => 5,
                'title' => 'Great',
                'body' => 'Excellent product.',
            ]);

        $response->assertNotFound();
    }

    public function test_cannot_submit_duplicate_review(): void
    {
        // Submit first review
        $this->actingAs($this->customer, 'customer')
            ->post(route('products.reviews.store', $this->product), [
                'order_item_id' => $this->orderItem->id,
                'rating' => 5,
                'title' => 'Great',
                'body' => 'Excellent product.',
            ]);

        // Try to submit second review for same item
        $response = $this->actingAs($this->customer, 'customer')
            ->post(route('products.reviews.store', $this->product), [
                'order_item_id' => $this->orderItem->id,
                'rating' => 4,
                'title' => 'Good',
                'body' => 'Good product.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('order_item_id');

        $this->assertDatabaseCount('product_reviews', 1);
    }
}
