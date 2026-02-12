<?php

return [
    // Minimum margin buffer over cost (in percent) required for selling price
    'min_margin_percent' => env('PRICING_MIN_MARGIN_PERCENT', 20),

    // Shipping cost buffer applied on top of cost (in percent)
    'shipping_buffer_percent' => env('PRICING_SHIPPING_BUFFER_PERCENT', 10),

    // Maximum discount percent allowed from the computed target price
    'max_discount_percent' => env('PRICING_MAX_DISCOUNT_PERCENT', 30),

    // Category-based margin tiers for repricing command.
    // Supports either:
    // 1) JSON map: {"25":35,"80":28}
    // 2) JSON list: [{"category_id":25,"margin_percent":35},{"category_ids":[80,81],"margin_percent":28}]
    'category_margin_tiers' => (static function (): array {
        $decoded = json_decode((string) env('PRICING_CATEGORY_MARGIN_TIERS', '[]'), true);
        return is_array($decoded) ? $decoded : [];
    })(),

    // Dedicated queue for bulk margin and repricing workloads.
    'bulk_margin_queue' => env('PRICING_BULK_QUEUE', 'pricing'),

    // Queue used for compare-at jobs triggered by repricing.
    'compare_at_queue' => env('PRICING_COMPARE_AT_QUEUE', env('PRICING_BULK_QUEUE', 'pricing')),
];
