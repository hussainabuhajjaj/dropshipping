const FALLBACK = 'USD';

let apiCurrency: string = FALLBACK;

export function normalizeCurrency(input?: string | null): string {
  if (!input) return FALLBACK;
  const raw = String(input).toUpperCase();
  const parenMatch = raw.match(/\(([A-Z]{3})\)/);
  if (parenMatch) {
    return normalizeCode(parenMatch[1]);
  }
  const codeMatch = raw.match(/[A-Z]{3}/);
  if (codeMatch) {
    return normalizeCode(codeMatch[0]);
  }
  return normalizeCode(raw);
}

function normalizeCode(code: string): string {
  const upper = code.toUpperCase();
  if (upper === 'XFA' || upper === 'XFC') return 'XAF';
  if (upper === 'XOF' || upper === 'XAF') return upper;
  if (upper === 'USD') return 'USD';
  return FALLBACK;
}

export function setApiCurrency(value?: string | null) {
  apiCurrency = normalizeCurrency(value);
}

export function getApiCurrency(): string {
  return apiCurrency;
}
