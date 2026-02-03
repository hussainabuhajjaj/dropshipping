export type CartLine = {
  id: string;
  productId: string;
  variantId?: string | null;
  name: string;
  price: number;
  compareAt?: number | null;
  quantity: number;
  image?: string | null;
  currency?: string | null;
  slug?: string | null;
};

export type CartSummary = {
  currency: string;
  subtotal: number;
  shipping: number;
  discount: number;
  tax: number;
  total: number;
  coupon?: Record<string, unknown> | null;
  discountLabel?: string | null;
  minimumRequirement?: Record<string, unknown> | null;
};

export type CartPayload = {
  lines: CartLine[];
  summary: CartSummary;
};
