<?php

namespace App\Domain\Orders\Services;

use App\Domain\Orders\Models\MessageLog;
use App\Domain\Orders\Models\MessageTemplate;
use App\Domain\Orders\Models\MessageTriggerHistory;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\Shipment;
use App\Enums\MessageChannel;
use Illuminate\Support\Collection;

class MessageTemplateService
{
    /**
     * Send a template to a customer.
     */
    public function sendTemplate(
        MessageTemplate $template,
        Order $order,
        array $placeholders = [],
        ?Shipment $shipment = null,
        ?MessageChannel $channel = null,
        ?int $userId = null
    ): MessageLog {
        $channel = $channel ?? $template->default_channel;
        $data = $this->buildPlaceholderData($order, $shipment, $placeholders);
        $rendered = $template->render($data);

        $customer = $order->customer;
        $recipient = $this->getRecipient($customer, $channel);

        $messageLog = MessageLog::create([
            'message_template_id' => $template->id,
            'order_id' => $order->id,
            'shipment_id' => $shipment?->id,
            'customer_id' => $customer?->id,
            'recipient' => $recipient,
            'channel' => $channel,
            'subject' => $rendered['subject'],
            'message_content' => $rendered['message'],
            'placeholders_used' => $data,
            'status' => 'queued',
            'sent_by' => $userId,
            'is_automatic' => is_null($userId),
        ]);

        // Queue actual sending
        $this->queueSend($messageLog, $channel);

        return $messageLog;
    }

    /**
     * Send message and log result.
     */
    public function send(MessageLog $messageLog): bool
    {
        try {
            $result = match ($messageLog->channel) {
                MessageChannel::EMAIL => $this->sendEmail($messageLog),
                MessageChannel::WHATSAPP => $this->sendWhatsApp($messageLog),
                MessageChannel::SMS => $this->sendSMS($messageLog),
                MessageChannel::MANUAL => true, // Manual messages marked sent by admin
            };

            if ($result) {
                $messageLog->markAsSent();
                return true;
            } else {
                $messageLog->markAsFailed('Provider returned false');
                return false;
            }
        } catch (\Exception $e) {
            $messageLog->markAsFailed($e->getMessage());
            return false;
        }
    }

    /**
     * Get recommended templates for an order or shipment context.
     */
    public function getRecommendedTemplates(Order $order, ?Shipment $shipment = null): Collection
    {
        $templates = MessageTemplate::where('is_active', true)->get();

        return $templates->filter(function (MessageTemplate $template) use ($order, $shipment) {
            return $this->matchesConditions($template, $order, $shipment);
        });
    }

    /**
     * Get templates for a specific trigger event.
     */
    public function getTemplatesForTrigger(string $triggerType): Collection
    {
        return MessageTemplate::where('is_active', true)
            ->where('send_automatically', true)
            ->whereJsonContains('trigger_types', $triggerType)
            ->get();
    }

    /**
     * Create a trigger history entry.
     */
    public function createTriggerHistory(
        MessageTemplate $template,
        Order $order,
        string $triggerType,
        array $triggerData = [],
        ?Shipment $shipment = null,
        int $delayMinutes = 0
    ): MessageTriggerHistory {
        $history = MessageTriggerHistory::create([
            'message_template_id' => $template->id,
            'order_id' => $order->id,
            'shipment_id' => $shipment?->id,
            'trigger_type' => $triggerType,
            'trigger_data' => $triggerData,
            'status' => 'pending',
            'scheduled_for' => now()->addMinutes($delayMinutes ?? $template->auto_send_delay_hours * 60),
        ]);

        return $history;
    }

