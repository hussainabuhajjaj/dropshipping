import { apiFetch } from './http';
import type { ApiError } from './http';
import { mobileApiBaseUrl } from './config';
import type { AnnouncementItem, AnnouncementMeta } from '@/src/types/announcements';

export type ApiEnvelope<T> = {
  success: boolean;
  data?: T;
  message?: string | null;
  errors?: Record<string, string[]> | null;
  meta?: Record<string, unknown> | null;
};

type ApiAnnouncement = Record<string, unknown>;

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

const mapAnnouncement = (source: ApiAnnouncement): AnnouncementItem => {
  return {
    id: toStringValue(source.id),
    locale: typeof source.locale === 'string' ? source.locale : null,
    title: toStringValue(source.title, ''),
    body: toStringValue(source.body, ''),
    image: typeof source.image === 'string' ? source.image : null,
    actionHref: typeof source.actionHref === 'string' ? source.actionHref : null,
    createdAt: typeof source.createdAt === 'string' ? source.createdAt : null,
  };
};

const mapMeta = (meta?: Record<string, unknown> | null): AnnouncementMeta => {
  return {
    currentPage: typeof meta?.currentPage === 'number' ? meta.currentPage : 1,
    lastPage: typeof meta?.lastPage === 'number' ? meta.lastPage : 1,
    perPage: typeof meta?.perPage === 'number' ? meta.perPage : 20,
    total: typeof meta?.total === 'number' ? meta.total : 0,
  };
};

export const fetchAnnouncements = async (params?: {
  page?: number;
  per_page?: number;
}): Promise<{ items: AnnouncementItem[]; meta: AnnouncementMeta }> => {
  const search = new URLSearchParams();
  if (params?.page) search.set('page', String(params.page));
  if (params?.per_page) search.set('per_page', String(params.per_page));
  const url = `${mobileApiBaseUrl}/announcements${search.toString() ? `?${search}` : ''}`;
  const payload = await apiFetch<ApiEnvelope<ApiAnnouncement[]>>(url);
  const { data, meta } = unwrap(payload);
  const items = Array.isArray(data) ? data.map(mapAnnouncement) : [];
  return { items, meta: mapMeta(meta) };
};

