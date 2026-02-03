import { apiFetch, apiPatch, apiPost } from './http';
import type { ApiError } from './http';
import { mobileApiBaseUrl } from './config';
import type { CartLine, CartPayload, CartSummary } from '@/src/types/cart';

export type ApiEnvelope<T> = {
  success: boolean;
  data?: T;
  message?: string | null;
  errors?: Record<string, string[]> | null;
  meta?: Record<string, unknown> | null;
};

type ApiCartLine = Record<string, unknown>;

type ApiCart = {
  lines?: ApiCartLine[];
  currency?: string;
  subtotal?: number;
  shipping?: number;
  discount?: number;
  tax?: number;
  total?: number;
  coupon?: Record<string, unknown> | null;
  discount_label?: string | null;
  minimum_cart_requirement?: Record<string, unknown> | null;
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

const toNumber = (value: unknown, fallback = 0): number => {
  if (typeof value === 'number' && Number.isFinite(value)) return value;
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : fallback;
};

const toStringValue = (value: unknown, fallback = ''): string => {
  if (typeof value === 'string' && value.trim().length > 0) return value;
  if (typeof value === 'number' && Number.isFinite(value)) return String(value);
  return fallback;
};

const mapLine = (line: ApiCartLine): CartLine => {
  const media = Array.isArray(line.media)
    ? line.media.filter((item) => typeof item === 'string')
    : [];
  const image = media.length > 0 ? media[0] : null;
  return {
    id: toStringValue(line.id),
    productId: toStringValue(line.product_id),
    variantId: line.variant_id !== undefined && line.variant_id !== null ? toStringValue(line.variant_id) : null,
    name: toStringValue(line.name, 'Item'),
    price: toNumber(line.price ?? 0, 0),
    compareAt: line.compare_at_price !== undefined && line.compare_at_price !== null
      ? toNumber(line.compare_at_price, 0)
      : null,
    quantity: Math.max(1, toNumber(line.quantity ?? 1, 1)),
    image,
    currency: typeof line.currency === 'string' ? line.currency : null,
    slug: typeof line.slug === 'string' ? line.slug : null,
  };
};

const mapSummary = (cart: ApiCart): CartSummary => {
  return {
    currency: typeof cart.currency === 'string' ? cart.currency : 'USD',
    subtotal: toNumber(cart.subtotal ?? 0, 0),
    shipping: toNumber(cart.shipping ?? 0, 0),
    discount: toNumber(cart.discount ?? 0, 0),
    tax: toNumber(cart.tax ?? 0, 0),
    total: toNumber(cart.total ?? 0, 0),
    coupon: cart.coupon ?? null,
    discountLabel: typeof cart.discount_label === 'string' ? cart.discount_label : null,
    minimumRequirement:
      typeof cart.minimum_cart_requirement === 'object' && cart.minimum_cart_requirement !== null
        ? cart.minimum_cart_requirement
        : null,
  };
};

const mapCart = (cart: ApiCart): CartPayload => {
  return {
    lines: Array.isArray(cart.lines) ? cart.lines.map(mapLine) : [],
    summary: mapSummary(cart),
  };
};

export const fetchCart = async (): Promise<CartPayload> => {
  const payload = await apiFetch<ApiEnvelope<ApiCart>>(`${mobileApiBaseUrl}/cart`);
  return mapCart(unwrap(payload));
};

export const addCartItem = async (input: {
  productId: string | number;
  variantId?: string | number | null;
  quantity?: number;
}): Promise<CartPayload> => {
  const payload = await apiPost<ApiEnvelope<ApiCart>>(`${mobileApiBaseUrl}/cart/items`, {
    product_id: input.productId,
    variant_id: input.variantId ?? undefined,
    quantity: input.quantity ?? 1,
  });
  return mapCart(unwrap(payload));
};

export const updateCartItem = async (itemId: string | number, quantity: number): Promise<CartPayload> => {
  const payload = await apiPatch<ApiEnvelope<ApiCart>>(
    `${mobileApiBaseUrl}/cart/items/${encodeURIComponent(String(itemId))}`,
    { quantity }
  );
  return mapCart(unwrap(payload));
};

export const removeCartItem = async (itemId: string | number): Promise<CartPayload> => {
  const payload = await apiFetch<ApiEnvelope<ApiCart>>(
    `${mobileApiBaseUrl}/cart/items/${encodeURIComponent(String(itemId))}`,
    { method: 'DELETE' }
  );
  return mapCart(unwrap(payload));
};

export const applyCoupon = async (code: string): Promise<CartPayload> => {
  const payload = await apiPost<ApiEnvelope<ApiCart>>(`${mobileApiBaseUrl}/cart/apply-coupon`, { code });
  return mapCart(unwrap(payload));
};

export const removeCoupon = async (): Promise<CartPayload> => {
  const payload = await apiPost<ApiEnvelope<ApiCart>>(`${mobileApiBaseUrl}/cart/remove-coupon`, {});
  return mapCart(unwrap(payload));
};
