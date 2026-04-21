<?php

namespace App\Console\Commands;

use App\Domain\Common\Models\Address;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixIncorrectOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:fix-incorrect {--dry-run : Show what would be changed without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix orders with incorrect totals and placeholder addresses';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('Starting to fix incorrect orders...');

        // Find orders with placeholder addresses
        $placeholderOrders = Order::whereHas('shippingAddress', function ($query) {
            $query->where('line1', 'like', '%Temporary%')
                  ->orWhere('line1', 'like', '%Checkout Address%')
                  ->orWhere('city', 'like', '%Temporary%');
        })
        ->orWhere('grand_total', 0)
        ->orWhere('subtotal', 0)
        ->get();

        $this->info("Found {$placeholderOrders->count()} orders with issues");

        foreach ($placeholderOrders as $order) {
            $this->info("\nProcessing Order #{$order->number} (ID: {$order->id})");

            // Fix address
            $address = $order->shippingAddress;
            if ($address && (str_contains($address->line1, 'Temporary') || str_contains($address->line1, 'Checkout Address'))) {
                $this->warn("  - Found placeholder address: {$address->line1}, {$address->city}");
                
                if (!$dryRun) {
                    // Try to get address from customer's other addresses
                    $customerAddress = Address::where('customer_id', $order->customer_id)
                        ->where('id', '!=', $address->id)
                        ->where('line1', 'not like', '%Temporary%')
                        ->where('line1', 'not like', '%Checkout Address%')
                        ->first();

                    if ($customerAddress) {
                        $this->info("  - Using customer's existing address as replacement");
                        $address->update([
                            'line1' => $customerAddress->line1,
                            'line2' => $customerAddress->line2,
                            'city' => $customerAddress->city,
                            'state' => $customerAddress->state,
                            'postal_code' => $customerAddress->postal_code,
                            'name' => $customerAddress->name,
                            'phone' => $customerAddress->phone,
                        ]);
                    } else {
                        $this->warn("  - No alternative address found for customer");
                    }
                }
            }

            // Fix totals from order items
            $orderItems = $order->orderItems;
            if ($orderItems->count() > 0) {
                $calculatedSubtotal = $orderItems->sum('total');
                
                $this->info("  - Current subtotal: {$order->subtotal}, Calculated from items: {$calculatedSubtotal}");
                
                if ($order->subtotal === 0 || $order->subtotal !== $calculatedSubtotal) {
                    if (!$dryRun) {
                        $order->update([
                            'subtotal' => $calculatedSubtotal,
                        ]);
                        $this->info("  - Updated subtotal to: {$calculatedSubtotal}");
                    }
                }

                // Recalculate grand_total = subtotal + shipping - discount + tax
                $newGrandTotal = $calculatedSubtotal + $order->shipping_total - $order->discount_total + $order->tax_total;
                
                $this->info("  - Current grand_total: {$order->grand_total}, Recalculated: {$newGrandTotal}");
                
                if ($order->grand_total === 0 || $order->grand_total !== $newGrandTotal) {
                    if (!$dryRun) {
                        $order->update([
                            'grand_total' => $newGrandTotal,
                        ]);
                        $this->info("  - Updated grand_total to: {$newGrandTotal}");
                    }
                }

                // Update payment amount if it exists and is wrong
                $payment = $order->payments()->first();
                if ($payment && ($payment->amount === 0 || $payment->amount !== $newGrandTotal)) {
                    $this->info("  - Payment amount: {$payment->amount}, Should be: {$newGrandTotal}");
                    if (!$dryRun) {
                        $payment->update([
                            'amount' => $newGrandTotal,
                        ]);
                        $this->info("  - Updated payment amount to: {$newGrandTotal}");
                    }
                }
            } else {
                $this->warn("  - No order items found, cannot recalculate totals");
            }

            // Fix status if payment is paid but order status is pending_payment
            if ($order->payment_status === 'paid' && $order->status === 'pending_payment') {
                $this->warn("  - Status mismatch: payment_status is paid but status is pending_payment");
                if (!$dryRun) {
                    $order->update([
                        'status' => 'paid',
                    ]);
                    $this->info("  - Updated order status to: paid");
                }
            }
        }

        if ($dryRun) {
            $this->info("\nDry run completed. No changes were made.");
            $this->info("Run without --dry-run to apply the changes.");
        } else {
            $this->info("\nCompleted fixing orders.");
        }

        return Command::SUCCESS;
    }
}
