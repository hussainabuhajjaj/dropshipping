import { apiPost } from './http';
import type { ApiError } from './http';
import { mobileApiBaseUrl } from './config';

export type ApiEnvelope<T> = {
  success: boolean;
  data?: T;
  message?: string | null;
  errors?: Record<string, string[]> | null;
  meta?: Record<string, unknown> | null;
};

export type CheckoutPreview = {
  subtotal: number;
  shipping: number;
  discount: number;
  tax: number;
  total: number;
  currency: string;
  applied_promotions?: Record<string, unknown>[];
  minimum_cart_requirement?: Record<string, unknown> | null;
};

export type CheckoutConfirm = {
  order_number: string | null;
  payment_reference: string | null;
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

export const previewCheckout = async (input?: {
  email?: string;
  country?: string;
}): Promise<CheckoutPreview> => {
  const payload = await apiPost<ApiEnvelope<CheckoutPreview>>(`${mobileApiBaseUrl}/checkout/preview`, {
    email: input?.email,
    country: input?.country,
  });
  return unwrap(payload);
};

export const confirmCheckout = async (input: {
  email: string;
  phone: string;
  first_name: string;
  last_name?: string;
  line1: string;
  line2?: string;
  city: string;
  state?: string;
  postal_code?: string;
  country: string;
  delivery_notes?: string;
  payment_method?: string;
}): Promise<CheckoutConfirm> => {
  const payload = await apiPost<ApiEnvelope<CheckoutConfirm>>(
    `${mobileApiBaseUrl}/checkout/confirm`,
    input
  );
  return unwrap(payload);
};
