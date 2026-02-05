import { normalizeCurrency } from '@/src/api/currency';

export function formatCurrency(value: number, currency?: string | null, fallback?: string): string {
  const code = normalizeCurrency(currency ?? fallback);
  try {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: code,
      maximumFractionDigits: 2,
    }).format(value);
  } catch {
    return `${code} ${value.toFixed(2)}`;
  }
}
