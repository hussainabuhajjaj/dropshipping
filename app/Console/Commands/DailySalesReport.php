<?php

namespace App\Console\Commands;

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Payment;
use App\Models\WhatsAppOrderIntent;
use Carbon\Carbon;
use Illuminate\Console\Command;

class DailySalesReport extends Command
{
    protected $signature = 'sales:daily-report';
    protected $description = 'Generate daily Simbazu sales report';

    public function handle()
    {
        $today = Carbon::today('Africa/Dakar');
        $tomorrow = $today->copy()->addDay();

        $this->info("===== SIMBAZU DAILY SALES REPORT =====");
        $this->info("📅 Date: " . $today->format('l, F j, Y') . " (Africa/Dakar)");
        $this->newLine();

        // Total orders placed today (not cancelled/refunded)
        $orders = Order::whereBetween('placed_at', [$today, $tomorrow])
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->get();

        $totalOrders = $orders->count();
        $this->info("📦 Total Orders Placed Today: {$totalOrders}");
        $this->newLine();

        // Total revenue
        $totalRevenue = $orders->sum('grand_total');
        $this->info("💰 Total Revenue Collected: " . number_format($totalRevenue, 2) . " XOF");
        $this->newLine();

        // COD vs Paystack split via payments
        $codPayments = Payment::whereBetween('paid_at', [$today, $tomorrow])
            ->where('provider', 'cod')
            ->sum('amount');
        $paystackPayments = Payment::whereBetween('paid_at', [$today, $tomorrow])
            ->where('provider', 'paystack')
            ->sum('amount');

        $codPercent = $totalRevenue > 0 ? round(($codPayments / $totalRevenue) * 100, 1) : 0;
        $paystackPercent = $totalRevenue > 0 ? round(($paystackPayments / $totalRevenue) * 100, 1) : 0;

        $this->info("===== PAYMENT METHOD SPLIT =====");
        $this->info("🟤 COD (Cash on Delivery): " . number_format($codPayments, 2) . " XOF ({$codPercent}%)");
        $this->info("🟢 Paystack: " . number_format($paystackPayments, 2) . " XOF ({$paystackPercent}%)");
        $this->newLine();

        // Average Order Value
        $aov = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;
        $this->info("===== AVERAGE ORDER VALUE =====");
        $this->info("📊 AOV: " . number_format($aov, 2) . " XOF");
        $this->newLine();

        // Order status breakdown
        $statusCounts = $orders->groupBy('status')->map->count();
        if ($statusCounts->isNotEmpty()) {
            $this->info("===== ORDER STATUS BREAKDOWN =====");
            foreach ($statusCounts as $status => $count) {
                $this->info("  • {$status}: {$count}");
            }
            $this->newLine();
        }

        // Top orders by value
        if ($orders->isNotEmpty()) {
            $this->info("===== TOP ORDERS BY VALUE (Today) =====");
            $topOrders = $orders->sortByDesc('grand_total')->take(5);
            foreach ($topOrders as $order) {
                $customer = $order->customer ? $order->customer->name : ($order->guest_name ?? 'Guest');
                $this->info("  • #{$order->number} — " . number_format($order->grand_total, 2) . " XOF — {$customer}");
            }
            $this->newLine();
        }

        // WhatsApp order intents today
        $waIntents = WhatsAppOrderIntent::whereBetween('created_at', [$today, $tomorrow])->count();
        $this->info("💬 WhatsApp Order Intents Today: {$waIntents}");
        $this->newLine();

        // Context: last order date
        $lastOrder = Order::whereNotIn('status', ['cancelled', 'refunded'])
            ->orderBy('placed_at', 'desc')
            ->first();
        if ($lastOrder) {
            $this->info("📌 Last Order: #{$lastOrder->number} on " . $lastOrder->placed_at->format('l, F j, Y') . " — " . number_format($lastOrder->grand_total, 2) . " XOF");
            $daysSinceLast = $today->diffInDays($lastOrder->placed_at);
            if ($daysSinceLast > 0) {
                $this->info("   ⏳ {$daysSinceLast} day(s) since last order");
            }
        } else {
            $this->info("📌 No orders in the system yet.");
        }

        $this->newLine();
        $this->info("===== END OF REPORT =====");
    }
}
