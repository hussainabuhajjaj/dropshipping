import { apiFetch } from './http';
import type { ApiError } from './http';
import { mobileApiBaseUrl } from './config';
import type { Wallet, GiftCard } from '@/src/types/rewards';

export type ApiEnvelope<T> = {
  success: boolean;
  data?: T;
  message?: string | null;
  errors?: Record<string, string[]> | null;
  meta?: Record<string, unknown> | null;
};

type ApiWallet = Record<string, unknown>;
type ApiGiftCard = Record<string, unknown>;

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

const mapGiftCard = (source: ApiGiftCard): GiftCard => {
  return {
    id: String(source.id ?? ''),
    code: typeof source.code === 'string' ? source.code : null,
    balance: typeof source.balance === 'number' ? source.balance : Number(source.balance ?? 0),
    currency: typeof source.currency === 'string' ? source.currency : null,
    status: typeof source.status === 'string' ? source.status : null,
    expiresAt: typeof source.expires_at === 'string' ? source.expires_at : null,
  };
};

export const fetchWallet = async (): Promise<Wallet> => {
  const payload = await apiFetch<ApiEnvelope<ApiWallet>>(`${mobileApiBaseUrl}/wallet`);
  const data = unwrap(payload);
  const giftCards = Array.isArray(data.gift_cards) ? data.gift_cards.map(mapGiftCard) : [];
  return {
    giftCards,
    savedCoupons: Array.isArray(data.saved_coupons) ? data.saved_coupons : [],
    availableCoupons: Array.isArray(data.available_coupons) ? data.available_coupons : [],
  };
};
