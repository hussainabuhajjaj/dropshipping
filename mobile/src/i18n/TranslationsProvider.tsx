import React, { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { fetchTranslations, registerMissingTranslations } from '@/src/api/translations';
import { normalizeLocale } from '@/src/api/locale';
import { usePreferences } from '@/src/store/preferencesStore';

type TranslationContextValue = {
  locale: string;
  ready: boolean;
  t: (key: string, fallback?: string, params?: Record<string, string | number>) => string;
  refresh: () => Promise<void>;
};

const TranslationsContext = createContext<TranslationContextValue | null>(null);

const interpolate = (template: string, params?: Record<string, string | number>) => {
  if (!params) return template;
  return Object.keys(params).reduce((acc, key) => {
    const value = String(params[key]);
    return acc.replace(new RegExp(`:${key}\\b`, 'g'), value);
  }, template);
};

export const TranslationsProvider = ({ children }: { children: React.ReactNode }) => {
  const { state } = usePreferences();
  const [translations, setTranslations] = useState<Record<string, string>>({});
  const [ready, setReady] = useState(false);
  const locale = normalizeLocale(state.language);
  const missingKeys = useRef<Set<string>>(new Set());
  const flushTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  const loadTranslations = useCallback(async (nextLocale?: string) => {
    try {
      const payload = await fetchTranslations(nextLocale);
      setTranslations(payload.translations ?? {});
      setReady(true);
    } catch {
      setTranslations({});
      setReady(true);
    }
  }, []);

  useEffect(() => {
    loadTranslations(locale);
  }, [loadTranslations, locale]);

  const scheduleMissingFlush = useCallback(() => {
    if (flushTimer.current) return;
    flushTimer.current = setTimeout(async () => {
      const keys = Array.from(missingKeys.current);
      missingKeys.current.clear();
      flushTimer.current = null;
      if (keys.length === 0) return;
      try {
        await registerMissingTranslations(keys);
      } catch {
        // ignore registration errors
      }
    }, 1500);
  }, []);

  const t = useCallback(
    (key: string, fallback?: string, params?: Record<string, string | number>) => {
      const template = translations[key] ?? fallback ?? key;
      if (!translations[key]) {
        missingKeys.current.add(key);
        scheduleMissingFlush();
      }
      return interpolate(template, params);
    },
    [translations, scheduleMissingFlush]
  );

  const value = useMemo(
    () => ({
      locale,
      ready,
      t,
      refresh: loadTranslations,
    }),
    [locale, ready, t, loadTranslations]
  );

  return <TranslationsContext.Provider value={value}>{children}</TranslationsContext.Provider>;
};

export const useTranslations = () => {
  const ctx = useContext(TranslationsContext);
  if (!ctx) {
    throw new Error('useTranslations must be used within TranslationsProvider');
  }
  return ctx;
};

export const useTranslationsOptional = () => {
  const ctx = useContext(TranslationsContext);
  if (ctx) return ctx;
  return {
    locale: 'en',
    ready: false,
    t: (key: string, fallback?: string) => fallback ?? key,
    refresh: async () => {},
  };
};

// Backwards-compatible aliases for callers using singular names.
export const TranslationProvider = TranslationsProvider;
export const useTranslation = useTranslations;
