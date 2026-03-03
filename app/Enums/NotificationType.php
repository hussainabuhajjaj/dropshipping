<?php

declare(strict_types=1);

namespace App\Enums;

final class NotificationType
{
    public const PROMOTION = 'promotion';
    public const NEW_PRODUCT = 'new_product';
    public const COUPON = 'coupon';
    public const SUPPORT_REPLY = 'support_reply';
    public const SUPPORT_CONVERSATION_ALERT = 'support_conversation_alert';
    public const SUPPORT_ESCALATION_DIGEST = 'support_escalation_digest';
    public const ORDER_UPDATE = 'order_update';
    public const GENERAL = 'general';
}
