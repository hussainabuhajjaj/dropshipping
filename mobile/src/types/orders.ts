export type OrderItem = {
    id: string;
    name: string;
    quantity: number;
    price: number;
    image: string | null;
};

export type TrackingEvent = {
    id: string;
    status: string;
    description: string;
    occurredAt: string | null;
};

export type OrderStatusKey =
    | 'received'
    | 'processing'
    | 'dispatched'
    | 'in_transit'
    | 'out_for_delivery'
    | 'delivered'
    | 'issue_detected'
    | 'refunded';

export type Order = {
    number: string;
    status: string;
    statusKey: OrderStatusKey | string;
    statusExplanation?: string;
    total: number;
    placedAt: string | null;
    items: OrderItem[];
    tracking: TrackingEvent[];
};
