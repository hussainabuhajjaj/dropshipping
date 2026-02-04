export type OrderItem = {
  id: string;
  name: string;
  quantity: number;
  price: number;
  image: string;
};

export type TrackingEvent = {
  id: string;
  status: string;
  description: string;
  occurredAt: string;
};

export type Order = {
  number: string;
  status: string;
  total: number;
  placedAt: string;
  items: OrderItem[];
  tracking: TrackingEvent[];
};

export const orders: Order[] = [
  {
    number: 'DS-4821',
    status: 'In transit',
    total: 82.4,
    placedAt: '2025-12-21',
    items: [
      {
        id: 'oi-1',
        name: 'Smart LED Strip Lights',
        quantity: 2,
        price: 19.9,
        image: 'https://images.unsplash.com/photo-1545239351-1141bd82e8a6?auto=format&fit=crop&w=700&q=80',
      },
      {
        id: 'oi-2',
        name: 'City Travel Backpack',
        quantity: 1,
        price: 42.6,
        image: 'https://images.unsplash.com/photo-1503342217505-b0a15ec3261c?auto=format&fit=crop&w=700&q=80',
      },
    ],
    tracking: [
      {
        id: 'te-1',
        status: 'Dispatched',
        description: 'Package left the origin warehouse.',
        occurredAt: '2025-12-22 11:30',
      },
      {
        id: 'te-2',
        status: 'In transit',
        description: 'Arrived at export facility.',
        occurredAt: '2025-12-23 08:20',
      },
      {
        id: 'te-3',
        status: 'Customs',
        description: 'Customs clearance in progress.',
        occurredAt: '2025-12-24 15:05',
      },
    ],
  },
  {
    number: 'DS-3920',
    status: 'Delivered',
    total: 41.7,
    placedAt: '2025-12-10',
    items: [
      {
        id: 'oi-3',
        name: 'Wireless Noise Buds',
        quantity: 1,
        price: 28.5,
        image: 'https://images.unsplash.com/photo-1518443895471-3e5a0c0f60e6?auto=format&fit=crop&w=700&q=80',
      },
      {
        id: 'oi-4',
        name: 'Mini Smoothie Blender',
        quantity: 1,
        price: 13.2,
        image: 'https://images.unsplash.com/photo-1561049938-6b8d79d1b724?auto=format&fit=crop&w=700&q=80',
      },
    ],
    tracking: [
      {
        id: 'te-4',
        status: 'Delivered',
        description: 'Delivered to recipient.',
        occurredAt: '2025-12-17 16:40',
      },
    ],
  },
];
