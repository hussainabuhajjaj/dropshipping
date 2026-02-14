import React, { createContext, useContext, useEffect, useMemo, useReducer, useRef, useState } from 'react';
import { loadPersisted, savePersisted } from './persist';
import { fetchPreferences, fetchPreferencesLookups, updatePreferences } from '@/src/api/preferences';
import { setApiCurrency, normalizeCurrency } from '@/src/api/currency';
import { setApiLocale } from '@/src/api/locale';
import type { PreferencesLookups, Preferences as ApiPreferences } from '@/src/types/preferences';
import { useAuth } from '@/lib/authStore';
import { clearExpoPushToken, primeExpoPushToken, syncExpoPushTokenIfPermitted } from '@/src/lib/pushTokens';

export type ShippingAddress = {
  country: string;
  name: string;
  address: string;
  city: string;
  postcode: string;
  phone: string;
};

type PreferencesState = {
  country: string;
  currency: string;
  size: string;
  language: string;
  languageSource: 'device' | 'user';
  shippingAddress: ShippingAddress;
  notifications: {
    push: boolean;
    email: boolean;
    sms: boolean;
  };
  lookups: PreferencesLookups;
  loading: boolean;
  error: string | null;
};

type PreferencesAction =
  | { type: 'setCountry'; value: string }
  | { type: 'setCurrency'; value: string }
  | { type: 'setSize'; value: string }
  | { type: 'setLanguage'; value: string }
  | { type: 'setLanguageSource'; value: 'device' | 'user' }
  | { type: 'setShippingAddress'; value: Partial<ShippingAddress> }
  | { type: 'setNotification'; key: 'push' | 'email' | 'sms'; value: boolean }
  | { type: 'setLookups'; value: PreferencesLookups }
  | { type: 'setLoading'; value: boolean }
  | { type: 'setError'; value: string | null }
  | { type: 'setFromApi'; value: ApiPreferences };

const defaultState: PreferencesState = {
  country: 'United States',
  currency: 'USD ($)',
  size: 'US',
  language: 'English',
  languageSource: 'device',
  shippingAddress: {
    country: 'United States',
    name: 'Romina',
    address: '74 Sunset Blvd, Apt 12',
    city: 'Los Angeles',
    postcode: '90001',
    phone: '+1 000 000 000',
  },
  notifications: {
    push: false,
    email: false,
    sms: false,
  },
  lookups: {
    countries: [],
    currencies: [],
    sizes: [],
    languages: [],
  },
  loading: false,
  error: null,
};

const PreferencesContext = createContext<{
  state: PreferencesState;
  setCountry: (value: string) => void;
  setCurrency: (value: string) => void;
  setSize: (value: string) => void;
  setLanguage: (value: string) => Promise<{ ok: boolean; message?: string }>;
  setShippingAddress: (value: Partial<ShippingAddress>) => void;
  setNotification: (key: 'push' | 'email' | 'sms', value: boolean) => void;
  refresh: () => Promise<void>;
} | null>(null);

const reducer = (state: PreferencesState, action: PreferencesAction): PreferencesState => {
  switch (action.type) {
    case 'setCountry':
      return { ...state, country: action.value };
    case 'setCurrency':
      return { ...state, currency: action.value };
    case 'setSize':
      return { ...state, size: action.value };
    case 'setLanguage':
      return { ...state, language: action.value };
    case 'setLanguageSource':
      return { ...state, languageSource: action.value };
    case 'setShippingAddress':
      return { ...state, shippingAddress: { ...state.shippingAddress, ...action.value } };
    case 'setNotification':
      return {
        ...state,
        notifications: { ...state.notifications, [action.key]: action.value },
      };
    case 'setLookups':
      return { ...state, lookups: action.value };
    case 'setLoading':
      return { ...state, loading: action.value };
    case 'setError':
      return { ...state, error: action.value };
    case 'setFromApi':
      return {
        ...state,
        country: action.value.country,
        currency: action.value.currency,
        size: action.value.size,
        language: action.value.language,
        languageSource: 'user',
        notifications: action.value.notifications,
      };
    default:
      return state;
  }
};

const getDeviceLanguageCode = (): string => {
  try {
    const locale =
      (globalThis as any)?.navigator?.language ||
      (globalThis as any)?.Intl?.DateTimeFormat?.().resolvedOptions?.().locale ||
      'en';
    return String(locale).split('-')[0].toLowerCase();
  } catch {
    return 'en';
  }
};

