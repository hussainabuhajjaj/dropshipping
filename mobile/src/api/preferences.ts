import { apiFetch, apiPatch } from './http';
import type { ApiError } from './http';
import { mobileApiBaseUrl } from './config';
import type { Preferences, PreferencesLookups } from '@/src/types/preferences';

export type ApiEnvelope<T> = {
  success: boolean;
  data?: T;
  message?: string | null;
  errors?: Record<string, string[]> | null;
  meta?: Record<string, unknown> | null;
};

type ApiPreferences = Record<string, unknown>;

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

const mapPreferences = (source: ApiPreferences): Preferences => {
  return {
    country: typeof source.country === 'string' ? source.country : '',
    currency: typeof source.currency === 'string' ? source.currency : '',
    size: typeof source.size === 'string' ? source.size : '',
    language: typeof source.language === 'string' ? source.language : '',
    notifications: {
      push: Boolean(source.notifications?.push),
      email: Boolean(source.notifications?.email),
      sms: Boolean(source.notifications?.sms),
    },
  };
};

const mapLookups = (source: ApiPreferences): PreferencesLookups => {
  return {
    countries: Array.isArray(source.countries) ? source.countries.map(String) : [],
    currencies: Array.isArray(source.currencies) ? source.currencies.map(String) : [],
    sizes: Array.isArray(source.sizes) ? source.sizes.map(String) : [],
    languages: Array.isArray(source.languages) ? source.languages.map(String) : [],
  };
};

export const fetchPreferencesLookups = async (): Promise<PreferencesLookups> => {
  const payload = await apiFetch<ApiEnvelope<ApiPreferences>>(`${mobileApiBaseUrl}/preferences/lookups`);
  return mapLookups(unwrap(payload));
};

export const fetchPreferences = async (): Promise<Preferences> => {
  const payload = await apiFetch<ApiEnvelope<ApiPreferences>>(`${mobileApiBaseUrl}/preferences`);
  return mapPreferences(unwrap(payload));
};

export const updatePreferences = async (
  input: Partial<Preferences>
): Promise<Preferences> => {
  const payload = await apiPatch<ApiEnvelope<ApiPreferences>>(`${mobileApiBaseUrl}/preferences`, input);
  return mapPreferences(unwrap(payload));
};
