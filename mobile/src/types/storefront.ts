export type Category = {
  id: string;
  name: string;
  slug?: string;
  count: number;
  image?: string | null;
  heroTitle?: string | null;
  heroSubtitle?: string | null;
  heroImage?: string | null;
  heroCtaLabel?: string | null;
  heroCtaLink?: string | null;
  metaTitle?: string | null;
  metaDescription?: string | null;
  accent?: string | null;
  children?: Category[];
};

export type ProductVariant = {
  id: string | number;
  title?: string | null;
  price?: number | null;
  compare_at_price?: number | null;
  sku?: string | null;
  currency?: string | null;
  cj_vid?: string | null;
  stock_on_hand?: number | null;
  low_stock_threshold?: number | null;
};

export type Product = {
  id: string;
  slug: string;
  name: string;
  price: number;
  compareAt?: number | null;
  rating: number;
  reviews: number;
  image?: string | null;
  badge?: string | null;
  media?: string[];
  description?: string | null;
  category?: string | null;
  categoryId?: string | number | null;
  currency?: string | null;
  variants?: ProductVariant[];
  leadTimeDays?: number | null;
  specs?: Record<string, unknown> | null;
  metaTitle?: string | null;
  metaDescription?: string | null;
};

export type PromoSlide = {
  id: string;
  kicker?: string | null;
  title: string;
  subtitle?: string | null;
  cta: string;
  href?: string | null;
  image?: string | null;
  tone?: string | null;
};

export type Banner = {
  id: string;
  title?: string | null;
  description?: string | null;
  type?: string | null;
  displayType?: string | null;
  image?: string | null;
  imageMode?: string | null;
  backgroundColor?: string | null;
  textColor?: string | null;
  badgeText?: string | null;
  badgeColor?: string | null;
  ctaText?: string | null;
  ctaUrl?: string | null;
  endsAt?: string | null;
};

export type BannerGroups = {
  hero: Banner[];
  carousel: Banner[];
  strip: Banner[];
  full: Banner[];
  popup: Banner[];
};

export type NewsletterPopup = {
  enabled: boolean;
  title?: string | null;
  body?: string | null;
  incentive?: string | null;
  image?: string | null;
  delaySeconds?: number | null;
  dismissDays?: number | null;
  source?: string | null;
};

export type TopStripItem = {
  icon: string;
  title: string;
  subtitle: string;
};

export type ValueProp = {
  title: string;
  body: string;
};

export type HomePayload = {
  currency?: string | null;
  hero: PromoSlide[];
  categories: Category[];
  flashDeals: Product[];
  trending: Product[];
  recommended: Product[];
  topStrip: TopStripItem[];
  valueProps: ValueProp[];
  seasonalDrops: Record<string, unknown>[];
  banners?: BannerGroups;
  newsletterPopup?: NewsletterPopup | null;
};
