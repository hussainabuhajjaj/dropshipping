import { apiFetch } from './http';
import { mobileApiBaseUrl } from './config';
import { mapProduct } from './catalog';
import type { ApiEnvelope } from './catalog';
import type { Product } from '@/src/types/storefront';

export type Collection = {
  id: number;
  slug: string;
  type: string | null;
  title: string | null;
  description: string | null;
  hero_kicker: string | null;
  hero_subtitle: string | null;
  hero_image: string | null;
  starts_at: string | null;
  ends_at: string | null;
};

export type CollectionDetail = Collection & {
  hero_cta_label: string | null;
  hero_cta_url: string | null;
  content: string | null;
  seo_title: string | null;
  seo_description: string | null;
};

export type CollectionShowPayload = {
  collection: CollectionDetail;
  products: Product[];
  meta: {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
  } | null;
};

const toStringOrNull = (value: unknown): string | null => {
  if (typeof value === 'string' && value.length > 0) return value;
  return null;
};

const toNumberValue = (value: unknown, fallback = 0): number => {
  if (typeof value === 'number' && Number.isFinite(value)) return value;
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : fallback;
};

const mapCollection = (source: Record<string, unknown>): Collection => ({
  id: toNumberValue(source.id),
  slug: toStringOrNull(source.slug) ?? '',
  type: toStringOrNull(source.type),
  title: toStringOrNull(source.title),
  description: toStringOrNull(source.description),
  hero_kicker: toStringOrNull(source.hero_kicker),
  hero_subtitle: toStringOrNull(source.hero_subtitle),
  hero_image: toStringOrNull(source.hero_image),
  starts_at: toStringOrNull(source.starts_at),
  ends_at: toStringOrNull(source.ends_at),
});

const mapCollectionDetail = (source: Record<string, unknown>): CollectionDetail => ({
  ...mapCollection(source),
  hero_cta_label: toStringOrNull(source.hero_cta_label),
  hero_cta_url: toStringOrNull(source.hero_cta_url),
  content: toStringOrNull(source.content),
  seo_title: toStringOrNull(source.seo_title),
  seo_description: toStringOrNull(source.seo_description),
});

const unwrap = <T>(payload: ApiEnvelope<T>): T => {
  if (payload?.success && payload.data !== undefined) return payload.data;
  throw { status: 422, message: payload?.message ?? 'Request failed', errors: payload?.errors };
};

export const fetchCollections = async (): Promise<Collection[]> => {
  const payload = await apiFetch<ApiEnvelope<Record<string, unknown>[]>>(
    `${mobileApiBaseUrl}/collections`
  );
  return unwrap(payload).map(mapCollection);
};

export const fetchCollection = async (
  slug: string,
  params?: { page?: number; per_page?: number }
): Promise<CollectionShowPayload> => {
  const search = new URLSearchParams();
  if (params?.page) search.set('page', String(params.page));
  if (params?.per_page) search.set('per_page', String(params.per_page));

  const qs = search.toString();
  const url = `${mobileApiBaseUrl}/collections/${encodeURIComponent(slug)}${qs ? `?${qs}` : ''}`;

  const payload = await apiFetch<ApiEnvelope<Record<string, unknown>>>(url);
  const data = unwrap(payload);

  const collection = mapCollectionDetail(
    (data.collection as Record<string, unknown>) ?? {}
  );

  const products = Array.isArray(data.products)
    ? (data.products as Record<string, unknown>[]).map(mapProduct)
    : [];

  const rawMeta = payload.meta as Record<string, unknown> | null | undefined;
  const meta = rawMeta
    ? {
        currentPage: toNumberValue(rawMeta.currentPage, 1),
        lastPage: toNumberValue(rawMeta.lastPage, 1),
        perPage: toNumberValue(rawMeta.perPage, 18),
        total: toNumberValue(rawMeta.total, 0),
      }
    : null;

  return { collection, products, meta };
};