export const PreferencesProvider = ({ children }: { children: React.ReactNode }) => {
  const { status } = useAuth();
  const [state, dispatch] = useReducer(reducer, defaultState);
  const [hydrated, setHydrated] = useState(false);
  const lastPushSync = useRef<{ status: typeof status; push: boolean } | null>(null);

  useEffect(() => {
    loadPersisted<PreferencesState>({ key: 'prefs:v1' }, defaultState).then((data) => {
      dispatch({ type: 'setCountry', value: data.country });
      dispatch({ type: 'setCurrency', value: data.currency });
      dispatch({ type: 'setSize', value: data.size });
      dispatch({ type: 'setLanguage', value: data.language });
      setApiCurrency(normalizeCurrency(data.currency));
      setApiLocale(data.language);
      dispatch({
        type: 'setLanguageSource',
        value: (data as any).languageSource === 'user' ? 'user' : 'device',
      });
      dispatch({
        type: 'setShippingAddress',
        value: (data as any).shippingAddress ?? defaultState.shippingAddress,
      });
      const notifications = (data as any).notifications as PreferencesState['notifications'] | undefined;
      dispatch({ type: 'setNotification', key: 'push', value: Boolean(notifications?.push) });
      dispatch({ type: 'setNotification', key: 'email', value: Boolean(notifications?.email) });
      dispatch({ type: 'setNotification', key: 'sms', value: Boolean(notifications?.sms) });
      setHydrated(true);
    });
  }, []);

  useEffect(() => {
    if (hydrated) {
      savePersisted<PreferencesState>({ key: 'prefs:v1' }, {
        country: state.country,
        currency: state.currency,
        size: state.size,
        language: state.language,
        languageSource: state.languageSource,
        shippingAddress: state.shippingAddress,
        notifications: state.notifications,
      } as PreferencesState);
    }
  }, [state, hydrated]);

  const value = useMemo(
    () => {
      const applyUpdate = async (next: Partial<ApiPreferences>) => {
        if (status !== 'authed') {
          return { ok: true as const };
        }
        try {
          dispatch({ type: 'setLoading', value: true });
          const updated = await updatePreferences(next);
          dispatch({ type: 'setFromApi', value: updated });
          dispatch({ type: 'setError', value: null });
          return { ok: true as const };
        } catch (err: any) {
          dispatch({ type: 'setError', value: err?.message ?? 'Unable to update preferences.' });
          return { ok: false as const, message: err?.message ?? 'Unable to update preferences.' };
        } finally {
          dispatch({ type: 'setLoading', value: false });
        }
      };

      return {
        state,
        setCountry: (value: string) => {
          if (value === state.country) return;
          dispatch({ type: 'setCountry', value });
          applyUpdate({ country: value });
        },
        setCurrency: (value: string) => {
          if (value === state.currency) return;
          dispatch({ type: 'setCurrency', value });
          setApiCurrency(normalizeCurrency(value));
          applyUpdate({ currency: value });
        },
        setSize: (value: string) => {
          if (value === state.size) return;
          dispatch({ type: 'setSize', value });
          applyUpdate({ size: value });
        },
        setLanguage: (value: string) => {
          if (value === state.language) return Promise.resolve({ ok: true });
          dispatch({ type: 'setLanguage', value });
          dispatch({ type: 'setLanguageSource', value: 'user' });
          setApiLocale(value);
          return applyUpdate({ language: value });
        },
        setShippingAddress: (value: Partial<ShippingAddress>) =>
          dispatch({ type: 'setShippingAddress', value }),
        setNotification: (key: 'push' | 'email' | 'sms', value: boolean) => {
          if (state.notifications[key] === value) return;
          dispatch({ type: 'setNotification', key, value });
          applyUpdate({ notifications: { ...state.notifications, [key]: value } });
        },
        refresh: async () => {
          try {
            dispatch({ type: 'setLoading', value: true });
            const lookups = await fetchPreferencesLookups();
            dispatch({ type: 'setLookups', value: lookups });
            if (status === 'authed') {
              const prefs = await fetchPreferences();
              dispatch({ type: 'setFromApi', value: prefs });
            }
            dispatch({ type: 'setError', value: null });
          } catch (err: any) {
            dispatch({ type: 'setError', value: err?.message ?? 'Unable to load preferences.' });
          } finally {
            dispatch({ type: 'setLoading', value: false });
          }
        },
      };
    },
    [state, status]
  );

  useEffect(() => {
    let active = true;
    const hydrateRemote = async () => {
      try {
        dispatch({ type: 'setLoading', value: true });
        const lookups = await fetchPreferencesLookups();
        if (!active) return;
        dispatch({ type: 'setLookups', value: lookups });
        if (status === 'authed') {
          const prefs = await fetchPreferences();
          if (!active) return;
          dispatch({ type: 'setFromApi', value: prefs });
        }
        dispatch({ type: 'setError', value: null });
      } catch (err: any) {
        if (!active) return;
        dispatch({ type: 'setError', value: err?.message ?? 'Unable to load preferences.' });
      } finally {
        if (active) dispatch({ type: 'setLoading', value: false });
      }
    };

    hydrateRemote();
    return () => {
      active = false;
    };
  }, [status]);

  useEffect(() => {
    setApiLocale(state.language);
  }, [state.language]);

  useEffect(() => {
    if (!hydrated) return;
    if (state.languageSource === 'user') return;

    const deviceCode = getDeviceLanguageCode();
    const nextLanguage = deviceCode.startsWith('fr') ? 'French' : 'English';

    if (nextLanguage !== state.language) {
      dispatch({ type: 'setLanguage', value: nextLanguage });
      dispatch({ type: 'setLanguageSource', value: 'device' });
      if (status === 'authed') {
        updatePreferences({ language: nextLanguage }).catch(() => {});
      }
    }
  }, [hydrated, state.language, state.languageSource, status]);

  useEffect(() => {
    if (!hydrated) return;

    const next = { status, push: state.notifications.push };
    const prev = lastPushSync.current;
    if (prev && prev.status === next.status && prev.push === next.push) return;
    lastPushSync.current = next;

    if (state.notifications.push) {
      if (status === 'authed') {
        syncExpoPushTokenIfPermitted().catch(() => {});
      } else {
        primeExpoPushToken().catch(() => {});
      }
      return;
    }

    clearExpoPushToken().catch(() => {});
  }, [hydrated, status, state.notifications.push]);

  return <PreferencesContext.Provider value={value}>{children}</PreferencesContext.Provider>;
};

export const usePreferences = () => {
  const ctx = useContext(PreferencesContext);
  if (!ctx) {
    throw new Error('usePreferences must be used within PreferencesProvider');
  }
  return ctx;
};
