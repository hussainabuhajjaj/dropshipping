<?php

namespace App\Domain\Orders\Services;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Orders\Models\Refund;
use App\Domain\Orders\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RefundService
{
    /**
     * Create a refund request with validation.
     *
     * @param array{
     *     order_id: int,
     *     amount: float|int,
     *     reason_code: string,
     *     customer_reason?: string,
     *     admin_notes?: string,
     *     order_item_id?: int,
     *     shipment_id?: int
     * } $data
     */
    public function createRefund(array $data): Refund
    {
        $order = Order::findOrFail($data['order_id']);

        // Validate amount
        $refundAmount = (float) $data['amount'];
        if ($refundAmount <= 0) {
            throw new InvalidArgumentException('Refund amount must be greater than zero.');
        }

        // If specific shipment provided, validate it belongs to order
        if (! empty($data['shipment_id'])) {
            $shipment = Shipment::findOrFail($data['shipment_id']);
            if ($shipment->orderItem?->order_id !== $order->id) {
                throw new InvalidArgumentException('Shipment does not belong to this order.');
            }
        }

        // If specific order item provided, validate it belongs to order
        if (! empty($data['order_item_id'])) {
            $orderItem = OrderItem::findOrFail($data['order_item_id']);
            if ($orderItem->order_id !== $order->id) {
                throw new InvalidArgumentException('Order item does not belong to this order.');
            }
        }

        // Check if refund can be created based on shipment status
        $this->validateRefundRules($order, $data);

        return DB::transaction(function () use ($order, $data) {
            $refund = Refund::create([
                'order_id' => $order->id,
                'order_item_id' => $data['order_item_id'] ?? null,
                'shipment_id' => $data['shipment_id'] ?? null,
                'amount' => $data['amount'],
                'reason_code' => $data['reason_code'],
                'customer_reason' => $data['customer_reason'] ?? null,
                'admin_notes' => $data['admin_notes'] ?? null,
                'status' => Refund::STATUS_PENDING,
            ]);

            // Log audit trail
            $this->auditLog($refund, 'created', null, auth()->user());

            return $refund;
        });
    }

    /**
     * Validate refund rules based on shipment status.
     * Enforce basic rules:
     * - Pre-shipped: Can refund up to 100%
     * - Shipped: Can refund up to 100% (customer may refuse or return)
     * - Delivered: Can refund only with reason (damaged, wrong item, quality issue) - partial refunds allowed
     * - Delivery failed: Can refund 100%
     */
    public function validateRefundRules(Order $order, array $data): void
    {
        $shipmentId = $data['shipment_id'] ?? null;
        $orderItemId = $data['order_item_id'] ?? null;
        $amount = (float) $data['amount'];
        $reasonCode = $data['reason_code'];

        // Get shipments to validate against
        if ($shipmentId) {
            $shipments = Shipment::where('id', $shipmentId)->get();
        } elseif ($orderItemId) {
            $shipments = Shipment::where('order_item_id', $orderItemId)->get();
        } else {
            $shipments = $order->shipments;
        }

        foreach ($shipments as $shipment) {
            $this->validateShipmentRefundRules($shipment, $amount, $reasonCode);
        }
    }

    /**
     * Check if refund can be created for this shipment based on its status.
     */
    private function validateShipmentRefundRules(Shipment $shipment, float $amount, string $reasonCode): void
    {
        // Pre-shipped: no restrictions
        if (is_null($shipment->shipped_at)) {
            return;
        }

        // Delivered: restrict by reason code
        if (! is_null($shipment->delivered_at)) {
            $allowedReasons = [
                Refund::REASON_DAMAGED,
                Refund::REASON_WRONG_ITEM,
                Refund::REASON_QUALITY_ISSUE,
                Refund::REASON_LATE_DELIVERY,
            ];

            if (! in_array($reasonCode, $allowedReasons)) {
                throw new InvalidArgumentException(
                    "Refunds for delivered shipments require a specific reason: " .
                    implode(', ', $allowedReasons) . ". Provided: {$reasonCode}"
                );
            }
        }

        // In transit: allow refund with any reason (customer can refuse delivery)
        // Shipped but not in transit: allow with any reason
    }

    /**
     * Approve a pending refund.
     */
    public function approveRefund(Refund $refund, ?User $user = null): Refund
    {
        if (! $refund->canBeApproved()) {
            throw new InvalidArgumentException(
                "Refund {$refund->id} cannot be approved (current status: {$refund->status})"
            );
        }

        $user = $user ?? auth()->user();

        return DB::transaction(function () use ($refund, $user) {
            $refund->update([
                'status' => Refund::STATUS_APPROVED,
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            $this->auditLog($refund, 'approved', null, $user);

            return $refund->fresh();
        });
    }

    /**
     * Reject a pending refund.
     */
    public function rejectRefund(Refund $refund, string $reason = '', ?User $user = null): Refund
    {
        if (! $refund->canBeRejected()) {
            throw new InvalidArgumentException(
                "Refund {$refund->id} cannot be rejected (current status: {$refund->status})"
            );
        }

        $user = $user ?? auth()->user();

        return DB::transaction(function () use ($refund, $reason, $user) {
            $refund->update([
                'status' => Refund::STATUS_REJECTED,
            ]);

            $this->auditLog($refund, 'rejected', $reason ?: null, $user);

            return $refund->fresh();
        });
    }

    /**
     * Mark refund as completed (funds processed).
     */
    public function completeRefund(Refund $refund, string $transactionId = '', ?array $gatewayResponse = null, ?User $user = null): Refund
    {
        if (! $refund->canBeProcessed()) {
            throw new InvalidArgumentException(
                "Refund {$refund->id} cannot be completed (current status: {$refund->status})"
            );
        }

        $user = $user ?? auth()->user();

        return DB::transaction(function () use ($refund, $transactionId, $gatewayResponse, $user) {
            $refund->update([
                'status' => Refund::STATUS_COMPLETED,
                'transaction_id' => $transactionId ?: null,
                'gateway_response' => $gatewayResponse,
                'processed_at' => now(),
            ]);

            $this->auditLog($refund, 'completed', null, $user);

            return $refund->fresh();
        });
    }

    /**
     * Cancel an approved or pending refund.
     */
    public function cancelRefund(Refund $refund, string $reason = '', ?User $user = null): Refund
    {
        if (! $refund->canBeCancelled()) {
            throw new InvalidArgumentException(
                "Refund {$refund->id} cannot be cancelled (current status: {$refund->status})"
            );
        }

        $user = $user ?? auth()->user();

        return DB::transaction(function () use ($refund, $reason, $user) {
            $refund->update([
                'status' => Refund::STATUS_CANCELLED,
            ]);

            $this->auditLog($refund, 'cancelled', $reason ?: null, $user);

            return $refund->fresh();
        });
    }

    /**
     * Get refunds for an order or order item.
     */
    public function getRefunds(Order $order, ?int $orderItemId = null, ?string $status = null): array
    {
        $query = Refund::where('order_id', $order->id);

        if ($orderItemId) {
            $query->where('order_item_id', $orderItemId);
        }

        if ($status) {
            $query->where('status', $status);
        }

        return $query->get()->all();
    }

    /**
     * Calculate total refunded amount for an order.
     */
    public function getOrderRefundTotal(Order $order, ?string $status = null): float
    {
        $query = Refund::where('order_id', $order->id);

        if ($status) {
            $query->where('status', $status);
        }

        return (float) $query->sum('amount');
    }

    /**
     * Calculate remaining refundable amount for an order (gross total - completed refunds).
     */
    public function getRemainingRefundable(Order $order): float
    {
        $refundedAmount = $this->getOrderRefundTotal($order, Refund::STATUS_COMPLETED);
        return max(0, (float) ($order->grand_total ?? 0) - $refundedAmount);
    }

    /**
     * Create audit log entry for refund action.
     */
    private function auditLog(Refund $refund, string $action, ?string $reason = null, ?User $user = null): void
    {
        $user = $user ?? auth()->user();

        $details = [
            'action' => $action,
            'refund_id' => $refund->id,
            'amount' => (string) $refund->amount,
            'reason_code' => $refund->reason_code,
            'status' => $refund->status,
        ];

        if ($reason) {
            $details['reason'] = $reason;
        }

        if ($refund->shipment_id) {
            $details['shipment_id'] = $refund->shipment_id;
        }

        if ($refund->order_item_id) {
            $details['order_item_id'] = $refund->order_item_id;
        }

        // Log to order audit trail
        $refund->order?->auditLogs()->create([
            'action' => "refund_{$action}",
            'details' => $details,
            'user_id' => $user?->id,
            'ip_address' => request()?->ip(),
        ]);
    }
}
