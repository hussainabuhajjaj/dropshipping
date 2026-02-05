const FALLBACK = 'en';

let apiLocale: string = FALLBACK;

export function normalizeLocale(input?: string | null): string {
  if (!input) return FALLBACK;
  const raw = String(input).toLowerCase();
  if (raw.startsWith('fr') || raw.includes('french')) return 'fr';
  if (raw.startsWith('en') || raw.includes('english')) return 'en';
  return FALLBACK;
}

export function setApiLocale(value?: string | null) {
  apiLocale = normalizeLocale(value);
}

export function getApiLocale(): string {
  return apiLocale;
}
