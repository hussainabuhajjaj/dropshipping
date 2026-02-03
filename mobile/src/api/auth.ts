import { apiFetch, apiPatch, apiPost, ApiError } from './http';
import { mobileApiBaseUrl } from './config';

export type ApiEnvelope<T> = {
  success: boolean;
  data?: T;
  message?: string | null;
  errors?: Record<string, string[]> | null;
};

export type AuthUser = {
  id: number | string;
  name?: string | null;
  email?: string | null;
  first_name?: string | null;
  last_name?: string | null;
  phone?: string | null;
  avatar?: string | null;
  email_verified_at?: string | null;
  is_verified?: boolean | null;
  phone_verified_at?: string | null;
  is_phone_verified?: boolean | null;
};

export type AuthResponse = {
  user: AuthUser;
  token?: string | null;
  token_type?: string | null;
  expires_at?: string | null;
};

const unwrap = <T>(payload: ApiEnvelope<T>): T => {
  if (payload && payload.success && payload.data !== undefined) {
    return payload.data;
  }
  const message = payload?.message ?? 'Request failed';
  const error: ApiError = {
    status: 422,
    message: message || 'Request failed',
    errors: payload?.errors ?? undefined,
  };
  throw error;
};

export const loginRequest = async (body: {
  email: string;
  password: string;
  device_name?: string;
}): Promise<AuthResponse> => {
  const payload = await apiPost<ApiEnvelope<AuthResponse>>(
    `${mobileApiBaseUrl}/auth/login`,
    body
  );
  return unwrap(payload);
};

export const registerRequest = async (body: {
  email: string;
  password: string;
  phone?: string;
  name?: string;
  first_name?: string;
  last_name?: string;
  device_name?: string;
  avatar?: string;
}): Promise<AuthResponse> => {
  const payload = await apiPost<ApiEnvelope<AuthResponse>>(
    `${mobileApiBaseUrl}/auth/register`,
    body
  );
  return unwrap(payload);
};

export const meRequest = async (): Promise<AuthUser> => {
  const payload = await apiFetch<ApiEnvelope<AuthUser>>(`${mobileApiBaseUrl}/auth/me`);
  return unwrap(payload);
};

export const resendVerificationRequest = async (): Promise<{ ok: boolean }> => {
  const payload = await apiPost<ApiEnvelope<{ ok: boolean }>>(
    `${mobileApiBaseUrl}/auth/verify/resend`,
    {}
  );
  return unwrap(payload);
};

export const verifyEmailOtpRequest = async (body: { code: string }): Promise<AuthUser> => {
  const payload = await apiPost<ApiEnvelope<AuthUser>>(
    `${mobileApiBaseUrl}/auth/verify/email`,
    body
  );
  return unwrap(payload);
};

export const logoutRequest = async (): Promise<{ ok: boolean }> => {
  const payload = await apiPost<ApiEnvelope<{ ok: boolean }>>(`${mobileApiBaseUrl}/auth/logout`, {});
  return unwrap(payload);
};

export const updateProfileRequest = async (body: {
  name?: string;
  first_name?: string;
  last_name?: string;
  email?: string;
  phone?: string;
  avatar?: string | null;
}): Promise<AuthUser> => {
  const payload = await apiPatch<ApiEnvelope<AuthUser>>(`${mobileApiBaseUrl}/auth/profile`, body);
  return unwrap(payload);
};

export const sendPhoneOtpRequest = async (body?: { phone?: string }): Promise<{ ok: boolean }> => {
  const payload = await apiPost<ApiEnvelope<{ ok: boolean }>>(
    `${mobileApiBaseUrl}/auth/phone/send-otp`,
    body ?? {}
  );
  return unwrap(payload);
};

export const verifyPhoneOtpRequest = async (body: { code: string }): Promise<AuthUser> => {
  const payload = await apiPost<ApiEnvelope<AuthUser>>(
    `${mobileApiBaseUrl}/auth/phone/verify-otp`,
    body
  );
  return unwrap(payload);
};
