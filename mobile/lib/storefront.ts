import { apiFetch } from './api';
import {
  categories as fallbackCategories,
  products as fallbackProducts,
  promoSlides,
  topStrip,
  valueProps,
  Category,
  Product,
  PromoSlide,
} from './mockData';
import { Order, OrderItem, TrackingEvent, orders as fallbackOrders } from './mockOrders';

type TopStripItem = {
  icon: string;
  title: string;
  subtitle: string;
};

type ValueProp = {
  title: string;
  body: string;
};

export type HomePayload = {
  hero: PromoSlide[];
  categories: Category[];
  flashDeals: Product[];
  trending: Product[];
  topStrip: TopStripItem[];
  valueProps: ValueProp[];
};

type HomeResponse = {
  hero?: Record<string, unknown>[];
  categories?: Record<string, unknown>[];
  flashDeals?: Record<string, unknown>[];
  trending?: Record<string, unknown>[];
  topStrip?: Record<string, unknown>[];
  valueProps?: Record<string, unknown>[];
};

const fallbackCategoryByName = new Map(
  fallbackCategories.map((category) => [category.name.toLowerCase(), category])
);

const normalizeText = (value: unknown, fallback: string) => {
  if (typeof value === 'string' && value.trim().length > 0) {
    return value;
  }
  return fallback;
};

const slugify = (value: string) =>
  value
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/(^-|-$)/g, '');

const mapSlide = (slide: Record<string, unknown> | PromoSlide, index: number): PromoSlide => {
  const source = slide as Record<string, unknown>;
  const fallback = promoSlides[index % promoSlides.length];
  const title = normalizeText(source.title, fallback.title);
  const subtitle = normalizeText(source.subtitle, fallback.subtitle);
  const cta = normalizeText(source.cta ?? source.primary_label, fallback.cta);
  const image = normalizeText(source.image, fallback.image);
  const tone = normalizeText(source.tone ?? source.background_color, fallback.tone);
  const kicker = normalizeText(source.kicker, fallback.kicker ?? 'Featured');
  const href = normalizeText(source.href ?? source.primary_href, '');

  return {
    id: String(source.id ?? `slide-${index}`),
    kicker,
    title,
    subtitle,
    cta,
    href: href || undefined,
    image,
    tone,
  };
};

const mapCategory = (item: Record<string, unknown> | Category, index: number): Category => {
  const source = item as Record<string, unknown>;
  const baseFallback = fallbackCategories[index % fallbackCategories.length];
  const name = normalizeText(source.name, baseFallback.name);
  const fallback = fallbackCategoryByName.get(name.toLowerCase()) ?? baseFallback;
  const slug = normalizeText(source.slug, slugify(name));
  const image = normalizeText(source.image, fallback.image);
  const accent = normalizeText(source.accent, fallback.accent);
  const count = Number(source.count ?? source.products_count ?? fallback.count ?? 0);

  return {
    id: String(source.id ?? slug),
    name,
    slug,
    count: Number.isFinite(count) ? count : 0,
    image,
    accent,
  };
};

const mapProduct = (item: Record<string, unknown> | Product, index: number): Product => {
  const source = item as Record<string, unknown>;
  const fallback = fallbackProducts[index % fallbackProducts.length];
  const name = normalizeText(source.name, fallback.name);
  const slug = normalizeText(source.slug, fallback.slug);
  const price = Number(source.price ?? source.selling_price ?? fallback.price ?? 0);
  const compareAt = Number(source.compare_at_price ?? source.compareAt ?? fallback.compareAt ?? 0);
  const image = normalizeText(
    source.image ?? (Array.isArray(source.media) ? source.media[0] : undefined),
    fallback.image
  );
  const rating = Number(source.rating ?? source.reviews_avg_rating ?? fallback.rating ?? 0);
  const reviews = Number(source.rating_count ?? source.reviews ?? fallback.reviews ?? 0);
  const badgeSource = normalizeText(source.badge, '');
  const badge =
    badgeSource ||
    (Number.isFinite(compareAt) && compareAt > price ? 'Deal' : fallback.badge ?? undefined);

  return {
    id: String(source.id ?? slug),
    slug,
    name,
    price: Number.isFinite(price) ? price : 0,
    compareAt: Number.isFinite(compareAt) && compareAt > 0 ? compareAt : undefined,
    rating: Number.isFinite(rating) ? rating : 0,
    reviews: Number.isFinite(reviews) ? reviews : 0,
    image,
    badge,
  };
};

