import React, { createContext, useContext, useEffect, useMemo, useReducer, useRef } from 'react';
import type { Order } from '@/src/types/orders';
import { fetchOrders } from '@/src/api/orders';
import { useToast } from '@/src/overlays/ToastProvider';
import { CartItem } from './cartStore';
import { useAuth } from './authStore';

type OrdersState = {
  orders: Order[];
  loading: boolean;
  error: string | null;
};

type OrdersAction =
  | { type: 'add'; order: Order }
  | { type: 'syncFromApi'; orders: Order[] }
  | { type: 'clear' }
  | { type: 'update'; number: string; patch: Partial<Order> }
  | { type: 'setLoading'; value: boolean }
  | { type: 'setError'; error: string | null };

const OrdersContext = createContext<{
  orders: Order[];
  loading: boolean;
  error: string | null;
  refreshOrders: () => Promise<void>;
  addOrderFromCart: (input: {
    items: CartItem[];
    shipping: number;
    discount?: number;
    number?: string;
  }) => Order;
  getOrderByNumber: (number: string) => Order | undefined;
  updateOrder: (number: string, patch: Partial<Order>) => void;
  clearLocal: () => void;
} | null>(null);

const formatDate = (date: Date) => date.toISOString().slice(0, 10);

const createOrderNumber = (existing: Set<string>) => {
  for (let attempt = 0; attempt < 20; attempt += 1) {
    const suffix = Math.floor(1000 + Math.random() * 9000);
    const number = `DS-${suffix}`;
    if (!existing.has(number)) return number;
  }
  // last resort
  return `DS-${Date.now().toString().slice(-4)}`;
};

const mergeOrder = (current: Order | undefined, next: Order): Order => {
  if (!current) return next;
  const items = next.items.length > 0 ? next.items : current.items;
  const tracking = next.tracking.length > 0 ? next.tracking : current.tracking;
  return { ...current, ...next, items, tracking };
};

const reducer = (state: OrdersState, action: OrdersAction): OrdersState => {
  switch (action.type) {
    case 'add':
      return { ...state, orders: [action.order, ...state.orders] };
    case 'syncFromApi': {
      const apiOrders = action.orders;
      if (!Array.isArray(apiOrders) || apiOrders.length === 0) {
        return { ...state, loading: false, error: null };
      }
      const existingByNumber = new Map(state.orders.map((order) => [order.number, order]));
      const merged = apiOrders.map((order) => mergeOrder(existingByNumber.get(order.number), order));
      const localOnly = state.orders.filter((order) => !apiOrders.some((it) => it.number === order.number));
      return { orders: [...localOnly, ...merged], loading: false, error: null };
    }
    case 'clear':
      return { orders: [], loading: false, error: null };
    case 'update':
      return {
        ...state,
        orders: state.orders.map((order) =>
          order.number === action.number ? mergeOrder(order, { ...order, ...action.patch }) : order
        ),
      };
    case 'setLoading':
      return { ...state, loading: action.value };
    case 'setError':
      return { ...state, error: action.error };
    default:
      return state;
  }
};

export const OrdersProvider = ({ children }: { children: React.ReactNode }) => {
  const { status } = useAuth();
  const { show } = useToast();
  const [state, dispatch] = useReducer(reducer, { orders: [], loading: false, error: null });
  const lastError = useRef<string | null>(null);

  useEffect(() => {
    let active = true;
    if (status !== 'authed') {
      dispatch({ type: 'clear' });
      return () => {
        active = false;
      };
    }

    dispatch({ type: 'setLoading', value: true });
    fetchOrders()
      .then(({ items }) => {
        if (!active) return;
        dispatch({ type: 'syncFromApi', orders: items });
      })
      .catch((err: any) => {
        if (!active) return;
        dispatch({ type: 'setError', error: err?.message ?? 'Unable to load orders.' });
        dispatch({ type: 'setLoading', value: false });
      });

    return () => {
      active = false;
    };
  }, [status]);

  useEffect(() => {
    if (!state.error) {
      lastError.current = null;
      return;
    }
    if (state.error !== lastError.current) {
      show({ type: 'error', message: state.error });
      lastError.current = state.error;
    }
  }, [state.error, show]);

  const value = useMemo(() => {
    const getOrderByNumber = (number: string) =>
      state.orders.find((order) => order.number === number);

    const updateOrder = (number: string, patch: Partial<Order>) =>
      dispatch({ type: 'update', number, patch });

    const refreshOrders = async () => {
      if (status !== 'authed') {
        dispatch({ type: 'clear' });
        return;
      }
      dispatch({ type: 'setLoading', value: true });
      try {
        const { items } = await fetchOrders();
        dispatch({ type: 'syncFromApi', orders: items });
      } catch (err: any) {
        dispatch({ type: 'setError', error: err?.message ?? 'Unable to load orders.' });
        dispatch({ type: 'setLoading', value: false });
      }
    };

    const clearLocal = () => dispatch({ type: 'clear' });

    const addOrderFromCart = (input: {
      items: CartItem[];
      shipping: number;
      discount?: number;
      number?: string;
    }) => {
      const existingNumbers = new Set(state.orders.map((order) => order.number));
      const number = input.number && !existingNumbers.has(input.number) ? input.number : createOrderNumber(existingNumbers);
      const subtotal = input.items.reduce((sum, item) => sum + item.price * item.quantity, 0);
      const discount = input.discount ?? 0;
      const total = Math.max(0, subtotal + input.shipping - discount);
      const placedAt = formatDate(new Date());

      const order: Order = {
        number,
        status: 'Processing',
        total,
        placedAt,
        items: input.items.map((item, index) => ({
          id: item.id || `oi-${index}`,
          name: item.name,
          quantity: item.quantity,
          price: item.price,
          image: item.image ?? null,
        })),
        tracking: [],
      };

      dispatch({ type: 'add', order });
      return order;
    };

    return {
      orders: state.orders,
      loading: state.loading,
      error: state.error,
      refreshOrders,
      addOrderFromCart,
      getOrderByNumber,
      updateOrder,
      clearLocal,
    };
  }, [state.orders, state.loading, state.error, status]);

  return <OrdersContext.Provider value={value}>{children}</OrdersContext.Provider>;
};

export const useOrders = () => {
  const ctx = useContext(OrdersContext);
  if (!ctx) throw new Error('useOrders must be used within OrdersProvider');
  return ctx;
};
