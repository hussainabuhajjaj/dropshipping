<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\User;
use Illuminate\Console\Command;

class AdminSoftDeleteOrder extends Command
{
    protected $signature = 'order:admin-soft-delete
                            {order_id : The ID of the order to soft delete}
                            {--admin_id= : Admin user ID performing the deletion}';

    protected $description = 'Soft delete a cancelled and unpaid order (admin only)';

    public function handle(): int
    {
        $orderId = $this->argument('order_id');
        $adminId = $this->option('admin_id');

        // Find the order
        $order = Order::withTrashed()->find($orderId);

        if (! $order) {
            $this->error("Order with ID {$orderId} not found.");
            return self::FAILURE;
        }

        if ($order->trashed()) {
            $this->error("Order {$order->number} is already soft deleted.");
            return self::FAILURE;
        }

        // Verify admin if provided
        if ($adminId) {
            $admin = User::find($adminId);
            if (! $admin) {
                $this->error("Admin user with ID {$adminId} not found.");
                return self::FAILURE;
            }
            if (! in_array($admin->role, ['admin', 'staff'], true)) {
                $this->error("User {$admin->name} does not have admin privileges.");
                return self::FAILURE;
            }
        }

        // Check if can be soft deleted
        if (! $order->canBeAdminSoftDeleted()) {
            $this->error("Order {$order->number} cannot be soft deleted.");
            $this->error("Status: {$order->status}, Payment: {$order->payment_status}");
            $this->error("Only cancelled and unpaid orders can be soft deleted.");
            return self::FAILURE;
        }

        // Confirm deletion
        if (! $this->confirm("Are you sure you want to soft delete order {$order->number}? This action cannot be undone.")) {
            $this->info('Operation cancelled.');
            return self::SUCCESS;
        }

        try {
            $order->adminSoftDelete((int) ($adminId ?? 0));
            $this->info("✅ Order {$order->number} has been soft deleted successfully.");
            $this->info("Deleted by: " . ($adminId ? User::find($adminId)?->name : 'System'));
            return self::SUCCESS;
        } catch (\RuntimeException $e) {
            $this->error("Failed to soft delete order: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
