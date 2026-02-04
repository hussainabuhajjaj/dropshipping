import { apiFetch, apiPost } from './http';
import type { ApiError } from './http';
import { mobileApiBaseUrl } from './config';

export type ApiEnvelope<T> = {
  success: boolean;
  data?: T;
  message?: string | null;
  errors?: Record<string, string[]> | null;
  meta?: Record<string, unknown> | null;
};

export type KorapayInit = {
  reference: string | null;
  checkout_url: string | null;
};

export type PaymentStatus = {
  payment_status: string | null;
  order_status: string | null;
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

export const initKorapay = async (input: {
  order_number: string;
  amount?: number;
  currency?: string;
  customer?: { email?: string; name?: string };
}): Promise<KorapayInit> => {
  const payload = await apiPost<ApiEnvelope<KorapayInit>>(
    `${mobileApiBaseUrl}/payments/korapay/init`,
    input
  );
  return unwrap(payload);
};

export const verifyKorapay = async (reference: string): Promise<PaymentStatus> => {
  const url = `${mobileApiBaseUrl}/payments/korapay/verify?reference=${encodeURIComponent(reference)}`;
  const payload = await apiFetch<ApiEnvelope<PaymentStatus>>(url);
  return unwrap(payload);
};