const mapTopStrip = (items: Record<string, unknown>[] | undefined): TopStripItem[] => {
  if (!Array.isArray(items) || items.length === 0) {
    return topStrip;
  }

  return items.map((item, index) => {
    const fallback = topStrip[index % topStrip.length];
    return {
      icon: normalizeText(item.icon, fallback.icon),
      title: normalizeText(item.title, fallback.title),
      subtitle: normalizeText(item.subtitle, fallback.subtitle),
    };
  });
};

const mapValueProps = (items: Record<string, unknown>[] | undefined): ValueProp[] => {
  if (!Array.isArray(items) || items.length === 0) {
    return valueProps;
  }

  return items.map((item, index) => {
    const fallback = valueProps[index % valueProps.length];
    return {
      title: normalizeText(item.title, fallback.title),
      body: normalizeText(item.body, fallback.body),
    };
  });
};

const fallbackHome = (): HomePayload => ({
  hero: promoSlides,
  categories: fallbackCategories.slice(0, 5),
  flashDeals: fallbackProducts.slice(0, 4),
  trending: fallbackProducts,
  topStrip,
  valueProps,
});

export const fetchHome = async (): Promise<HomePayload> => {
  try {
    const payload = await apiFetch<HomeResponse>('/home');
    const heroSource =
      Array.isArray(payload.hero) && payload.hero.length > 0 ? payload.hero : promoSlides;
    const categorySource =
      Array.isArray(payload.categories) && payload.categories.length > 0
        ? payload.categories
        : fallbackCategories;
    const flashDealsSource =
      Array.isArray(payload.flashDeals) && payload.flashDeals.length > 0
        ? payload.flashDeals
        : fallbackProducts;
    const trendingSource =
      Array.isArray(payload.trending) && payload.trending.length > 0
        ? payload.trending
        : fallbackProducts;

    return {
      hero: heroSource.map(mapSlide),
      categories: categorySource.map(mapCategory),
      flashDeals: flashDealsSource.map(mapProduct),
      trending: trendingSource.map(mapProduct),
      topStrip: mapTopStrip(payload.topStrip),
      valueProps: mapValueProps(payload.valueProps),
    };
  } catch (error) {
    return fallbackHome();
  }
};

export const fetchCategories = async (): Promise<Category[]> => {
  try {
    const payload = await apiFetch<{ categories?: Record<string, unknown>[] }>('/categories');
    if (!Array.isArray(payload.categories)) {
      return fallbackCategories;
    }
    return payload.categories.map(mapCategory);
  } catch (error) {
    return fallbackCategories;
  }
};

export const fetchProducts = async (options?: {
  query?: string;
  category?: string;
}): Promise<Product[]> => {
  const params = new URLSearchParams();

  if (options?.query) {
    params.set('q', options.query);
  }

  if (options?.category) {
    params.set('category', options.category);
  }

  const path = params.toString().length ? `/products?${params.toString()}` : '/products';

  try {
    const payload = await apiFetch<{ products?: Record<string, unknown>[] }>(path);
    if (!Array.isArray(payload.products)) {
      return fallbackProducts;
    }
    return payload.products.map(mapProduct);
  } catch (error) {
    return fallbackProducts;
  }
};

export const fetchProductBySlug = async (slug: string): Promise<Product | null> => {
  try {
    const payload = await apiFetch<{ product?: Record<string, unknown> }>(
      `/products/${encodeURIComponent(slug)}`
    );
    if (!payload.product) {
      return null;
    }
    return mapProduct(payload.product, 0);
  } catch (error) {
    return fallbackProducts.find((product) => product.slug === slug) ?? null;
  }
};

