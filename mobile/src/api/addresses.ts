import { apiFetch, apiPatch, apiPost } from './http';
import type { ApiError } from './http';
import { mobileApiBaseUrl } from './config';
import type { Address } from '@/src/types/address';

export type ApiEnvelope<T> = {
  success: boolean;
  data?: T;
  message?: string | null;
  errors?: Record<string, string[]> | null;
  meta?: Record<string, unknown> | null;
};

type ApiAddress = Record<string, unknown>;

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

const mapAddress = (source: ApiAddress): Address => {
  return {
    id: toStringValue(source.id),
    name: typeof source.name === 'string' ? source.name : null,
    phone: typeof source.phone === 'string' ? source.phone : null,
    line1: toStringValue(source.line1),
    line2: typeof source.line2 === 'string' ? source.line2 : null,
    city: typeof source.city === 'string' ? source.city : null,
    state: typeof source.state === 'string' ? source.state : null,
    postalCode: typeof source.postal_code === 'string' ? source.postal_code : null,
    country: typeof source.country === 'string' ? source.country : null,
    type: typeof source.type === 'string' ? source.type : null,
    isDefault: Boolean(source.is_default),
    createdAt: typeof source.created_at === 'string' ? source.created_at : null,
    updatedAt: typeof source.updated_at === 'string' ? source.updated_at : null,
  };
};

export const fetchAddresses = async (): Promise<Address[]> => {
  const payload = await apiFetch<ApiEnvelope<ApiAddress[]>>(`${mobileApiBaseUrl}/account/addresses`);
  return unwrap(payload).map(mapAddress);
};

export type AddressInput = {
  name?: string | null;
  phone?: string | null;
  line1: string;
  line2?: string | null;
  city?: string | null;
  state?: string | null;
  postal_code?: string | null;
  country?: string | null;
  type?: string | null;
  is_default?: boolean;
};

export const createAddress = async (input: AddressInput): Promise<Address> => {
  const payload = await apiPost<ApiEnvelope<ApiAddress>>(`${mobileApiBaseUrl}/account/addresses`, input);
  return mapAddress(unwrap(payload));
};

export const updateAddress = async (id: string, input: Partial<AddressInput>): Promise<Address> => {
  const payload = await apiPatch<ApiEnvelope<ApiAddress>>(
    `${mobileApiBaseUrl}/account/addresses/${encodeURIComponent(id)}`,
    input
  );
  return mapAddress(unwrap(payload));
};

export const deleteAddress = async (id: string): Promise<boolean> => {
  const payload = await apiFetch<ApiEnvelope<{ ok?: boolean }>>(
    `${mobileApiBaseUrl}/account/addresses/${encodeURIComponent(id)}`,
    { method: 'DELETE' }
  );
  const data = unwrap(payload);
  return Boolean(data?.ok);
};
