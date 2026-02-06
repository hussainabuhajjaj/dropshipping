import { apiFetch } from './http';
import type { ApiError } from './http';
import { mobileApiBaseUrl } from './config';

export type OnboardingSlide = {
  key: string;
  background: 'hello' | 'ready';
  title: string;
  body: string;
  image?: string | null;
  imageColors: [string, string];
  actionHref?: string | null;
};

export type OnboardingSettings = {
  configured: boolean;
  enabled: boolean;
  locale: string;
  updatedAt?: string | null;
  slides: OnboardingSlide[];
};

export type ApiEnvelope<T> = {
  success: boolean;
  data?: T;
  message?: string | null;
  errors?: Record<string, string[]> | null;
  meta?: Record<string, unknown> | null;
};

type ApiOnboarding = Record<string, unknown>;
type ApiSlide = Record<string, unknown>;

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
  if (typeof value === 'string') return value;
  if (typeof value === 'number' && Number.isFinite(value)) return String(value);
  return fallback;
};

const isBackground = (value: unknown): value is OnboardingSlide['background'] => value === 'hello' || value === 'ready';

const mapSlide = (payload: ApiSlide, index: number): OnboardingSlide => {
  const imageColors = Array.isArray(payload.imageColors) ? payload.imageColors : [];
  const color1 = toStringValue(imageColors[0], '#ffcad9');
  const color2 = toStringValue(imageColors[1], '#f39db0');

  return {
    key: toStringValue(payload.key, `slide-${index}`) || `slide-${index}`,
    background: isBackground(payload.background) ? payload.background : 'hello',
    title: toStringValue(payload.title, ''),
    body: toStringValue(payload.body, ''),
    image: typeof payload.image === 'string' ? payload.image : null,
    imageColors: [color1, color2],
    actionHref: typeof payload.actionHref === 'string' ? payload.actionHref : null,
  };
};

const mapSettings = (payload: ApiOnboarding): OnboardingSettings => {
  const slides = Array.isArray(payload.slides)
    ? payload.slides
        .map((item, index) => (item && typeof item === 'object' && !Array.isArray(item) ? mapSlide(item as ApiSlide, index) : null))
        .filter((item): item is OnboardingSlide => Boolean(item))
    : [];

  return {
    configured: Boolean(payload.configured),
    enabled: Boolean(payload.enabled),
    locale: toStringValue(payload.locale, 'en'),
    updatedAt: typeof payload.updatedAt === 'string' ? payload.updatedAt : null,
    slides,
  };
};

export const fetchOnboardingSettings = async (): Promise<OnboardingSettings> => {
  const payload = await apiFetch<ApiEnvelope<ApiOnboarding>>(`${mobileApiBaseUrl}/onboarding`);
  return mapSettings(unwrap(payload));
};
