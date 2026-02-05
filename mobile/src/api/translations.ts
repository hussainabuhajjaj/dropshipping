import { apiFetch } from './http';
import type { ApiError } from './http';
import { mobileApiBaseUrl } from './config';

export type ApiEnvelope<T> = {
  success: boolean;
  data?: T;
  message?: string | null;
  errors?: Record<string, string[]> | null;
  meta?: Record<string, unknown> | null;
};

export type TranslationPayload = {
  locale: string;
  fallback: string;
  translations: Record<string, string>;
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

export const fetchTranslations = async (locale?: string): Promise<TranslationPayload> => {
  const query = locale ? `?locale=${encodeURIComponent(locale)}` : '';
  const payload = await apiFetch<ApiEnvelope<TranslationPayload>>(`${mobileApiBaseUrl}/translations${query}`);
  return unwrap(payload);
};

export const registerMissingTranslations = async (keys: string[]): Promise<void> => {
  if (!Array.isArray(keys) || keys.length === 0) return;
  await apiFetch<ApiEnvelope<{ count?: number }>>(`${mobileApiBaseUrl}/translations/register`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ keys }),
  });
};
