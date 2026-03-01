export type StoryLayoutConfig = {
  showBadge: boolean;
  showCountdown?: boolean;
  showCode?: boolean;
  showPrice?: boolean;
  layout: 'overlay' | 'split' | 'fullscreen' | 'product' | 'card';
  badgePosition?: 'top-left' | 'top-right' | 'bottom' | 'center';
};

export const STORY_LAYOUTS: Record<string, StoryLayoutConfig> = {
  offer: {
    showBadge: true,
    showCountdown: true,
    showCode: true,
    layout: 'overlay',
    badgePosition: 'top-right',
  },
  promotion: {
    showBadge: true,
    showCountdown: false,
    layout: 'split',
    badgePosition: 'top-left',
  },
  seasonal: {
    showBadge: true,
    layout: 'fullscreen',
    badgePosition: 'bottom',
  },
  product: {
    showBadge: false,
    showPrice: true,
    layout: 'product',
  },
  announcement: {
    showBadge: true,
    layout: 'card',
    badgePosition: 'top-left',
  },
  default: {
    showBadge: true,
    layout: 'overlay',
    badgePosition: 'top-right',
  },
};

export const getStoryLayout = (storyType: string | null): StoryLayoutConfig => {
  if (!storyType || !STORY_LAYOUTS[storyType]) {
    return STORY_LAYOUTS.default;
  }
  return STORY_LAYOUTS[storyType];
};
