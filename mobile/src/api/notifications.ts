import { apiFetch, apiPost } from './http';
import type { ApiError } from './http';
import { mobileApiBaseUrl } from './config';
import type { NotificationItem, NotificationMeta } from '@/src/types/notifications';

export type ApiEnvelope<T> = {
  success: boolean;
  data?: T;
  message?: string | null;
  errors?: Record<string, string[]> | null;
  meta?: Record<string, unknown> | null;
};

type ApiNotification = Record<string, unknown>;

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

const mapNotification = (source: ApiNotification): NotificationItem => {
  return {
    id: toStringValue(source.id),
    type: typeof source.type === 'string' ? source.type : null,
    title: typeof source.title === 'string' ? source.title : null,
    body: typeof source.body === 'string' ? source.body : null,
    actionUrl: typeof source.action_url === 'string' ? source.action_url : null,
    actionLabel: typeof source.action_label === 'string' ? source.action_label : null,
    readAt: typeof source.read_at === 'string' ? source.read_at : null,
    createdAt: typeof source.created_at === 'string' ? source.created_at : null,
  };
};

const mapMeta = (meta?: Record<string, unknown> | null): NotificationMeta => {
  return {
    currentPage: typeof meta?.currentPage === 'number' ? meta.currentPage : 1,
    lastPage: typeof meta?.lastPage === 'number' ? meta.lastPage : 1,
    perPage: typeof meta?.perPage === 'number' ? meta.perPage : 20,
    total: typeof meta?.total === 'number' ? meta.total : 0,
    unreadCount: typeof meta?.unreadCount === 'number' ? meta.unreadCount : 0,
  };
};

export const fetchNotifications = async (params?: {
  page?: number;
  per_page?: number;
}): Promise<{ items: NotificationItem[]; meta: NotificationMeta }> => {
  const search = new URLSearchParams();
  if (params?.page) search.set('page', String(params.page));
  if (params?.per_page) search.set('per_page', String(params.per_page));
  const url = `${mobileApiBaseUrl}/notifications${search.toString() ? `?${search}` : ''}`;
  const payload = await apiFetch<ApiEnvelope<ApiNotification[]>>(url);
  const { data, meta } = unwrap(payload);
  const items = Array.isArray(data) ? data.map(mapNotification) : [];
  return { items, meta: mapMeta(meta) };
};

export const markNotificationsRead = async (input: { ids?: string[]; id?: string }) => {
  const payload = await apiPost<ApiEnvelope<{ ok?: boolean; read_ids?: string[]; unread_count?: number }>>(
    `${mobileApiBaseUrl}/notifications/mark-read`,
    input
  );
  return unwrap(payload).data;
};

export const registerExpoToken = async (token: string) => {
  const payload = await apiPost<ApiEnvelope<{ ok?: boolean; token?: string }>>(
    `${mobileApiBaseUrl}/notifications/expo-token`,
    { token }
  );
  return unwrap(payload).data;
};

export const removeExpoToken = async (token: string) => {
  const payload = await apiPost<ApiEnvelope<{ ok?: boolean; token?: string }>>(
    `${mobileApiBaseUrl}/notifications/expo-token`,
    { token, _method: 'DELETE' }
  );
  return unwrap(payload).data;
};
