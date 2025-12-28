<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Widgets\ConversionFunnelWidget;
use App\Filament\Widgets\LowStockWidget;
use App\Filament\Widgets\OrderStatsWidget;
use App\Filament\Widgets\RevenueChartWidget;
use App\Filament\Widgets\TopCustomersWidget;
use App\Filament\Widgets\TopProductsWidget;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsWidgetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_stats_widget_can_be_instantiated(): void
    {
        $widget = app(OrderStatsWidget::class);
        
        $this->assertInstanceOf(OrderStatsWidget::class, $widget);
    }

    public function test_revenue_chart_widget_can_be_instantiated(): void
    {
        $widget = app(RevenueChartWidget::class);
        
        $this->assertInstanceOf(RevenueChartWidget::class, $widget);
    }

    public function test_conversion_funnel_widget_can_be_instantiated(): void
    {
        $widget = app(ConversionFunnelWidget::class);
        
        $this->assertInstanceOf(ConversionFunnelWidget::class, $widget);
    }

    public function test_top_products_widget_can_be_instantiated(): void
    {
        $widget = app(TopProductsWidget::class);
        
        $this->assertInstanceOf(TopProductsWidget::class, $widget);
    }

    public function test_top_customers_widget_can_be_instantiated(): void
    {
        $widget = app(TopCustomersWidget::class);
        
        $this->assertInstanceOf(TopCustomersWidget::class, $widget);
    }

    public function test_low_stock_widget_can_be_instantiated(): void
    {
        $widget = app(LowStockWidget::class);
        
        $this->assertInstanceOf(LowStockWidget::class, $widget);
    }

    public function test_order_stats_widget_returns_stats(): void
    {
        // Create test data
        Order::factory()->create(['created_at' => now()]);
        
        $widget = app(OrderStatsWidget::class);
        $stats = $widget->getStats();

        $this->assertIsArray($stats);
        $this->assertCount(4, $stats);
    }

    public function test_revenue_chart_widget_returns_data(): void
    {
        $widget = app(RevenueChartWidget::class);
        $data = $widget->getData();

        $this->assertIsArray($data);
        $this->assertArrayHasKey('datasets', $data);
        $this->assertArrayHasKey('labels', $data);
    }

    public function test_conversion_funnel_widget_returns_data(): void
    {
        $widget = app(ConversionFunnelWidget::class);
        $data = $widget->getData();

        $this->assertIsArray($data);
        $this->assertArrayHasKey('datasets', $data);
        $this->assertArrayHasKey('labels', $data);
    }

    public function test_low_stock_widget_returns_stats(): void
    {
        // Create test product with low stock
        $product = Product::factory()->create(['is_active' => true]);
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'stock_on_hand' => 3,
            'low_stock_threshold' => 5,
        ]);

        $widget = app(LowStockWidget::class);
        $stats = $widget->getStats();

        $this->assertIsArray($stats);
        $this->assertCount(3, $stats);
    }
}
