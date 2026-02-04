export type Category = {
  id: string;
  name: string;
  slug?: string;
  count: number;
  image: string;
  accent: string;
};

export type Product = {
  id: string;
  slug: string;
  name: string;
  price: number;
  compareAt?: number;
  rating: number;
  reviews: number;
  image: string;
  badge?: string;
};

export type PromoSlide = {
  id: string;
  kicker?: string;
  title: string;
  subtitle: string;
  cta: string;
  href?: string;
  image: string;
  tone: string;
};

export const promoSlides: PromoSlide[] = [
  {
    id: 'slide-1',
    title: 'Mega Flash Deals',
    subtitle: 'Daily drops with bold price cuts.',
    cta: 'Shop deals',
    image: 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=900&q=80',
    tone: '#ffe7d6',
  },
  {
    id: 'slide-2',
    title: 'Home refresh picks',
    subtitle: 'Bright upgrades that ship fast.',
    cta: 'Explore home',
    image: 'https://images.unsplash.com/photo-1505691938895-1758d7feb511?auto=format&fit=crop&w=900&q=80',
    tone: '#fff6c8',
  },
  {
    id: 'slide-3',
    title: 'Beauty + Care bundles',
    subtitle: 'Top rated essentials under one cart.',
    cta: 'Shop care',
    image: 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?auto=format&fit=crop&w=900&q=80',
    tone: '#f8d8ff',
  },
];

export const categories: Category[] = [
  {
    id: 'cat-home',
    name: 'Home & Kitchen',
    count: 420,
    image: 'https://images.unsplash.com/photo-1501045661006-fcebe0257c3f?auto=format&fit=crop&w=600&q=80',
    accent: '#ffe9cc',
  },
  {
    id: 'cat-tech',
    name: 'Tech & Gadgets',
    count: 310,
    image: 'https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=600&q=80',
    accent: '#f0ecd6',
  },
  {
    id: 'cat-fashion',
    name: 'Fashion',
    count: 520,
    image: 'https://images.unsplash.com/photo-1483985988355-763728e1935b?auto=format&fit=crop&w=600&q=80',
    accent: '#ffe0f4',
  },
  {
    id: 'cat-beauty',
    name: 'Beauty & Care',
    count: 260,
    image: 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?auto=format&fit=crop&w=600&q=80',
    accent: '#f0ffe8',
  },
  {
    id: 'cat-fitness',
    name: 'Fitness',
    count: 180,
    image: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=600&q=80',
    accent: '#e9fff5',
  },
  {
    id: 'cat-baby',
    name: 'Baby & Kids',
    count: 150,
    image: 'https://images.unsplash.com/photo-1516627145497-ae6968895b74?auto=format&fit=crop&w=600&q=80',
    accent: '#fef4dd',
  },
];

export const products: Product[] = [
  {
    id: 'prod-1',
    slug: 'smart-led-strip',
    name: 'Smart LED Strip Lights',
    price: 19.9,
    compareAt: 39.9,
    rating: 4.8,
    reviews: 1240,
    image: 'https://images.unsplash.com/photo-1545239351-1141bd82e8a6?auto=format&fit=crop&w=700&q=80',
    badge: 'Hot',
  },
  {
    id: 'prod-2',
    slug: 'wireless-earbuds',
    name: 'Wireless Noise Buds',
    price: 28.5,
    compareAt: 59.0,
    rating: 4.6,
    reviews: 820,
    image: 'https://images.unsplash.com/photo-1518443895471-3e5a0c0f60e6?auto=format&fit=crop&w=700&q=80',
    badge: '50% off',
  },
  {
    id: 'prod-3',
    slug: 'mini-blender',
    name: 'Mini Smoothie Blender',
    price: 24.0,
    compareAt: 44.0,
    rating: 4.4,
    reviews: 560,
    image: 'https://images.unsplash.com/photo-1561049938-6b8d79d1b724?auto=format&fit=crop&w=700&q=80',
    badge: 'Deal',
  },
  {
    id: 'prod-4',
    slug: 'travel-backpack',
    name: 'City Travel Backpack',
    price: 32.0,
    compareAt: 65.0,
    rating: 4.7,
    reviews: 910,
    image: 'https://images.unsplash.com/photo-1503342217505-b0a15ec3261c?auto=format&fit=crop&w=700&q=80',
    badge: 'Best',
  },
  {
    id: 'prod-5',
    slug: 'hair-styler',
    name: '2-in-1 Hair Styler',
    price: 21.5,
    compareAt: 45.0,
    rating: 4.5,
    reviews: 430,
    image: 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?auto=format&fit=crop&w=700&q=80',
  },
  {
    id: 'prod-6',
    slug: 'desk-setup-kit',
    name: 'Desk Setup Starter Kit',
    price: 38.0,
    compareAt: 75.0,
    rating: 4.3,
    reviews: 210,
    image: 'https://images.unsplash.com/photo-1504384308090-c894fdcc538d?auto=format&fit=crop&w=700&q=80',
  },
];

export const valueProps = [
  {
    title: 'Fast dispatch',
    body: 'Suppliers confirm within 24-48 hours.',
  },
  {
    title: 'Customs clarity',
    body: 'Duties shown before checkout.',
  },
  {
    title: 'Live tracking',
    body: 'Delivery updates inside the app.',
  },
];

export const topStrip = [
  { icon: 'zap', title: 'Flash deals daily', subtitle: 'New drops every 24h.' },
  { icon: 'check-circle', title: 'Verified stock', subtitle: 'We check suppliers for you.' },
  { icon: 'truck', title: 'Fast delivery', subtitle: 'Clear ETAs at checkout.' },
];
