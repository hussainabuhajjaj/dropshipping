export type RewardSummary = {
  pointsBalance: number;
  tier?: string | null;
  nextTier?: string | null;
  pointsToNextTier?: number | null;
  progressPercent?: number | null;
  voucherCount?: number | null;
  updatedAt?: string | null;
};

export type Voucher = {
  id: string;
  code?: string | null;
  title?: string | null;
  description?: string | null;
  value?: string | null;
  type?: string | null;
  amount?: number | null;
  minOrderTotal?: number | null;
  currency?: string | null;
  status?: string | null;
  startsAt?: string | null;
  endsAt?: string | null;
  redeemedAt?: string | null;
};

export type GiftCard = {
  id: string;
  code?: string | null;
  balance?: number | null;
  currency?: string | null;
  status?: string | null;
  expiresAt?: string | null;
};

export type Wallet = {
  giftCards: GiftCard[];
  savedCoupons: Array<Record<string, unknown>>;
  availableCoupons: Array<Record<string, unknown>>;
};
