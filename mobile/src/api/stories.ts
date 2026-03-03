import { apiFetch, mobileApiBaseUrl } from './config';
import type { ApiEnvelope } from './types';

export type StoryContent = Record<string, unknown>;

export type Story = {
  id: number;
  title: string;
  description: string | null;
  image: string | null;
  badgeText: string | null;
  badgeColor: string | null;
  backgroundColor: string | null;
  textColor: string | null;
  ctaText: string | null;
  ctaUrl: string | null;
  targetType: string | null;
  productId: number | null;
  categoryId: number | null;
  externalUrl: string | null;
  storyType: string | null;
  storyContent: StoryContent;
};

const toStringValue = (value: unknown, fallback = ''): string => {
  return typeof value === 'string' ? value : fallback;
};

const toNumberValue = (value: unknown, fallback: number | null = null): number | null => {
  const num = Number(value);
  return Number.isFinite(num) ? num : fallback;
};

const mapStory = (payload: Record<string, unknown>): Story => {
  return {
    id: toNumberValue(payload.id, 0) ?? 0,
    title: toStringValue(payload.title, 'Story'),
    description: typeof payload.description === 'string' ? payload.description : null,
    image: typeof payload.image === 'string' ? payload.image : null,
    badgeText: typeof payload.badge_text === 'string' ? payload.badge_text : null,
    badgeColor: typeof payload.badge_color === 'string' ? payload.badge_color : null,
    backgroundColor: typeof payload.background_color === 'string' ? payload.background_color : null,
    textColor: typeof payload.text_color === 'string' ? payload.text_color : null,
    ctaText: typeof payload.cta_text === 'string' ? payload.cta_text : null,
    ctaUrl: typeof payload.cta_url === 'string' ? payload.cta_url : null,
    targetType: typeof payload.target_type === 'string' ? payload.target_type : null,
    productId: toNumberValue(payload.product_id),
    categoryId: toNumberValue(payload.category_id),
    externalUrl: typeof payload.external_url === 'string' ? payload.external_url : null,
    storyType: typeof payload.story_type === 'string' ? payload.story_type : null,
    storyContent: typeof payload.story_content === 'object' && payload.story_content !== null 
      ? (payload.story_content as StoryContent) 
      : {},
  };
};

const unwrap = <T>(payload: ApiEnvelope<T>): T => {
  if (payload.data !== undefined) {
    return payload.data;
  }
  throw { status: 422, message: payload?.message ?? 'Request failed', errors: payload?.errors };
};

export const fetchStories = async (): Promise<Story[]> => {
  const payload = await apiFetch<ApiEnvelope<Record<string, unknown>[]>>(
    `${mobileApiBaseUrl}/stories`
  );
  const data = unwrap(payload);
  return Array.isArray(data) ? data.map(mapStory) : [];
};

export const fetchStory = async (id: number): Promise<Story> => {
  const payload = await apiFetch<ApiEnvelope<Record<string, unknown>>>(
    `${mobileApiBaseUrl}/stories/${id}`
  );
  return mapStory(unwrap(payload));
};
