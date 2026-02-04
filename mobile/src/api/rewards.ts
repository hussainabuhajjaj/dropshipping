import { apiFetch } from './http';
import type { ApiError } from './http';
import { mobileApiBaseUrl } from './config';
import type { RewardSummary, Voucher } from '@/src/types/rewards';

export type ApiEnvelope<T> = {
  success: boolean;
  data?: T;
  message?: string | null;
  errors?: Record<string, string[]> | null;
  meta?: Record<string, unknown> | null;
};

type ApiSummary = Record<string, unknown>;
type ApiVoucher = Record<string, unknown>;

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

const mapSummary = (source: ApiSummary): RewardSummary => {
  return {
    pointsBalance: typeof source.points_balance === 'number' ? source.points_balance : Number(source.points_balance ?? 0),
    tier: typeof source.tier === 'string' ? source.tier : null,
    nextTier: typeof source.next_tier === 'string' ? source.next_tier : null,
    pointsToNextTier:
      typeof source.points_to_next_tier === 'number' ? source.points_to_next_tier : Number(source.points_to_next_tier ?? 0),
    progressPercent:
      typeof source.progress_percent === 'number' ? source.progress_percent : Number(source.progress_percent ?? 0),
    voucherCount: typeof source.voucher_count === 'number' ? source.voucher_count : Number(source.voucher_count ?? 0),
    updatedAt: typeof source.updated_at === 'string' ? source.updated_at : null,
  };
};

const mapVoucher = (source: ApiVoucher): Voucher => {
  return {
    id: String(source.id ?? ''),
    code: typeof source.code === 'string' ? source.code : null,
    title: typeof source.title === 'string' ? source.title : null,
    description: typeof source.description === 'string' ? source.description : null,
    value: typeof source.value === 'string' ? source.value : null,
    type: typeof source.type === 'string' ? source.type : null,
    amount: typeof source.amount === 'number' ? source.amount : Number(source.amount ?? 0),
    minOrderTotal: typeof source.min_order_total === 'number' ? source.min_order_total : Number(source.min_order_total ?? 0),
    currency: typeof source.currency === 'string' ? source.currency : null,
    status: typeof source.status === 'string' ? source.status : null,
    startsAt: typeof source.starts_at === 'string' ? source.starts_at : null,
    endsAt: typeof source.ends_at === 'string' ? source.ends_at : null,
    redeemedAt: typeof source.redeemed_at === 'string' ? source.redeemed_at : null,
  };
};

export const fetchRewardSummary = async (): Promise<RewardSummary> => {
  const payload = await apiFetch<ApiEnvelope<ApiSummary>>(`${mobileApiBaseUrl}/rewards/summary`);
  return mapSummary(unwrap(payload));
};

export const fetchVouchers = async (): Promise<Voucher[]> => {
  const payload = await apiFetch<ApiEnvelope<ApiVoucher[]>>(`${mobileApiBaseUrl}/rewards/vouchers`);
  const data = unwrap(payload);
  return Array.isArray(data) ? data.map(mapVoucher) : [];
};
