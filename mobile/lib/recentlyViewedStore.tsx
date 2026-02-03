import React, { createContext, useCallback, useContext, useMemo, useReducer } from 'react';

type RecentlyViewedState = {
  slugs: string[];
};

type RecentlyViewedAction =
  | { type: 'track'; slug: string }
  | { type: 'clear' };

const MAX_ITEMS = 12;

const RecentlyViewedContext = createContext<{
  slugs: string[];
  track: (slug: string) => void;
  clear: () => void;
} | null>(null);

const reducer = (state: RecentlyViewedState, action: RecentlyViewedAction): RecentlyViewedState => {
  switch (action.type) {
    case 'track': {
      const slug = action.slug.trim();
      if (!slug) return state;
      const without = state.slugs.filter((it) => it !== slug);
      return { slugs: [slug, ...without].slice(0, MAX_ITEMS) };
    }
    case 'clear':
      return { slugs: [] };
    default:
      return state;
  }
};

export const RecentlyViewedProvider = ({ children }: { children: React.ReactNode }) => {
  const [state, dispatch] = useReducer(reducer, { slugs: [] });
  const track = useCallback((slug: string) => dispatch({ type: 'track', slug }), []);
  const clear = useCallback(() => dispatch({ type: 'clear' }), []);

  const value = useMemo(() => {
    return {
      slugs: state.slugs,
      track,
      clear,
    };
  }, [state.slugs, track, clear]);

  return <RecentlyViewedContext.Provider value={value}>{children}</RecentlyViewedContext.Provider>;
};

export const useRecentlyViewed = () => {
  const ctx = useContext(RecentlyViewedContext);
  if (!ctx) throw new Error('useRecentlyViewed must be used within RecentlyViewedProvider');
  return ctx;
};
