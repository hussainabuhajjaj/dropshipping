import React, { createContext, useContext, useEffect, useMemo, useReducer } from 'react';
import type { Product } from '@/src/types/storefront';
import type { CartPayload, CartSummary } from '@/src/types/cart';
import { addCartItem, applyCoupon, fetchCart, removeCartItem, removeCoupon, updateCartItem } from '@/src/api/cart';
import { useAuth } from './authStore';

export type CartItem = {
  id: string;
  productId: string;
  variantId?: string | null;
  slug?: string | null;
  name: string;
  price: number;
  compareAt?: number | null;
  currency?: string | null;
  image: string | null;
  quantity: number;
};

type CartState = {
  items: CartItem[];
  selectedIds: string[];
  summary: CartSummary;
  loading: boolean;
  error: string | null;
};

type CartAction =
  | { type: 'set'; payload: CartPayload }
  | { type: 'toggle'; productId: string }
  | { type: 'setLoading'; value: boolean }
  | { type: 'setError'; error: string | null }
  | { type: 'clear' };

const CartContext = createContext<{
  items: CartItem[];
  selectedIds: string[];
  subtotal: number;
  summary: CartSummary;
  loading: boolean;
  error: string | null;
  selectedCount: number;
  refreshCart: () => Promise<boolean>;
  addItem: (product: Product, quantity?: number) => Promise<boolean>;
  removeItem: (productId: string) => Promise<boolean>;
  updateQty: (productId: string, quantity: number) => Promise<boolean>;
  toggleSelection: (productId: string) => void;
  removeSelected: () => Promise<boolean>;
  applyCoupon: (code: string) => Promise<boolean>;
  removeCoupon: () => Promise<boolean>;
  clear: () => void;
} | null>(null);

const emptySummary: CartSummary = {
  currency: 'USD',
  subtotal: 0,
  shipping: 0,
  discount: 0,
  tax: 0,
  total: 0,
  coupon: null,
  discountLabel: null,
  minimumRequirement: null,
};

const mapPayloadToItems = (payload: CartPayload): CartItem[] => {
  return payload.lines.map((line) => ({
    id: line.id,
    productId: line.productId,
    variantId: line.variantId ?? null,
    slug: line.slug ?? null,
    name: line.name,
    price: line.price,
    compareAt: line.compareAt ?? null,
    currency: line.currency ?? null,
    image: line.image ?? null,
    quantity: line.quantity,
  }));
};

const reconcileSelection = (
  previousItems: CartItem[],
  previousSelected: string[],
  nextItems: CartItem[]
) => {
  const nextProductIds = nextItems.map((item) => item.productId);
  if (nextProductIds.length === 0) return [];

  const prevIds = previousItems.map((item) => item.productId);
  const hadAllSelected = prevIds.length > 0 && previousSelected.length === prevIds.length;
  const retained = previousSelected.filter((id) => nextProductIds.includes(id));
  if (hadAllSelected) return nextProductIds;
  if (retained.length > 0) return retained;
  return nextProductIds;
};

const reducer = (state: CartState, action: CartAction): CartState => {
  switch (action.type) {
    case 'set': {
      const items = mapPayloadToItems(action.payload);
      const selectedIds = reconcileSelection(state.items, state.selectedIds, items);
      return {
        ...state,
        items,
        selectedIds,
        summary: action.payload.summary,
        loading: false,
        error: null,
      };
    }
    case 'toggle':
      return {
        ...state,
        selectedIds: state.selectedIds.includes(action.productId)
          ? state.selectedIds.filter((id) => id !== action.productId)
          : [...state.selectedIds, action.productId],
      };
    case 'setLoading':
      return { ...state, loading: action.value };
    case 'setError':
      return { ...state, error: action.error };
    case 'clear':
      return { items: [], selectedIds: [], summary: emptySummary, loading: false, error: null };
    default:
      return state;
  }
};

