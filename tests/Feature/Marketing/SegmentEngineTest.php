<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Domain\Orders\Models\Order;
use App\Services\SegmentEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SegmentEngineTest extends TestCase
{
    use RefreshDatabase;

    private SegmentEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        App::setLocale('en');

        if (DB::connection()->getDriverName() === 'sqlite') {
            if (! Schema::hasColumn('orders', 'placed_at')) {
                Schema::table('orders', function ($table): void {
                    $table->timestamp('placed_at')->nullable();
                });
            }
            if (! Schema::hasColumn('orders', 'customer_status')) {
                Schema::table('orders', function ($table): void {
                    $table->string('customer_status')->nullable();
                });
            }
            if (! Schema::hasColumn('products', 'searchable_text')) {
                Schema::table('products', function ($table): void {
                    $table->text('searchable_text')->nullable();
                });
            }
        }

        $this->engine = app(SegmentEngine::class);
    }

    public function test_matches_locale(): void
    {
        $customer = Customer::factory()->create(['locale' => 'fr']);

        $segment = CustomerSegment::factory()->create([
            'is_active' => true,
            'rules' => [
                'operator' => 'and',
                'conditions' => [
                    ['field' => 'locale', 'operator' => 'eq', 'value' => 'fr'],
                ],
            ],
        ]);

        $this->assertTrue($this->engine->matches($customer, $segment));
    }

    public function test_matches_locale_fails_for_wrong_locale(): void
    {
        $customer = Customer::factory()->create(['locale' => 'en']);

        $segment = CustomerSegment::factory()->create([
            'is_active' => true,
            'rules' => [
                'operator' => 'and',
                'conditions' => [
                    ['field' => 'locale', 'operator' => 'eq', 'value' => 'fr'],
                ],
            ],
        ]);

        $this->assertFalse($this->engine->matches($customer, $segment));
    }

    public function test_matches_multiple_conditions_with_and(): void
    {
        $customer = Customer::factory()->create([
            'locale' => 'fr',
            'country_code' => 'CI',
            'marketing_opt_in' => true,
        ]);

        $segment = CustomerSegment::factory()->create([
            'is_active' => true,
            'rules' => [
                'operator' => 'and',
                'conditions' => [
                    ['field' => 'locale', 'operator' => 'eq', 'value' => 'fr'],
                    ['field' => 'country_code', 'operator' => 'eq', 'value' => 'CI'],
                    ['field' => 'marketing_opt_in', 'operator' => 'eq', 'value' => true],
                ],
            ],
        ]);

        $this->assertTrue($this->engine->matches($customer, $segment));
    }

    public function test_matches_with_or_operator(): void
    {
        $customer = Customer::factory()->create([
            'locale' => 'en',
            'country_code' => 'US',
            'city' => 'New York',
        ]);

        $segment = CustomerSegment::factory()->create([
            'is_active' => true,
            'rules' => [
                'operator' => 'or',
                'conditions' => [
                    ['field' => 'locale', 'operator' => 'eq', 'value' => 'fr'],
                    ['field' => 'city', 'operator' => 'eq', 'value' => 'New York'],
                ],
            ],
        ]);

        $this->assertTrue($this->engine->matches($customer, $segment));
    }

    public function test_matches_nested_groups(): void
    {
        $customer = Customer::factory()->create([
            'locale' => 'fr',
            'country_code' => 'CI',
            'city' => 'Abidjan',
        ]);

        $segment = CustomerSegment::factory()->create([
            'is_active' => true,
            'rules' => [
                'operator' => 'and',
                'conditions' => [
                    ['field' => 'locale', 'operator' => 'eq', 'value' => 'fr'],
                    [
                        'group' => [
                            'operator' => 'or',
                            'conditions' => [
                                ['field' => 'city', 'operator' => 'eq', 'value' => 'Abidjan'],
                                ['field' => 'city', 'operator' => 'eq', 'value' => 'Yamoussoukro'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($this->engine->matches($customer, $segment));
    }

    public function test_matches_all_operators(): void
    {
        $customer = Customer::factory()->create([
            'city' => 'Abidjan',
            'region' => 'Lagunes',
            'marketing_opt_in' => false,
        ]);

        $tests = [
            // [field, operator, value, expected]
            ['city', 'eq', 'Abidjan', true],
            ['city', 'neq', 'Paris', true],
            ['city', 'neq', 'Abidjan', false],
            ['city', 'contains', 'bidj', true],
            ['city', 'starts_with', 'Abi', true],
            ['city', 'ends_with', 'an', true],
            ['marketing_opt_in', 'eq', false, true],
            ['city', 'in', ['Abidjan', 'Paris'], true],
            ['city', 'not_in', ['Paris', 'London'], true],
            ['city', 'is_null', null, false],
            ['city', 'is_not_null', null, true],
        ];

        foreach ($tests as [$field, $op, $value, $expected]) {
            $segment = CustomerSegment::factory()->create([
                'is_active' => true,
                'rules' => [
                    'operator' => 'and',
                    'conditions' => [
                        ['field' => $field, 'operator' => $op, 'value' => $value],
                    ],
                ],
            ]);

            $this->assertSame($expected, $this->engine->matches($customer, $segment), "Failed: {$field} {$op} " . json_encode($value));
        }
    }

    public function test_inactive_segment_never_matches(): void
    {
        $customer = Customer::factory()->create(['locale' => 'fr']);

        $segment = CustomerSegment::factory()->create([
            'is_active' => false,
            'rules' => [
                'operator' => 'and',
                'conditions' => [
                    ['field' => 'locale', 'operator' => 'eq', 'value' => 'fr'],
                ],
            ],
        ]);

        $this->assertFalse($this->engine->matches($customer, $segment));
    }

    public function test_empty_rules_never_match(): void
    {
        $customer = Customer::factory()->create();

        $segment = CustomerSegment::factory()->create([
            'is_active' => true,
            'rules' => null,
        ]);

        $this->assertFalse($this->engine->matches($customer, $segment));
    }

    public function test_query_filters_by_basic_field(): void
    {
        Customer::factory()->create(['locale' => 'fr']);
        Customer::factory()->create(['locale' => 'en']);

        $segment = CustomerSegment::factory()->create([
            'is_active' => true,
            'rules' => [
                'operator' => 'and',
                'conditions' => [
                    ['field' => 'locale', 'operator' => 'eq', 'value' => 'fr'],
                ],
            ],
        ]);

        $this->assertSame(1, $this->engine->count($segment));
    }

    public function test_count_aggregate_total_spent(): void
    {
        $customer = Customer::factory()->create();
        Order::factory()->create([
            'customer_id' => $customer->id,
            'payment_status' => 'paid',
            'grand_total' => 200,
            'placed_at' => now()->subDays(5),
        ]);

        $segment = CustomerSegment::factory()->create([
            'is_active' => true,
            'rules' => [
                'operator' => 'and',
                'conditions' => [
                    ['field' => 'total_spent', 'operator' => 'gte', 'value' => 100],
                ],
            ],
        ]);

        $this->assertSame(1, $this->engine->count($segment));
    }

    public function test_count_aggregate_total_spent_excludes(): void
    {
        $customer = Customer::factory()->create();
        Order::factory()->create([
            'customer_id' => $customer->id,
            'payment_status' => 'paid',
            'grand_total' => 50,
            'placed_at' => now()->subDays(5),
        ]);

        $segment = CustomerSegment::factory()->create([
            'is_active' => true,
            'rules' => [
                'operator' => 'and',
                'conditions' => [
                    ['field' => 'total_spent', 'operator' => 'gte', 'value' => 100],
                ],
            ],
        ]);

        $this->assertSame(0, $this->engine->count($segment));
    }

    public function test_count_aggregate_order_count(): void
    {
        $customer = Customer::factory()->create();
        Order::factory()->count(3)->create([
            'customer_id' => $customer->id,
            'payment_status' => 'paid',
            'grand_total' => 50,
            'placed_at' => now()->subDays(5),
        ]);

        $segment = CustomerSegment::factory()->create([
            'is_active' => true,
            'rules' => [
                'operator' => 'and',
                'conditions' => [
                    ['field' => 'order_count', 'operator' => 'gte', 'value' => 3],
                ],
            ],
        ]);

        $this->assertSame(1, $this->engine->count($segment));
    }

    public function test_count_aggregate_order_count_excludes(): void
    {
        $customer = Customer::factory()->create();
        Order::factory()->count(2)->create([
            'customer_id' => $customer->id,
            'payment_status' => 'paid',
            'grand_total' => 50,
            'placed_at' => now()->subDays(5),
        ]);

        $segment = CustomerSegment::factory()->create([
            'is_active' => true,
            'rules' => [
                'operator' => 'and',
                'conditions' => [
                    ['field' => 'order_count', 'operator' => 'gte', 'value' => 3],
                ],
            ],
        ]);

        $this->assertSame(0, $this->engine->count($segment));
    }

    public function test_count_aggregate_last_order_days(): void
    {
        $customer = Customer::factory()->create();
        Order::factory()->create([
            'customer_id' => $customer->id,
            'payment_status' => 'paid',
            'grand_total' => 50,
            'placed_at' => now()->subDays(60),
        ]);

        $segment = CustomerSegment::factory()->create([
            'is_active' => true,
            'rules' => [
                'operator' => 'and',
                'conditions' => [
                    ['field' => 'last_order_days', 'operator' => 'gte', 'value' => 30],
                ],
            ],
        ]);

        $this->assertSame(1, $this->engine->count($segment));
    }

    public function test_count_aggregate_last_order_days_excludes_recent(): void
    {
        $customer = Customer::factory()->create();
        Order::factory()->create([
            'customer_id' => $customer->id,
            'payment_status' => 'paid',
            'grand_total' => 50,
            'placed_at' => now()->subDays(5),
        ]);

        $segment = CustomerSegment::factory()->create([
            'is_active' => true,
            'rules' => [
                'operator' => 'and',
                'conditions' => [
                    ['field' => 'last_order_days', 'operator' => 'gte', 'value' => 30],
                ],
            ],
        ]);

        $this->assertSame(0, $this->engine->count($segment));
    }

    public function test_matches_aggregate_total_spent(): void
    {
        $customer = Customer::factory()->create();
        Order::factory()->create([
            'customer_id' => $customer->id,
            'payment_status' => 'paid',
            'grand_total' => 250,
            'placed_at' => now()->subDays(5),
        ]);

        $segment = CustomerSegment::factory()->create([
            'is_active' => true,
            'rules' => [
                'operator' => 'and',
                'conditions' => [
                    ['field' => 'total_spent', 'operator' => 'gte', 'value' => 200],
                    ['field' => 'avg_order_value', 'operator' => 'gte', 'value' => 200],
                    ['field' => 'max_order_value', 'operator' => 'gte', 'value' => 200],
                ],
            ],
        ]);

        $this->assertTrue($this->engine->matches($customer, $segment));
    }

    public function test_customer_ids_returns_matching_ids(): void
    {
        $matching = Customer::factory()->create(['locale' => 'fr']);
        Customer::factory()->create(['locale' => 'en']);

        $segment = CustomerSegment::factory()->create([
            'is_active' => true,
            'rules' => [
                'operator' => 'and',
                'conditions' => [
                    ['field' => 'locale', 'operator' => 'eq', 'value' => 'fr'],
                ],
            ],
        ]);

        $ids = $this->engine->customerIds($segment);

        $this->assertCount(1, $ids);
        $this->assertSame($matching->id, $ids[0]);
    }

    public function test_customers_returns_matching_models(): void
    {
        Customer::factory()->create(['locale' => 'fr']);
        Customer::factory()->create(['locale' => 'en']);

        $segment = CustomerSegment::factory()->create([
            'is_active' => true,
            'rules' => [
                'operator' => 'and',
                'conditions' => [
                    ['field' => 'locale', 'operator' => 'eq', 'value' => 'fr'],
                ],
            ],
        ]);

        $customers = $this->engine->customers($segment);

        $this->assertCount(1, $customers);
        $this->assertSame('fr', $customers->first()->locale);
    }

    public function test_matches_unpaid_orders_excluded_from_aggregates(): void
    {
        $customer = Customer::factory()->create();
        Order::factory()->create([
            'customer_id' => $customer->id,
            'payment_status' => 'pending',
            'grand_total' => 999,
            'placed_at' => now()->subDays(1),
        ]);

        $segment = CustomerSegment::factory()->create([
            'is_active' => true,
            'rules' => [
                'operator' => 'and',
                'conditions' => [
                    ['field' => 'total_spent', 'operator' => 'gte', 'value' => 100],
                ],
            ],
        ]);

        $this->assertFalse($this->engine->matches($customer, $segment));
    }

    public function test_matches_operator_gt_and_lt(): void
    {
        $customer = Customer::factory()->create();
        Order::factory()->create([
            'customer_id' => $customer->id,
            'payment_status' => 'paid',
            'grand_total' => 150,
            'placed_at' => now()->subDays(5),
        ]);

        $segment = CustomerSegment::factory()->create([
            'is_active' => true,
            'rules' => [
                'operator' => 'and',
                'conditions' => [
                    ['field' => 'total_spent', 'operator' => 'gt', 'value' => 100],
                    ['field' => 'total_spent', 'operator' => 'lt', 'value' => 200],
                ],
            ],
        ]);

        $this->assertTrue($this->engine->matches($customer, $segment));
    }

    public function test_count_returns_zero_for_inactive_segment(): void
    {
        Customer::factory()->create(['locale' => 'fr']);

        $segment = CustomerSegment::factory()->create([
            'is_active' => false,
            'rules' => [
                'operator' => 'and',
                'conditions' => [
                    ['field' => 'locale', 'operator' => 'eq', 'value' => 'fr'],
                ],
            ],
        ]);

        $this->assertSame(0, $this->engine->count($segment));
    }
}
