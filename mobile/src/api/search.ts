import { apiFetch } from './http';
import type { ApiError } from './http';
import { mobileApiBaseUrl } from './config';
import type { Category, Product } from '@/src/types/storefront';
import { mapCategory, mapProduct } from './catalog';

export type ApiEnvelope<T> = {
  success: boolean;
  data?: T;
  message?: string | null;
  errors?: Record<string, string[]> | null;
  meta?: Record<string, unknown> | null;
};

type ApiSearchPayload = {
  query?: string | null;
  products?: Record<string, unknown>[];
  categories?: Record<string, unknown>[];
};

const unwrap = <T>(payload: ApiEnvelope<T>): { data: T; meta?: Record<string, unknown> | null } => {
  if (payload && payload.success && payload.data !== undefined) {
    return { data: payload.data, meta: payload.meta ?? null };
  }
  const error: ApiError = {
    status: 422,
    message: payload?.message ?? 'Request failed',
    errors: payload?.errors ?? undefined,
  };
  throw error;
};

export const searchRequest = async (params?: {
  q?: string;
  category?: string;
  min_price?: number;
  max_price?: number;
  sort?: string;
  page?: number;
  per_page?: number;
  categories_limit?: number;
}): Promise<{
  query: string | null;
  products: Product[];
  categories: Category[];
  meta?: Record<string, unknown> | null;
}> => {
  const search = new URLSearchParams();
  if (params?.q) search.set('q', params.q);
  if (params?.category) search.set('category', params.category);
  if (params?.min_price !== undefined) search.set('min_price', String(params.min_price));
  if (params?.max_price !== undefined) search.set('max_price', String(params.max_price));
  if (params?.sort) search.set('sort', params.sort);
  if (params?.page) search.set('page', String(params.page));
  if (params?.per_page) search.set('per_page', String(params.per_page));
  if (params?.categories_limit) search.set('categories_limit', String(params.categories_limit));

  const url = `${mobileApiBaseUrl}/search${search.toString() ? `?${search.toString()}` : ''}`;
  const payload = await apiFetch<ApiEnvelope<ApiSearchPayload>>(url);
  const { data, meta } = unwrap(payload);

  const products = Array.isArray(data.products) ? data.products.map(mapProduct) : [];
  const categories = Array.isArray(data.categories) ? data.categories.map(mapCategory) : [];

  return {
    query: typeof data.query === 'string' ? data.query : null,
    products,
    categories,
    meta,
  };
};

