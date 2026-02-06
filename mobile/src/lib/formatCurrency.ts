import { normalizeCurrency } from '@/src/api/currency';

const formatterCache = new Map<string, Intl.NumberFormat>();

function getDeviceLocale(): string | undefined {
  try {
    const locale =
      (globalThis as any)?.navigator?.language ||
      (globalThis as any)?.Intl?.DateTimeFormat?.().resolvedOptions?.().locale;
    if (typeof locale === 'string' && locale.trim().length > 0) return locale;
    return undefined;
  } catch {
    return undefined;
  }
}

export function formatCurrency(
  value: number,
  currency?: string | null,
  fallback?: string,
  locale?: string | null
): string {
  const code = normalizeCurrency(currency ?? fallback);
  const resolvedLocale = locale ?? getDeviceLocale();
  try {
    const cacheKey = `${resolvedLocale ?? 'default'}:${code}`;
    const formatter =
      formatterCache.get(cacheKey) ??
      new Intl.NumberFormat(resolvedLocale ?? undefined, {
        style: 'currency',
        currency: code,
        maximumFractionDigits: 2,
      });
    if (!formatterCache.has(cacheKey)) {
      formatterCache.set(cacheKey, formatter);
    }
    return formatter.format(value);
  } catch {
    return `${code} ${value.toFixed(2)}`;
  }
}