export const CartProvider = ({ children }: { children: React.ReactNode }) => {
  const { status } = useAuth();
  const [state, dispatch] = useReducer(reducer, {
    items: [],
    selectedIds: [],
    summary: emptySummary,
    loading: false,
    error: null,
  });

  useEffect(() => {
    if (status !== 'authed') {
      dispatch({ type: 'clear' });
      return;
    }
    dispatch({ type: 'setLoading', value: true });
    fetchCart()
      .then((payload) => dispatch({ type: 'set', payload }))
      .catch((err: any) => {
        dispatch({ type: 'setError', error: err?.message ?? 'Unable to load cart.' });
        dispatch({ type: 'setLoading', value: false });
      });
  }, [status]);

  const subtotal = useMemo(() => {
    return state.summary.subtotal;
  }, [state.summary.subtotal]);
  const selectedCount = useMemo(() => {
    return state.selectedIds.length;
  }, [state.selectedIds]);

  const value = useMemo(() => {
    const refreshCart = async () => {
      if (status !== 'authed') {
        dispatch({ type: 'clear' });
        return false;
      }
      dispatch({ type: 'setLoading', value: true });
      try {
        const payload = await fetchCart();
        dispatch({ type: 'set', payload });
        return true;
      } catch (err: any) {
        dispatch({ type: 'setError', error: err?.message ?? 'Unable to load cart.' });
        dispatch({ type: 'setLoading', value: false });
        return false;
      }
    };

    const addItem = async (product: Product, quantity = 1) => {
      if (status !== 'authed') {
        dispatch({ type: 'setError', error: 'Please sign in to add items to your cart.' });
        return false;
      }
      dispatch({ type: 'setLoading', value: true });
      try {
        const variantId = product.variants?.[0]?.id ?? null;
        const payload = await addCartItem({
          productId: product.id,
          variantId,
          quantity,
        });
        dispatch({ type: 'set', payload });
        return true;
      } catch (err: any) {
        dispatch({ type: 'setError', error: err?.message ?? 'Unable to add item.' });
        dispatch({ type: 'setLoading', value: false });
        return false;
      }
    };

    const updateQty = async (productId: string, quantity: number) => {
      const target = state.items.find((item) => item.productId === productId);
      if (!target) return false;
      if (status !== 'authed') {
        dispatch({ type: 'setError', error: 'Please sign in to update your cart.' });
        return false;
      }
      dispatch({ type: 'setLoading', value: true });
      try {
        const payload = await updateCartItem(target.id, Math.max(1, quantity));
        dispatch({ type: 'set', payload });
        return true;
      } catch (err: any) {
        dispatch({ type: 'setError', error: err?.message ?? 'Unable to update quantity.' });
        dispatch({ type: 'setLoading', value: false });
        return false;
      }
    };

    const removeItem = async (productId: string) => {
      const target = state.items.find((item) => item.productId === productId);
      if (!target) return false;
      if (status !== 'authed') {
        dispatch({ type: 'setError', error: 'Please sign in to update your cart.' });
        return false;
      }
      dispatch({ type: 'setLoading', value: true });
      try {
        const payload = await removeCartItem(target.id);
        dispatch({ type: 'set', payload });
        return true;
      } catch (err: any) {
        dispatch({ type: 'setError', error: err?.message ?? 'Unable to remove item.' });
        dispatch({ type: 'setLoading', value: false });
        return false;
      }
    };

    const removeSelected = async () => {
      if (state.selectedIds.length === 0) return true;
      if (status !== 'authed') {
        dispatch({ type: 'setError', error: 'Please sign in to update your cart.' });
        return false;
      }
      dispatch({ type: 'setLoading', value: true });
      try {
        const selected = state.items.filter((item) => state.selectedIds.includes(item.productId));
        for (const item of selected) {
          await removeCartItem(item.id);
        }
        const payload = await fetchCart();
        dispatch({ type: 'set', payload });
        return true;
      } catch (err: any) {
        dispatch({ type: 'setError', error: err?.message ?? 'Unable to update cart.' });
        dispatch({ type: 'setLoading', value: false });
        return false;
      }
    };

    const applyCouponCode = async (code: string) => {
      if (status !== 'authed') {
        dispatch({ type: 'setError', error: 'Please sign in to apply coupons.' });
        return false;
      }
      dispatch({ type: 'setLoading', value: true });
      try {
        const payload = await applyCoupon(code);
        dispatch({ type: 'set', payload });
        return true;
      } catch (err: any) {
        dispatch({ type: 'setError', error: err?.message ?? 'Unable to apply coupon.' });
        dispatch({ type: 'setLoading', value: false });
        return false;
      }
    };

    const removeCouponCode = async () => {
      if (status !== 'authed') {
        dispatch({ type: 'setError', error: 'Please sign in to update your cart.' });
        return false;
      }
      dispatch({ type: 'setLoading', value: true });
      try {
        const payload = await removeCoupon();
        dispatch({ type: 'set', payload });
        return true;
      } catch (err: any) {
        dispatch({ type: 'setError', error: err?.message ?? 'Unable to remove coupon.' });
        dispatch({ type: 'setLoading', value: false });
        return false;
      }
    };

    return {
      items: state.items,
      selectedIds: state.selectedIds,
      subtotal,
      summary: state.summary,
      loading: state.loading,
      error: state.error,
      selectedCount,
      refreshCart,
      addItem,
      removeItem,
      updateQty,
      toggleSelection: (productId: string) => dispatch({ type: 'toggle', productId }),
      removeSelected,
      applyCoupon: applyCouponCode,
      removeCoupon: removeCouponCode,
      clear: () => dispatch({ type: 'clear' }),
    };
  }, [state, subtotal, selectedCount, status]);

  return <CartContext.Provider value={value}>{children}</CartContext.Provider>;
};

export const useCart = () => {
  const context = useContext(CartContext);
  if (!context) {
    throw new Error('useCart must be used within CartProvider');
  }
  return context;
};
