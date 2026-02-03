import React, { createContext, useContext, useEffect, useMemo, useReducer } from 'react';
import type { Product } from '@/src/types/storefront';
import { useAuth } from './authStore';
import { addWishlistItem, fetchWishlist, removeWishlistItem } from '@/src/api/wishlist';

export type WishlistState = {
  items: Product[];
  loading: boolean;
  error: string | null;
};

type WishlistAction =
  | { type: 'setItems'; items: Product[] }
  | { type: 'add'; product: Product }
  | { type: 'upsert'; product: Product }
  | { type: 'remove'; productId: string }
  | { type: 'setLoading'; value: boolean }
  | { type: 'setError'; error: string | null }
  | { type: 'clear' };

const WishlistContext = createContext<{
  items: Product[];
  ids: string[];
  loading: boolean;
  error: string | null;
  refresh: () => Promise<boolean>;
  toggle: (product: Product) => Promise<{ ok: boolean; message?: string }>;
  remove: (productId: string) => Promise<{ ok: boolean; message?: string }>;
  add: (product: Product) => Promise<{ ok: boolean; message?: string }>;
  clear: () => void;
  contains: (productId: string) => boolean;
} | null>(null);

const reducer = (state: WishlistState, action: WishlistAction): WishlistState => {
  switch (action.type) {
    case 'setItems':
      return { ...state, items: action.items };
    case 'add':
      if (state.items.some((item) => item.id === action.product.id)) {
        return state;
      }
      return { ...state, items: [...state.items, action.product] };
    case 'upsert': {
      const exists = state.items.some((item) => item.id === action.product.id);
      return {
        ...state,
        items: exists
          ? state.items.map((item) => (item.id === action.product.id ? action.product : item))
          : [...state.items, action.product],
      };
    }
    case 'remove':
      return { ...state, items: state.items.filter((item) => item.id !== action.productId) };
    case 'setLoading':
      return { ...state, loading: action.value };
    case 'setError':
      return { ...state, error: action.error };
    case 'clear':
      return { items: [], loading: false, error: null };
    default:
      return state;
  }
};

export const WishlistProvider = ({ children }: { children: React.ReactNode }) => {
  const { status } = useAuth();
  const [state, dispatch] = useReducer(reducer, { items: [], loading: false, error: null });
  const ids = useMemo(() => state.items.map((item) => item.id), [state.items]);

  useEffect(() => {
    if (status !== 'authed') {
      dispatch({ type: 'clear' });
      return;
    }
    dispatch({ type: 'setLoading', value: true });
    fetchWishlist()
      .then((items) => {
        const products = items
          .map((item) => item.product)
          .filter((item): item is Product => Boolean(item));
        dispatch({ type: 'setItems', items: products });
        dispatch({ type: 'setError', error: null });
      })
      .catch((err: any) => {
        dispatch({ type: 'setError', error: err?.message ?? 'Unable to load wishlist.' });
      })
      .finally(() => {
        dispatch({ type: 'setLoading', value: false });
      });
  }, [status]);

  const value = useMemo(() => {
    const refresh = async () => {
      if (status !== 'authed') {
        dispatch({ type: 'clear' });
        return false;
      }
      dispatch({ type: 'setLoading', value: true });
      try {
        const items = await fetchWishlist();
        const products = items
          .map((item) => item.product)
          .filter((item): item is Product => Boolean(item));
        dispatch({ type: 'setItems', items: products });
        dispatch({ type: 'setError', error: null });
        return true;
      } catch (err: any) {
        dispatch({ type: 'setError', error: err?.message ?? 'Unable to load wishlist.' });
        return false;
      } finally {
        dispatch({ type: 'setLoading', value: false });
      }
    };

    const add = async (product: Product) => {
      if (status !== 'authed') {
        const message = 'Please sign in to save items to your wishlist.';
        dispatch({ type: 'setError', error: message });
        return { ok: false, message };
      }
      dispatch({ type: 'add', product });
      dispatch({ type: 'setLoading', value: true });
      try {
        const item = await addWishlistItem(product.id);
        if (item.product) {
          dispatch({ type: 'upsert', product: item.product });
        }
        dispatch({ type: 'setError', error: null });
        return { ok: true };
      } catch (err: any) {
        dispatch({ type: 'remove', productId: product.id });
        const message = err?.message ?? 'Unable to update wishlist.';
        dispatch({ type: 'setError', error: message });
        return { ok: false, message };
      } finally {
        dispatch({ type: 'setLoading', value: false });
      }
    };

    const remove = async (productId: string) => {
      if (status !== 'authed') {
        const message = 'Please sign in to update your wishlist.';
        dispatch({ type: 'setError', error: message });
        return { ok: false, message };
      }
      const removed = state.items.find((item) => item.id === productId) ?? null;
      dispatch({ type: 'remove', productId });
      dispatch({ type: 'setLoading', value: true });
      try {
        await removeWishlistItem(productId);
        dispatch({ type: 'setError', error: null });
        return { ok: true };
      } catch (err: any) {
        if (removed) {
          dispatch({ type: 'add', product: removed });
        }
        const message = err?.message ?? 'Unable to update wishlist.';
        dispatch({ type: 'setError', error: message });
        return { ok: false, message };
      } finally {
        dispatch({ type: 'setLoading', value: false });
      }
    };

    const toggle = async (product: Product) => {
      if (ids.includes(product.id)) {
        return remove(product.id);
      }
      return add(product);
    };

    return {
      items: state.items,
      ids,
      loading: state.loading,
      error: state.error,
      refresh,
      toggle,
      remove,
      add,
      clear: () => dispatch({ type: 'clear' }),
      contains: (productId: string) => ids.includes(productId),
    };
  }, [ids, state.error, state.items, state.loading, status]);

  return <WishlistContext.Provider value={value}>{children}</WishlistContext.Provider>;
};

export const useWishlist = () => {
  const context = useContext(WishlistContext);
  if (!context) {
    throw new Error('useWishlist must be used within WishlistProvider');
  }
  return context;
};
