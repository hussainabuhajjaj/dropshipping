import React, { createContext, useContext, useEffect, useMemo, useReducer } from 'react';
import { useAuth } from './authStore';
import type { Address } from '@/src/types/address';
import { createAddress, deleteAddress, fetchAddresses, updateAddress } from '@/src/api/addresses';

type AddressesState = {
  items: Address[];
  loading: boolean;
  error: string | null;
};

type AddressesAction =
  | { type: 'setItems'; items: Address[] }
  | { type: 'setLoading'; value: boolean }
  | { type: 'setError'; error: string | null }
  | { type: 'add'; address: Address }
  | { type: 'update'; address: Address }
  | { type: 'remove'; id: string }
  | { type: 'clear' };

const AddressesContext = createContext<{
  items: Address[];
  loading: boolean;
  error: string | null;
  refresh: () => Promise<boolean>;
  create: (input: {
    name?: string | null;
    phone?: string | null;
    line1: string;
    line2?: string | null;
    city?: string | null;
    state?: string | null;
    postal_code?: string | null;
    country?: string | null;
    type?: string | null;
    is_default?: boolean;
  }) => Promise<{ ok: boolean; address?: Address; message?: string }>;
  update: (id: string, input: Partial<{
    name?: string | null;
    phone?: string | null;
    line1: string;
    line2?: string | null;
    city?: string | null;
    state?: string | null;
    postal_code?: string | null;
    country?: string | null;
    type?: string | null;
    is_default?: boolean;
  }>) => Promise<{ ok: boolean; address?: Address; message?: string }>;
  remove: (id: string) => Promise<{ ok: boolean; message?: string }>;
  setDefault: (id: string) => Promise<{ ok: boolean; address?: Address; message?: string }>;
  clear: () => void;
} | null>(null);

const applyDefault = (items: Address[], next: Address) => {
  if (!next.isDefault) return items;
  return items.map((item) =>
    item.id === next.id ? next : { ...item, isDefault: false }
  );
};

const reducer = (state: AddressesState, action: AddressesAction): AddressesState => {
  switch (action.type) {
    case 'setItems':
      return { ...state, items: action.items };
    case 'setLoading':
      return { ...state, loading: action.value };
    case 'setError':
      return { ...state, error: action.error };
    case 'add': {
      const items = state.items.some((item) => item.id === action.address.id)
        ? state.items
        : [...state.items, action.address];
      return { ...state, items: applyDefault(items, action.address) };
    }
    case 'update': {
      const items = state.items.map((item) =>
        item.id === action.address.id ? action.address : item
      );
      return { ...state, items: applyDefault(items, action.address) };
    }
    case 'remove':
      return { ...state, items: state.items.filter((item) => item.id !== action.id) };
    case 'clear':
      return { items: [], loading: false, error: null };
    default:
      return state;
  }
};

export const AddressesProvider = ({ children }: { children: React.ReactNode }) => {
  const { status } = useAuth();
  const [state, dispatch] = useReducer(reducer, { items: [], loading: false, error: null });

  useEffect(() => {
    if (status !== 'authed') {
      dispatch({ type: 'clear' });
      return;
    }
    dispatch({ type: 'setLoading', value: true });
    fetchAddresses()
      .then((items) => {
        dispatch({ type: 'setItems', items });
        dispatch({ type: 'setError', error: null });
      })
      .catch((err: any) => {
        dispatch({ type: 'setError', error: err?.message ?? 'Unable to load addresses.' });
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
        const items = await fetchAddresses();
        dispatch({ type: 'setItems', items });
        dispatch({ type: 'setError', error: null });
        return true;
      } catch (err: any) {
        dispatch({ type: 'setError', error: err?.message ?? 'Unable to load addresses.' });
        return false;
      } finally {
        dispatch({ type: 'setLoading', value: false });
      }
    };

    const create = async (input: {
      name?: string | null;
      phone?: string | null;
      line1: string;
      line2?: string | null;
      city?: string | null;
      state?: string | null;
      postal_code?: string | null;
      country?: string | null;
      type?: string | null;
      is_default?: boolean;
    }) => {
      if (status !== 'authed') {
        const message = 'Please sign in to add an address.';
        dispatch({ type: 'setError', error: message });
        return { ok: false, message };
      }
      dispatch({ type: 'setLoading', value: true });
      try {
        const address = await createAddress(input);
        dispatch({ type: 'add', address });
        dispatch({ type: 'setError', error: null });
        return { ok: true, address };
      } catch (err: any) {
        const message = err?.message ?? 'Unable to save address.';
        dispatch({ type: 'setError', error: message });
        return { ok: false, message };
      } finally {
        dispatch({ type: 'setLoading', value: false });
      }
    };

    const update = async (
      id: string,
      input: Partial<{
        name?: string | null;
        phone?: string | null;
        line1: string;
        line2?: string | null;
        city?: string | null;
        state?: string | null;
        postal_code?: string | null;
        country?: string | null;
        type?: string | null;
        is_default?: boolean;
      }>
    ) => {
      if (status !== 'authed') {
        const message = 'Please sign in to update your address.';
        dispatch({ type: 'setError', error: message });
        return { ok: false, message };
      }
      dispatch({ type: 'setLoading', value: true });
      try {
        const address = await updateAddress(id, input);
        dispatch({ type: 'update', address });
        dispatch({ type: 'setError', error: null });
        return { ok: true, address };
      } catch (err: any) {
        const message = err?.message ?? 'Unable to update address.';
        dispatch({ type: 'setError', error: message });
        return { ok: false, message };
      } finally {
        dispatch({ type: 'setLoading', value: false });
      }
    };

    const remove = async (id: string) => {
      if (status !== 'authed') {
        const message = 'Please sign in to delete your address.';
        dispatch({ type: 'setError', error: message });
        return { ok: false, message };
      }
      dispatch({ type: 'setLoading', value: true });
      try {
        await deleteAddress(id);
        dispatch({ type: 'remove', id });
        dispatch({ type: 'setError', error: null });
        return { ok: true };
      } catch (err: any) {
        const message = err?.message ?? 'Unable to delete address.';
        dispatch({ type: 'setError', error: message });
        return { ok: false, message };
      } finally {
        dispatch({ type: 'setLoading', value: false });
      }
    };

    const setDefault = async (id: string) => {
      if (status !== 'authed') {
        const message = 'Please sign in to update your address.';
        dispatch({ type: 'setError', error: message });
        return { ok: false, message };
      }
      dispatch({ type: 'setLoading', value: true });
      try {
        const address = await updateAddress(id, { is_default: true });
        dispatch({ type: 'update', address });
        dispatch({ type: 'setError', error: null });
        return { ok: true, address };
      } catch (err: any) {
        const message = err?.message ?? 'Unable to update address.';
        dispatch({ type: 'setError', error: message });
        return { ok: false, message };
      } finally {
        dispatch({ type: 'setLoading', value: false });
      }
    };

    return {
      items: state.items,
      loading: state.loading,
      error: state.error,
      refresh,
      create,
      update,
      remove,
      setDefault,
      clear: () => dispatch({ type: 'clear' }),
    };
  }, [state.items, state.loading, state.error, status]);

  return <AddressesContext.Provider value={value}>{children}</AddressesContext.Provider>;
};

export const useAddresses = () => {
  const context = useContext(AddressesContext);
  if (!context) {
    throw new Error('useAddresses must be used within AddressesProvider');
  }
  return context;
};
