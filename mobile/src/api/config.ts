import Constants from 'expo-constants';

const DEFAULT_BASE_URL = 'http://192.168.46.244:8000';

const normalizeBaseUrl = (baseUrl: string) => baseUrl.replace(/\/+$/, '');
const stripApiSuffix = (baseUrl: string) => baseUrl.replace(/\/api\/?$/i, '');

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

const boolValue = (value: string | undefined, fallback = false): boolean => {
  if (value === undefined) return fallback;
  return ['1', 'true', 'yes', 'on'].includes(value.toLowerCase());
};

const intValue = (value: string | undefined, fallback: number): number => {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : fallback;
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
const normalizedApiRoot = normalizeBaseUrl(stripApiSuffix(apiBaseUrl));

const normalizedSiteUrl =
  explicitSiteUrl && !isLikelyLocalHost(explicitSiteUrl)
    ? normalizeBaseUrl(explicitSiteUrl)
    : null;
export const publicSiteUrl = normalizedSiteUrl;

// Laravel storefront endpoints
export const storefrontBaseUrl = `${normalizedApiRoot}/api/storefront`;

// Mobile v1 endpoints
export const mobileApiBaseUrl = `${normalizedApiRoot}/api/mobile/v1`;

// General API endpoints (auth/chat/etc)
export const backendApiBaseUrl = `${normalizedApiRoot}/api`;

const explicitRealtimeHost =
  process.env.EXPO_PUBLIC_PUSHER_HOST ??
  fromExpoExtra('PUSHER_HOST');

const realtimeHost = explicitRealtimeHost && explicitRealtimeHost.trim() !== ''
  ? explicitRealtimeHost.trim()
  : null;

export const supportChatRealtime = {
  enabled: boolValue(
    process.env.EXPO_PUBLIC_SUPPORT_CHAT_REALTIME_ENABLED ??
      fromExpoExtra('SUPPORT_CHAT_REALTIME_ENABLED'),
    false
  ),
  appKey:
    process.env.EXPO_PUBLIC_PUSHER_APP_KEY ??
    fromExpoExtra('PUSHER_APP_KEY') ??
    '',
  cluster:
    process.env.EXPO_PUBLIC_PUSHER_APP_CLUSTER ??
    fromExpoExtra('PUSHER_APP_CLUSTER') ??
    '',
  host: realtimeHost,
  wsPort: intValue(
    process.env.EXPO_PUBLIC_PUSHER_PORT ??
      fromExpoExtra('PUSHER_PORT'),
    6001
  ),
  wssPort: intValue(
    process.env.EXPO_PUBLIC_PUSHER_WSS_PORT ??
      fromExpoExtra('PUSHER_WSS_PORT'),
    443
  ),
  forceTLS: boolValue(
    process.env.EXPO_PUBLIC_PUSHER_TLS ??
      fromExpoExtra('PUSHER_USE_TLS'),
    true
  ),
  authEndpoint: `${mobileApiBaseUrl}/broadcasting/auth`,
} as const;
