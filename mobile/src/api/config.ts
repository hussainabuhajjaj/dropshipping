import Constants from 'expo-constants';

const DEFAULT_BASE_URL = 'http://192.168.46.244:8000';

const normalizeBaseUrl = (baseUrl: string) => baseUrl.replace(/\/+$/, '');

const isLikelyLocalHost = (baseUrl: string) => {
  try {
    const parsed = new URL(baseUrl);
    const host = parsed.hostname.toLowerCase();
    if (host === 'localhost' || host === '127.0.0.1' || host === '0.0.0.0') {
      return true;
    }
    if (host.endsWith('.test')) {
      return true;
    }
  } catch {
    return false;
  }
  return false;
};

const fromExpoExtra = (key: string) => {
  const extra = Constants.expoConfig?.extra as Record<string, unknown> | undefined;
  const value = extra?.[key];
  return typeof value === 'string' ? value : undefined;
};

const fromExpoHost = (): string | undefined => {
  const expoConfig: any = Constants.expoConfig ?? (Constants as any).manifest;
  const hostUri = typeof expoConfig?.hostUri === 'string' ? expoConfig.hostUri : undefined;
  const debuggerHost = typeof expoConfig?.debuggerHost === 'string' ? expoConfig.debuggerHost : undefined;
  const source = hostUri ?? debuggerHost;
  if (!source) return undefined;
  const host = source.split(':')[0];
  if (!host) return undefined;
  const port =
    process.env.EXPO_PUBLIC_API_PORT ??
    fromExpoExtra('API_PORT') ??
    fromExpoExtra('BACKEND_PORT') ??
    '8000';
  return `http://${host}:${port}`;
};

const getExpoHost = (): string | undefined => {
  const expoConfig: any = Constants.expoConfig ?? (Constants as any).manifest;
  const hostUri = typeof expoConfig?.hostUri === 'string' ? expoConfig.hostUri : undefined;
  const debuggerHost = typeof expoConfig?.debuggerHost === 'string' ? expoConfig.debuggerHost : undefined;
  const source = hostUri ?? debuggerHost;
  if (!source) return undefined;
  const host = source.split(':')[0];
  return host || undefined;
};

const explicitBaseUrl =
  process.env.EXPO_PUBLIC_API_URL ??
  process.env.EXPO_PUBLIC_API_BASE_URL ??
  process.env.BACKEND_URL ??
  fromExpoExtra('API_URL') ??
  fromExpoExtra('BACKEND_URL');

const explicitSiteUrl =
  process.env.EXPO_PUBLIC_SITE_URL ??
  fromExpoExtra('SITE_URL') ??
  fromExpoExtra('PUBLIC_SITE_URL');

const fallbackBaseUrl = fromExpoHost() ?? DEFAULT_BASE_URL;

const rebindLocalhostToExpoHost = (baseUrl: string): string | undefined => {
  const expoHost = getExpoHost();
  if (!expoHost) return undefined;

  try {
    const parsed = new URL(baseUrl);
    const port = parsed.port ? `:${parsed.port}` : '';
    return `${parsed.protocol}//${expoHost}${port}`;
  } catch {
    return undefined;
  }
};

const baseUrlCandidate = explicitBaseUrl ?? fallbackBaseUrl;
const resolvedBaseUrl =
  baseUrlCandidate && isLikelyLocalHost(baseUrlCandidate)
    ? rebindLocalhostToExpoHost(baseUrlCandidate) ?? baseUrlCandidate
    : baseUrlCandidate ?? DEFAULT_BASE_URL;

export const apiBaseUrl = normalizeBaseUrl(resolvedBaseUrl);

const normalizedSiteUrl =
  explicitSiteUrl && !isLikelyLocalHost(explicitSiteUrl)
    ? normalizeBaseUrl(explicitSiteUrl)
    : null;
export const publicSiteUrl = normalizedSiteUrl;

// Laravel storefront endpoints
export const storefrontBaseUrl = `${apiBaseUrl}/api/storefront`;

// Mobile v1 endpoints
export const mobileApiBaseUrl = `${apiBaseUrl}/api/mobile/v1`;

// General API endpoints (auth/chat/etc)
export const backendApiBaseUrl = `${apiBaseUrl}/api`;
