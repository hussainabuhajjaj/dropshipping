import { apiFetch } from './http';
import type { ApiError } from './http';
import { mobileApiBaseUrl } from './config';
import type {
  Banner,
  BannerGroups,
  Category,
  HomePayload,
  NewsletterPopup,
  Product,
  PromoSlide,
  TopStripItem,
  ValueProp,
} from '@/src/types/storefront';

export type ApiEnvelope<T> = {
  success: boolean;
  data?: T;
  message?: string | null;
  errors?: Record<string, string[]> | null;
  meta?: Record<string, unknown> | null;
};

type ApiCategory = Record<string, unknown>;

type ApiProduct = Record<string, unknown>;

type ApiHome = {
  currency?: string | null;
  hero?: Array<Record<string, unknown>>;
  categories?: ApiCategory[];
  flashDeals?: ApiProduct[];
  trending?: ApiProduct[];
  recommended?: ApiProduct[];
  topStrip?: Array<Record<string, unknown>>;
  valueProps?: Array<Record<string, unknown>>;
  seasonalDrops?: Array<Record<string, unknown>>;
  banners?: Record<string, unknown>;
  newsletterPopup?: Record<string, unknown> | null;
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

const toStringValue = (value: unknown, fallback = ''): string => {
  if (typeof value === 'string' && value.trim().length > 0) return value;
  if (typeof value === 'number' && Number.isFinite(value)) return String(value);
  return fallback;
};

const toNumberValue = (value: unknown, fallback = 0): number => {
  if (typeof value === 'number' && Number.isFinite(value)) return value;
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : fallback;
};

const decodeHtmlEntities = (value: string): string => {
  return value
    .replace(/&nbsp;/gi, ' ')
    .replace(/&amp;/gi, '&')
    .replace(/&lt;/gi, '<')
    .replace(/&gt;/gi, '>')
    .replace(/&quot;/gi, '"')
    .replace(/&#39;/g, "'");
};

const stripHtml = (value: string): string => {
  const withBreaks = value
    .replace(/<\s*br\s*\/?>/gi, '\n')
    .replace(/<\/\s*p\s*>/gi, '\n')
    .replace(/<\s*p[^>]*>/gi, '');
  const textOnly = withBreaks.replace(/<[^>]+>/g, '');
  return decodeHtmlEntities(textOnly).replace(/\n{3,}/g, '\n\n').trim();
};

const mapCategory = (source: ApiCategory): Category => {
  const children = Array.isArray(source.children)
    ? (source.children as ApiCategory[]).map(mapCategory)
    : [];

  return {
    id: toStringValue(source.id),
    name: toStringValue(source.name, 'Category'),
    slug: toStringValue(source.slug),
    count: toNumberValue(source.count ?? source.products_count ?? 0, 0),
    image: typeof source.image === 'string' ? source.image : null,
    accent: typeof source.accent === 'string' ? source.accent : null,
    children: children.length > 0 ? children : undefined,
  };
};

export const mapProduct = (source: ApiProduct): Product => {
  const media = Array.isArray(source.media)
    ? source.media.filter((item) => typeof item === 'string')
    : [];
  const image =
    typeof source.image === 'string'
      ? source.image
      : media.length > 0
        ? media[0]
        : null;
  const price = toNumberValue(source.price ?? source.selling_price ?? 0, 0);
  const compareAtRaw = source.compare_at_price ?? source.compareAt ?? null;
  const compareAt = compareAtRaw === null ? null : toNumberValue(compareAtRaw, 0);
  const ratingRaw = source.rating ?? source.reviews_avg_rating ?? 0;
  const ratingCountRaw = source.rating_count ?? source.reviews_count ?? 0;
  const badge =
    typeof source.badge === 'string'
      ? source.badge
      : compareAt && compareAt > price
        ? 'Deal'
        : null;
  const descriptionSource = typeof source.description === 'string' ? source.description : null;
  const description = descriptionSource ? stripHtml(descriptionSource) : null;

  return {
    id: toStringValue(source.id),
    slug: toStringValue(source.slug),
    name: toStringValue(source.name, 'Product'),
    price,
    compareAt: compareAt && compareAt > 0 ? compareAt : null,
    rating: toNumberValue(ratingRaw, 0),
    reviews: toNumberValue(ratingCountRaw, 0),
    image,
    badge,
    media,
    description,
    category: typeof source.category === 'string' ? source.category : null,
    categoryId:
      typeof source.category_id === 'number' || typeof source.category_id === 'string'
        ? source.category_id
        : null,
    currency: typeof source.currency === 'string' ? source.currency : null,
    variants: Array.isArray(source.variants)
      ? source.variants.map((variant) => {
          const value = variant as Record<string, unknown>;
          return {
            id: toStringValue(value.id),
            title: typeof value.title === 'string' ? value.title : null,
            price: typeof value.price === 'number' ? value.price : null,
            compare_at_price:
              typeof value.compare_at_price === 'number' ? value.compare_at_price : null,
            sku: typeof value.sku === 'string' ? value.sku : null,
            currency: typeof value.currency === 'string' ? value.currency : null,
            cj_vid: typeof value.cj_vid === 'string' ? value.cj_vid : null,
            stock_on_hand:
              typeof value.stock_on_hand === 'number' ? value.stock_on_hand : null,
            low_stock_threshold:
              typeof value.low_stock_threshold === 'number' ? value.low_stock_threshold : null,
          };
        })
      : undefined,
    leadTimeDays:
      typeof source.lead_time_days === 'number' ? source.lead_time_days : null,
    specs: typeof source.specs === 'object' && source.specs !== null ? source.specs : null,
    metaTitle: typeof source.meta_title === 'string' ? source.meta_title : null,
    metaDescription: typeof source.meta_description === 'string' ? source.meta_description : null,
  };
};

const mapSlide = (source: Record<string, unknown>, index: number): PromoSlide => {
  const title = toStringValue(source.title ?? source.kicker, `Slide ${index + 1}`);
  const subtitle = toStringValue(source.subtitle ?? source.description, '');
  const cta = toStringValue(source.cta ?? source.ctaText ?? source.primary_label, 'Shop now');
  const href = toStringValue(source.href ?? source.ctaUrl ?? source.primary_href, '');
  const image = typeof source.image === 'string' ? source.image : null;
  const tone = typeof source.tone === 'string' ? source.tone : null;
  const kicker = toStringValue(source.kicker ?? source.badgeText, 'Featured');

  return {
    id: toStringValue(source.id, `slide-${index}`),
    kicker,
    title,
    subtitle,
    cta,
    href: href || null,
    image,
    tone,
  };
};

const mapBanner = (source: Record<string, unknown>, index: number): Banner => {
  const rawDescription = typeof source.description === 'string' ? source.description : '';
  return {
    id: toStringValue(source.id, `banner-${index}`),
    title: toStringValue(source.title, ''),
    description: rawDescription ? stripHtml(rawDescription) : '',
    type: toStringValue(source.type, ''),
    displayType: toStringValue(source.displayType ?? source.display_type, ''),
    image: typeof source.image === 'string' ? source.image : typeof source.imagePath === 'string' ? source.imagePath : null,
    imageMode: typeof source.imageMode === 'string' ? source.imageMode : null,
    backgroundColor: typeof source.backgroundColor === 'string' ? source.backgroundColor : null,
    textColor: typeof source.textColor === 'string' ? source.textColor : null,
    badgeText: typeof source.badgeText === 'string' ? source.badgeText : null,
    badgeColor: typeof source.badgeColor === 'string' ? source.badgeColor : null,
    ctaText: typeof source.ctaText === 'string' ? source.ctaText : null,
    ctaUrl: typeof source.ctaUrl === 'string' ? source.ctaUrl : null,
    endsAt: typeof source.endsAt === 'string' ? source.endsAt : typeof source.ends_at === 'string' ? source.ends_at : null,
  };
};

const mapBannerGroup = (value: unknown): Banner[] => {
  return Array.isArray(value) ? value.map((item, index) => mapBanner(item as Record<string, unknown>, index)) : [];
};

const mapBannerGroups = (value: unknown): BannerGroups | undefined => {
  if (!value || typeof value !== 'object') return undefined;
  const payload = value as Record<string, unknown>;
  return {
    hero: mapBannerGroup(payload.hero),
    carousel: mapBannerGroup(payload.carousel),
    strip: mapBannerGroup(payload.strip),
    full: mapBannerGroup(payload.full),
    popup: mapBannerGroup(payload.popup),
  };
};

const mapNewsletterPopup = (value: unknown): NewsletterPopup | null => {
  if (!value || typeof value !== 'object') return null;
  const payload = value as Record<string, unknown>;
  return {
    enabled: Boolean(payload.enabled),
    title: typeof payload.title === 'string' ? payload.title : null,
    body: typeof payload.body === 'string' ? stripHtml(payload.body) : null,
    incentive: typeof payload.incentive === 'string' ? payload.incentive : null,
    image: typeof payload.image === 'string' ? payload.image : null,
    delaySeconds: typeof payload.delaySeconds === 'number' ? payload.delaySeconds : null,
    dismissDays: typeof payload.dismissDays === 'number' ? payload.dismissDays : null,
    source: typeof payload.source === 'string' ? payload.source : null,
  };
};

const mapTopStrip = (items: Array<Record<string, unknown>>): TopStripItem[] => {
  return items.map((item, index) => ({
    icon: toStringValue(item.icon, index === 1 ? 'check-circle' : index === 2 ? 'truck' : 'zap'),
    title: toStringValue(item.title, 'Highlight'),
    subtitle: toStringValue(item.subtitle, ''),
  }));
};

const mapValueProps = (items: Array<Record<string, unknown>>): ValueProp[] => {
  return items.map((item) => ({
    title: toStringValue(item.title, 'Value'),
    body: toStringValue(item.body, ''),
  }));
};

const mapHome = (payload: ApiHome): HomePayload => {
  return {
    currency: typeof payload.currency === 'string' ? payload.currency : null,
    hero: Array.isArray(payload.hero) ? payload.hero.map(mapSlide) : [],
    categories: Array.isArray(payload.categories) ? payload.categories.map(mapCategory) : [],
    flashDeals: Array.isArray(payload.flashDeals) ? payload.flashDeals.map(mapProduct) : [],
    trending: Array.isArray(payload.trending) ? payload.trending.map(mapProduct) : [],
    recommended: Array.isArray(payload.recommended) ? payload.recommended.map(mapProduct) : [],
    topStrip: Array.isArray(payload.topStrip) ? mapTopStrip(payload.topStrip) : [],
    valueProps: Array.isArray(payload.valueProps) ? mapValueProps(payload.valueProps) : [],
    seasonalDrops: Array.isArray(payload.seasonalDrops) ? payload.seasonalDrops : [],
    banners: mapBannerGroups(payload.banners),
    newsletterPopup: mapNewsletterPopup(payload.newsletterPopup),
  };
};

export const fetchHome = async (): Promise<HomePayload> => {
  const payload = await apiFetch<ApiEnvelope<ApiHome>>(`${mobileApiBaseUrl}/home`);
  return mapHome(unwrap(payload));
};

export const fetchCategories = async (): Promise<Category[]> => {
  const payload = await apiFetch<ApiEnvelope<ApiCategory[]>>(`${mobileApiBaseUrl}/categories`);
  return unwrap(payload).map(mapCategory);
};

export const fetchCategory = async (slug: string): Promise<{
  category: Category | null;
  products: Product[];
  pagination: Record<string, unknown> | null;
}> => {
  const payload = await apiFetch<ApiEnvelope<Record<string, unknown>>>(
    `${mobileApiBaseUrl}/categories/${encodeURIComponent(slug)}`
  );
  const data = unwrap(payload);
  const category = data?.category ? mapCategory(data.category as ApiCategory) : null;
  const products = Array.isArray(data?.products)
    ? (data.products as ApiProduct[]).map(mapProduct)
    : [];
  const pagination =
    typeof data?.pagination === 'object' && data.pagination !== null
      ? (data.pagination as Record<string, unknown>)
      : null;
  return { category, products, pagination };
};

export const fetchProducts = async (params?: {
  q?: string;
  category?: string;
  min_price?: number;
  max_price?: number;
  sort?: string;
  page?: number;
  per_page?: number;
}): Promise<{ items: Product[]; meta?: Record<string, unknown> | null }> => {
  const search = new URLSearchParams();
  if (params?.q) search.set('q', params.q);
  if (params?.category) search.set('category', params.category);
  if (params?.min_price !== undefined) search.set('min_price', String(params.min_price));
  if (params?.max_price !== undefined) search.set('max_price', String(params.max_price));
  if (params?.sort) search.set('sort', params.sort);
  if (params?.page) search.set('page', String(params.page));
  if (params?.per_page) search.set('per_page', String(params.per_page));

  const url = `${mobileApiBaseUrl}/products${search.toString() ? `?${search.toString()}` : ''}`;
  const payload = await apiFetch<ApiEnvelope<ApiProduct[]>>(url);
  return { items: unwrap(payload).map(mapProduct), meta: payload.meta ?? null };
};

export const fetchProduct = async (slug: string): Promise<Product> => {
  const payload = await apiFetch<ApiEnvelope<ApiProduct>>(
    `${mobileApiBaseUrl}/products/${encodeURIComponent(slug)}`
  );
  return mapProduct(unwrap(payload));
};

export const fetchProductsBySlugs = async (slugs: string[]): Promise<Product[]> => {
  const unique = Array.from(new Set(slugs.filter((slug) => slug && slug.trim().length > 0)));
  if (unique.length === 0) return [];

  const results = await Promise.allSettled(unique.map((slug) => fetchProduct(slug)));
  const lookup = new Map<string, Product>();
  let failed = 0;
  results.forEach((result, index) => {
    if (result.status === 'fulfilled') {
      lookup.set(unique[index], result.value);
    } else {
      failed += 1;
    }
  });

  const ordered = slugs
    .map((slug) => lookup.get(slug))
    .filter((item): item is Product => Boolean(item));

  if (ordered.length === 0 && failed > 0) {
    const firstError = results.find((result) => result.status === 'rejected') as
      | PromiseRejectedResult
      | undefined;
    throw (firstError?.reason as Error) ?? new Error('Unable to load products.');
  }

  return ordered;
};
