<?php

namespace App\Console\Commands;

use App\Domain\Orders\Models\Order;
use Illuminate\Console\Command;

class FixOrderTotals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:fix-totals {order-number : The order number to fix} {subtotal : The correct subtotal} {shipping : The correct shipping amount} {--dry-run : Show what would be changed without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manually fix totals for a specific order';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $orderNumber = $this->argument('order-number');
        $subtotal = (float) $this->argument('subtotal');
        $shipping = (float) $this->argument('shipping');
        $dryRun = $this->option('dry-run');

        $order = Order::where('number', $orderNumber)->first();

        if (!$order) {
            $this->error("Order #{$orderNumber} not found");
            return Command::FAILURE;
        }

        $this->info("Processing Order #{$order->number} (ID: {$order->id})");
        $this->info("Current values:");
        $this->info("  - Subtotal: {$order->subtotal}");
        $this->info("  - Shipping: {$order->shipping_total}");
        $this->info("  - Discount: {$order->discount_total}");
        $this->info("  - Tax: {$order->tax_total}");
        $this->info("  - Grand Total: {$order->grand_total}");
        $this->info("  - Status: {$order->status}");
        $this->info("  - Payment Status: {$order->payment_status}");

        $newGrandTotal = $subtotal + $shipping - $order->discount_total + $order->tax_total;

        $this->info("\nNew values:");
        $this->info("  - Subtotal: {$subtotal}");
        $this->info("  - Shipping: {$shipping}");
        $this->info("  - New Grand Total: {$newGrandTotal}");

        if ($dryRun) {
            $this->info("\nDry run - no changes made");
            return Command::SUCCESS;
        }

        $this->info("\nUpdating order...");

        $order->update([
            'subtotal' => $subtotal,
            'shipping_total' => $shipping,
            'shipping_total_estimated' => $shipping,
            'grand_total' => $newGrandTotal,
        ]);

        // Update payment amount
        $payment = $order->payments()->first();
        if ($payment) {
            $this->info("  - Updating payment amount from {$payment->amount} to {$newGrandTotal}");
            $payment->update([
                'amount' => $newGrandTotal,
            ]);
        }

        // Fix status if needed
        if ($order->payment_status === 'paid' && $order->status === 'pending_payment') {
            $this->info("  - Fixing status from pending_payment to paid");
            $order->update([
                'status' => 'paid',
            ]);
        }

        $this->info("\nOrder updated successfully!");
        $this->info("Refresh the confirmation page to see the changes.");

        return Command::SUCCESS;
    }
}
