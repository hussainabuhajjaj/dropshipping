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

export type Order = {
  number: string;
  status: string;
  total: number;
  placedAt: string | null;
  items: OrderItem[];
  tracking: TrackingEvent[];
};