    /**
     * Process pending trigger to send message.
     */
    public function processTrigger(MessageTriggerHistory $trigger): bool
    {
        if (!$trigger->template->is_active) {
            $trigger->cancel('Template is inactive');
            return false;
        }

        $order = $trigger->order;
        $shipment = $trigger->shipment;
        $placeholders = $trigger->trigger_data;

        try {
            $messageLog = $this->sendTemplate(
                $trigger->template,
                $order,
                $placeholders,
                $shipment
            );

            $trigger->markAsSent($messageLog);
            return true;
        } catch (\Exception $e) {
            $trigger->cancel('Failed to send: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get message statistics for order.
     */
    public function getOrderMessageStats(Order $order): array
    {
        $logs = $order->messageLogs ?? MessageLog::where('order_id', $order->id)->get();

        return [
            'total_sent' => $logs->where('status', 'sent')->count(),
            'failed' => $logs->where('status', 'failed')->count(),
            'opened' => $logs->where('status', 'opened')->count(),
            'by_channel' => $logs->groupBy('channel')->mapWithKeys(
                fn ($items, $channel) => [$channel => $items->count()]
            )->toArray(),
            'templates_used' => $logs->pluck('message_template_id')->unique()->count(),
        ];
    }

    /**
     * Recommend templates based on shipment exceptions.
     */
    public function getTemplatesByException(string $exceptionCode): Collection
    {
        $mapping = [
            'CUSTOMS_DUTY_UNPAID' => 'customs',
            'CUSTOMS_CLEARANCE_REQUIRED' => 'customs',
            'CUSTOMS_HELD' => 'customs',
            'TRACKING_NO_UPDATES' => 'delay',
            'TRACKING_LOST' => 'delay',
            'RETURNED_TO_SENDER' => 'delivery_update',
            'DELIVERY_FAILED' => 'delivery_update',
            'PACKAGE_DAMAGED' => 'exception',
            'PACKAGE_LOST' => 'exception',
        ];

        $type = $mapping[$exceptionCode] ?? null;

        if (!$type) {
            return collect();
        }

        return MessageTemplate::where('is_active', true)
            ->where('type', $type)
            ->get();
    }

    // Private helper methods

    private function sendEmail(MessageLog $messageLog): bool
    {
        // Placeholder for email sending (e.g., Mailgun, SendGrid, AWS SES)
        // Example:
        // Mail::send('emails.message', ['message' => $messageLog], function ($mail) use ($messageLog) {
        //     $mail->to($messageLog->recipient)
        //         ->subject($messageLog->subject);
        // });

        // For now, simulate success
        return true;
    }

    private function sendWhatsApp(MessageLog $messageLog): bool
    {
        // Placeholder for WhatsApp API (e.g., Twilio)
        // Example:
        // $twilio = new Client(env('TWILIO_ACCOUNT_SID'), env('TWILIO_AUTH_TOKEN'));
        // $message = $twilio->messages->create($messageLog->recipient, [
        //     'body' => $messageLog->message_content,
        //     'from' => env('TWILIO_WHATSAPP_NUMBER'),
        // ]);
        // $messageLog->update(['external_message_id' => $message->sid]);

        return true;
    }

    private function sendSMS(MessageLog $messageLog): bool
    {
        // Placeholder for SMS API (e.g., Twilio)
        // Similar to WhatsApp implementation

        return true;
    }

    private function queueSend(MessageLog $messageLog, MessageChannel $channel): void
    {
        // Queue for async processing
        // Example: MessageSendJob::dispatch($messageLog);
    }

    private function buildPlaceholderData(Order $order, ?Shipment $shipment, array $custom = []): array
    {
        $data = [
            'order_number' => $order->number,
            'customer_name' => $order->customer->first_name ?? 'Customer',
            'order_total' => $order->grand_total,
            'support_email' => config('mail.from.address', 'support@example.com'),
            'support_phone' => config('app.support_phone', '+1-800-SUPPORT'),
        ];

        if ($shipment) {
            $data = array_merge($data, [
                'tracking_number' => $shipment->tracking_number,
                'tracking_url' => $shipment->tracking_url,
                'carrier_name' => $shipment->carrier,
                'estimated_delivery' => $shipment->estimated_delivery_at?->format('M d, Y'),
            ]);
        }

        return $data;
    }

    private function getRecipient(\App\Models\Customer $customer = null, MessageChannel $channel): string
    {
        if (!$customer) {
            return '';
        }

        return match ($channel) {
            MessageChannel::EMAIL => $customer->email ?? '',
            MessageChannel::WHATSAPP => $customer->phone ?? '',
            MessageChannel::SMS => $customer->phone ?? '',
            MessageChannel::MANUAL => $customer->email ?? '',
        };
    }

    private function matchesConditions(MessageTemplate $template, Order $order, ?Shipment $shipment = null): bool
    {
        if (!$template->condition_rules) {
            return true;
        }

        $conditions = $template->condition_rules;

        // Check order status conditions
        if (isset($conditions['order_status']) && $order->status !== $conditions['order_status']) {
            return false;
        }

        // Check shipment conditions
        if ($shipment) {
            if (isset($conditions['shipment_days_shipped_min'])) {
                $daysSent = $shipment->shipped_at?->diffInDays(now()) ?? 0;
                if ($daysSent < $conditions['shipment_days_shipped_min']) {
                    return false;
                }
            }

            if (isset($conditions['shipment_days_shipped_max'])) {
                $daysSent = $shipment->shipped_at?->diffInDays(now()) ?? 0;
                if ($daysSent > $conditions['shipment_days_shipped_max']) {
                    return false;
                }
            }
        }

        return true;
    }
}
