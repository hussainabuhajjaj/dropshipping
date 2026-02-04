import { apiFetch, apiPost } from './http';
import type { ApiError } from './http';
import { mobileApiBaseUrl } from './config';
import type { ProductReview, ReviewMeta } from '@/src/types/reviews';

export type ApiEnvelope<T> = {
  success: boolean;
  data?: T;
  message?: string | null;
  errors?: Record<string, string[]> | null;
  meta?: Record<string, unknown> | null;
};

type ApiReview = Record<string, unknown>;

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

const toStringValue = (value: unknown, fallback = ''): string => {
  if (typeof value === 'string' && value.trim().length > 0) return value;
  if (typeof value === 'number' && Number.isFinite(value)) return String(value);
  return fallback;
};

const mapReview = (source: ApiReview): ProductReview => {
  return {
    id: toStringValue(source.id),
    rating: typeof source.rating === 'number' ? source.rating : Number(source.rating ?? 0),
    title: typeof source.title === 'string' ? source.title : null,
    body: typeof source.body === 'string' ? source.body : null,
    images: Array.isArray(source.images) ? source.images.filter((item) => typeof item === 'string') : [],
    verifiedPurchase: Boolean(source.verified_purchase),
    helpfulCount: typeof source.helpful_count === 'number' ? source.helpful_count : Number(source.helpful_count ?? 0),
    status: typeof source.status === 'string' ? source.status : null,
    author: typeof source.author === 'string' ? source.author : null,
    createdAt: typeof source.created_at === 'string' ? source.created_at : null,
  };
};

const mapMeta = (meta?: Record<string, unknown> | null): ReviewMeta => {
  return {
    currentPage: typeof meta?.currentPage === 'number' ? meta.currentPage : 1,
    lastPage: typeof meta?.lastPage === 'number' ? meta.lastPage : 1,
    perPage: typeof meta?.perPage === 'number' ? meta.perPage : 20,
    total: typeof meta?.total === 'number' ? meta.total : 0,
  };
};

export const fetchProductReviews = async (
  slug: string,
  params?: { page?: number; per_page?: number }
): Promise<{ items: ProductReview[]; meta: ReviewMeta }> => {
  const search = new URLSearchParams();
  if (params?.page) search.set('page', String(params.page));
  if (params?.per_page) search.set('per_page', String(params.per_page));
  const url = `${mobileApiBaseUrl}/products/${encodeURIComponent(slug)}/reviews${
    search.toString() ? `?${search}` : ''
  }`;
  const payload = await apiFetch<ApiEnvelope<ApiReview[]>>(url);
  const { data, meta } = unwrap(payload);
  const items = Array.isArray(data) ? data.map(mapReview) : [];
  return { items, meta: mapMeta(meta) };
};

export const createProductReview = async (slug: string, input: {
  order_item_id: number;
  rating: number;
  title?: string;
  body: string;
}): Promise<ProductReview> => {
  const url = `${mobileApiBaseUrl}/products/${encodeURIComponent(slug)}/reviews`;
  const payload = await apiPost<ApiEnvelope<ApiReview>>(url, input);
  return mapReview(unwrap(payload).data);
};
