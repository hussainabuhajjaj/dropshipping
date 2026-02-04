export type ProductReview = {
  id: string;
  rating: number;
  title?: string | null;
  body?: string | null;
  images?: string[];
  verifiedPurchase?: boolean;
  helpfulCount?: number;
  status?: string | null;
  author?: string | null;
  createdAt?: string | null;
};

export type ReviewMeta = {
  currentPage: number;
  lastPage: number;
  perPage: number;
  total: number;
};
