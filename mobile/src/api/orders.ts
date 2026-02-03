import { apiFetch } from './http';
import type { ApiError } from './http';
import { mobileApiBaseUrl } from './config';
import type { Order, OrderItem, TrackingEvent } from '@/src/types/orders';

export type ApiEnvelope<T> = {
  success: boolean;
  data?: T;
  message?: string | null;
  errors?: Record<string, string[]> | null;
  meta?: Record<string, unknown> | null;
};

type ApiOrder = Record<string, unknown>;

type ApiTrackingPayload = {
  orderNumber?: string;
  status?: string;
  tracking?: Array<Record<string, unknown>>;
};

export type OrderTrackingPayload = {
  orderNumber: string;
  status: string;
  tracking: TrackingEvent[];
};

const unwrap = <T>(payload: ApiEnvelope<T>): T => {
  if (payload && payload.success && payload.data !== undefined) {
    return payload.data;
  }
  const error: ApiError = {
    status: 422,
    message: payload?.message ?? 'Request failed',
    errors: payload?.errors ?? undefined,
  };
  throw error;
};

const toStringValue = (value: unknown, fallback = ''): string => {
  if (typeof value === 'string' && value.trim().length > 0) return value;
  if (typeof value === 'number' && Number.isFinite(value)) return String(value);
  return fallback;
};

const toNumberValue = (value: unknown, fallback = 0): number => {
  if (typeof value === 'number' && Number.isFinite(value)) return value;
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : fallback;
};

const mapTrackingEvent = (item: Record<string, unknown>, index: number): TrackingEvent => {
  const occurredAt =
    typeof item.occurredAt === 'string'
      ? item.occurredAt
      : typeof item.occurred_at === 'string'
        ? item.occurred_at
        : null;
  return {
    id: toStringValue(item.id, `trk-${index}`),
    status: toStringValue(item.status, 'Update'),
    description: toStringValue(item.description, 'Tracking update'),
    occurredAt,
  };
};

const mapOrderItem = (item: Record<string, unknown>, index: number): OrderItem => {
  return {
    id: toStringValue(item.id, `item-${index}`),
    name: toStringValue(item.name, 'Item'),
    quantity: Math.max(1, toNumberValue(item.quantity ?? 1, 1)),
    price: toNumberValue(item.price ?? 0, 0),
    image: typeof item.image === 'string' ? item.image : null,
  };
};

const mapOrderSummary = (item: ApiOrder): Order => {
  return {
    number: toStringValue(item.number, 'Order'),
    status: toStringValue(item.status, 'Processing'),
    total: toNumberValue(item.total ?? item.grand_total ?? 0, 0),
    placedAt: typeof item.placedAt === 'string'
      ? item.placedAt
      : typeof item.placed_at === 'string'
        ? item.placed_at
        : null,
    items: [],
    tracking: [],
  };
};

const mapOrderDetail = (payload: ApiOrder): Order => {
  const items = Array.isArray(payload.items) ? payload.items : [];
  const tracking = Array.isArray(payload.tracking) ? payload.tracking : [];

  return {
    number: toStringValue(payload.number, 'Order'),
    status: toStringValue(payload.status, 'Processing'),
    total: toNumberValue(payload.total ?? payload.grand_total ?? 0, 0),
    placedAt: typeof payload.placedAt === 'string'
      ? payload.placedAt
      : typeof payload.placed_at === 'string'
        ? payload.placed_at
        : null,
    items: items.map((item, index) => mapOrderItem(item as Record<string, unknown>, index)),
    tracking: tracking.map((item, index) => mapTrackingEvent(item as Record<string, unknown>, index)),
  };
};

export const fetchOrders = async (params?: {
  page?: number;
  per_page?: number;
}): Promise<{ items: Order[]; meta: Record<string, unknown> | null }> => {
  const search = new URLSearchParams();
  if (params?.page) search.set('page', String(params.page));
  if (params?.per_page) search.set('per_page', String(params.per_page));

  const url = `${mobileApiBaseUrl}/orders${search.toString() ? `?${search.toString()}` : ''}`;
  const payload = await apiFetch<ApiEnvelope<ApiOrder[]>>(url);
  const data = unwrap(payload);
  return { items: Array.isArray(data) ? data.map(mapOrderSummary) : [], meta: payload.meta ?? null };
};

export const fetchOrderDetail = async (number: string): Promise<Order> => {
  const payload = await apiFetch<ApiEnvelope<ApiOrder>>(
    `${mobileApiBaseUrl}/orders/${encodeURIComponent(number)}`
  );
  return mapOrderDetail(unwrap(payload));
};

export const trackOrder = async (number: string, email: string): Promise<OrderTrackingPayload> => {
  const params = new URLSearchParams({ number, email });
  const payload = await apiFetch<ApiEnvelope<ApiTrackingPayload>>(
    `${mobileApiBaseUrl}/orders/track?${params.toString()}`
  );
  const data = unwrap(payload);
  const tracking = Array.isArray(data?.tracking)
    ? data.tracking.map((item, index) => mapTrackingEvent(item as Record<string, unknown>, index))
    : [];

  return {
    orderNumber: toStringValue(data?.orderNumber ?? (data as any)?.order_number, number),
    status: toStringValue(data?.status, 'Processing'),
    tracking,
  };
};