const mapOrderItem = (item: Record<string, unknown>, index: number, fallback?: OrderItem) => {
  return {
    id: String(item.id ?? fallback?.id ?? `oi-${index}`),
    name: normalizeText(item.name, fallback?.name ?? 'Item'),
    quantity: Number(item.quantity ?? fallback?.quantity ?? 1),
    price: Number(item.price ?? fallback?.price ?? 0),
    image: normalizeText(item.image, fallback?.image ?? fallbackProducts[0].image),
  };
};

const mapTrackingEvent = (
  item: Record<string, unknown>,
  index: number,
  fallback?: TrackingEvent
) => {
  return {
    id: String(item.id ?? fallback?.id ?? `te-${index}`),
    status: normalizeText(item.status, fallback?.status ?? 'Update'),
    description: normalizeText(item.description, fallback?.description ?? 'Tracking update'),
    occurredAt: normalizeText(item.occurredAt, fallback?.occurredAt ?? ''),
  };
};

const mapOrderSummary = (item: Record<string, unknown>, index: number): Order => {
  const fallback = fallbackOrders[index % fallbackOrders.length];
  const total = Number(item.total ?? item.grand_total ?? fallback.total ?? 0);

  return {
    number: normalizeText(item.number, fallback.number),
    status: normalizeText(item.status, fallback.status),
    total: Number.isFinite(total) ? total : fallback.total,
    placedAt: normalizeText(item.placedAt ?? item.placed_at, fallback.placedAt),
    items: [],
    tracking: [],
  };
};

const mapOrderDetail = (payload: Record<string, unknown>, fallback?: Order): Order => {
  const itemsSource = Array.isArray(payload.items) ? payload.items : fallback?.items ?? [];
  const trackingSource = Array.isArray(payload.tracking) ? payload.tracking : fallback?.tracking ?? [];
  const total = Number(payload.total ?? fallback?.total ?? 0);

  return {
    number: normalizeText(payload.number, fallback?.number ?? 'Order'),
    status: normalizeText(payload.status, fallback?.status ?? 'Processing'),
    total: Number.isFinite(total) ? total : fallback?.total ?? 0,
    placedAt: normalizeText(payload.placedAt ?? payload.placed_at, fallback?.placedAt ?? ''),
    items: itemsSource.map((item, index) =>
      mapOrderItem(item as Record<string, unknown>, index, fallback?.items[index])
    ),
    tracking: trackingSource.map((item, index) =>
      mapTrackingEvent(item as Record<string, unknown>, index, fallback?.tracking[index])
    ),
  };
};

export const fetchOrders = async (): Promise<Order[]> => {
  try {
    const payload = await apiFetch<{ orders?: Record<string, unknown>[] }>('/orders');
    const orders = payload.orders ?? [];
    return orders.map(mapOrderSummary);
  } catch (error) {
    return fallbackOrders;
  }
};

export const fetchOrdersStrict = async (): Promise<Order[]> => {
  const payload = await apiFetch<{ orders?: Record<string, unknown>[] }>('/orders');
  const orders = payload.orders ?? [];
  return orders.map(mapOrderSummary);
};

export const fetchOrderByNumber = async (number: string): Promise<Order | null> => {
  try {
    const payload = await apiFetch<{ order?: Record<string, unknown> }>(
      `/orders/${encodeURIComponent(number)}`
    );
    if (!payload.order) {
      return null;
    }
    const fallback = fallbackOrders.find((order) => order.number === number);
    return mapOrderDetail(payload.order, fallback);
  } catch (error) {
    return fallbackOrders.find((order) => order.number === number) ?? null;
  }
};

export const trackOrder = async (
  number: string,
  email: string
): Promise<TrackingEvent[] | null> => {
  const params = new URLSearchParams({ number, email });
  const fallback = fallbackOrders.find((order) => order.number === number);

  try {
    const payload = await apiFetch<{ tracking?: Record<string, unknown>[] }>(
      `/orders/track?${params.toString()}`
    );
    if (!payload.tracking) {
      return null;
    }
    return payload.tracking.map((item, index) =>
      mapTrackingEvent(item, index, fallback?.tracking[index])
    );
  } catch (error) {
    return fallback?.tracking ?? null;
  }
};
