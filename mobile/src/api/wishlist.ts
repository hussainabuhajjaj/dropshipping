import { apiFetch, apiPost } from './http';
import type { ApiError } from './http';
import { mobileApiBaseUrl } from './config';
import type { Product } from '@/src/types/storefront';
import { mapProduct } from './catalog';

export type ApiEnvelope<T> = {
  success: boolean;
  data?: T;
  message?: string | null;
  errors?: Record<string, string[]> | null;
  meta?: Record<string, unknown> | null;
};

export type WishlistItem = {
  id: string;
  productId: string;
  addedAt: string | null;
  product: Product | null;
};

type ApiWishlistItem = Record<string, unknown>;

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

const mapWishlistItem = (source: ApiWishlistItem): WishlistItem => {
  const productPayload =
    source.product && typeof source.product === 'object'
      ? mapProduct(source.product as Record<string, unknown>)
      : null;

  return {
    id: toStringValue(source.id),
    productId: toStringValue(source.product_id),
    addedAt: typeof source.added_at === 'string' ? source.added_at : null,
    product: productPayload,
  };
};

export const fetchWishlist = async (): Promise<WishlistItem[]> => {
  const payload = await apiFetch<ApiEnvelope<ApiWishlistItem[]>>(`${mobileApiBaseUrl}/wishlist`);
  return unwrap(payload).map(mapWishlistItem);
};

export const addWishlistItem = async (productId: string | number): Promise<WishlistItem> => {
  const payload = await apiPost<ApiEnvelope<ApiWishlistItem>>(
    `${mobileApiBaseUrl}/wishlist/${encodeURIComponent(String(productId))}`,
    {}
  );
  return mapWishlistItem(unwrap(payload));
};

export const removeWishlistItem = async (productId: string | number): Promise<boolean> => {
  const payload = await apiFetch<ApiEnvelope<{ ok?: boolean }>>(
    `${mobileApiBaseUrl}/wishlist/${encodeURIComponent(String(productId))}`,
    { method: 'DELETE' }
  );
  const data = unwrap(payload);
  return Boolean(data?.ok);
};
