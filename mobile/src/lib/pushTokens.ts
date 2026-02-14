import AsyncStorage from '@react-native-async-storage/async-storage';
import Constants from 'expo-constants';
import * as Device from 'expo-device';
import { Platform } from 'react-native';

import { meRequest } from '@/src/api/auth';
import { registerExpoToken, removeExpoToken } from '@/src/api/notifications';

const EXPO_TOKEN_KEY = 'push.expo.token';
const EXPO_TOKEN_SYNCED_KEY = 'push.expo.token.synced';

export type PushSyncFailureReason =
  | null
  | 'web'
  | 'simulator'
  | 'expo_go'
  | 'no_notifications_module'
  | 'permission_denied'
  | 'no_token'
  | 'auth_required'
  | 'network'
  | 'register_failed'
  | 'unknown';

let lastPushSyncFailureReason: PushSyncFailureReason = null;
let lastPushSyncFailureContext: { message?: string; status?: number; url?: string } | null = null;

const resolveProjectId = (): string | undefined => {
  const eas = (Constants.expoConfig as { extra?: { eas?: { projectId?: string } } } | undefined)?.extra?.eas;
  const easConfig = (Constants as { easConfig?: { projectId?: string } }).easConfig;
  return eas?.projectId ?? easConfig?.projectId;
};

const isExpoGo = (): boolean => {
  const ownership = (Constants as { appOwnership?: string }).appOwnership;
  return ownership === 'expo';
};

const loadNotificationsModule = () => {
  try {
    // eslint-disable-next-line @typescript-eslint/no-var-requires
    return require('expo-notifications') as typeof import('expo-notifications');
  } catch {
    return null;
  }
};

const debug = (message: string, context?: Record<string, unknown>) => {
  if (__DEV__) {
    // eslint-disable-next-line no-console
    console.warn(`[pushTokens] ${message}`, context ?? {});
  }
};

export const getStoredExpoToken = async (): Promise<string | null> => {
  try {
    return await AsyncStorage.getItem(EXPO_TOKEN_KEY);
  } catch {
    return null;
  }
};

export const getLastPushSyncFailureReason = (): PushSyncFailureReason => lastPushSyncFailureReason;
export const getLastPushSyncFailureContext = (): { message?: string; status?: number; url?: string } | null =>
  lastPushSyncFailureContext;

const isStoredTokenSynced = async (): Promise<boolean> => {
  try {
    return (await AsyncStorage.getItem(EXPO_TOKEN_SYNCED_KEY)) === 'true';
  } catch {
    return false;
  }
};

const storeExpoToken = async (token: string, synced: boolean) => {
  try {
    await AsyncStorage.setItem(EXPO_TOKEN_KEY, token);
    await AsyncStorage.setItem(EXPO_TOKEN_SYNCED_KEY, synced ? 'true' : 'false');
  } catch {
    // ignore storage errors
  }
};

export const syncExpoPushToken = async (): Promise<string | null> => {
  try {
    lastPushSyncFailureReason = null;
    lastPushSyncFailureContext = null;

    if (Platform.OS === 'web') {
      lastPushSyncFailureReason = 'web';
      debug('skipped on web');
      return null;
    }
    if (!Device.isDevice) {
      lastPushSyncFailureReason = 'simulator';
      debug('requires physical device');
      return null;
    }
    if (isExpoGo()) {
      lastPushSyncFailureReason = 'expo_go';
      debug('Expo Go detected - use development/production build for push token sync');
      return null;
    }

    const Notifications = loadNotificationsModule();
    if (!Notifications) {
      lastPushSyncFailureReason = 'no_notifications_module';
      debug('expo-notifications module not available');
      return null;
    }
    const permission = await Notifications.getPermissionsAsync();
    if (!permission.granted) {
      const request = await Notifications.requestPermissionsAsync();
      if (!request.granted) {
        lastPushSyncFailureReason = 'permission_denied';
        debug('permission denied by user');
        return null;
      }
    }

    const projectId = resolveProjectId();
    const response = projectId
      ? await Notifications.getExpoPushTokenAsync({ projectId })
      : await Notifications.getExpoPushTokenAsync();
    const token = response?.data ?? null;
    if (!token) {
      lastPushSyncFailureReason = 'no_token';
      debug('no token returned by Expo');
      return null;
    }

    const [stored, synced] = await Promise.all([getStoredExpoToken(), isStoredTokenSynced()]);
    if (stored === token && synced) {
      return token;
    }

    await storeExpoToken(token, false);
    const registered = await registerExpoToken(token)
      .then(() => true)
      .catch((error) => {
        const status = typeof error?.status === 'number' ? error.status : undefined;
        const message = typeof error?.message === 'string' ? error.message : undefined;
        const url = typeof error?.url === 'string' ? error.url : undefined;
        lastPushSyncFailureContext = { status, message, url };

        if (status === 0 || /network request failed/i.test(message ?? '')) {
          lastPushSyncFailureReason = 'network';
        } else {
          lastPushSyncFailureReason = 'register_failed';
        }

        debug('registerExpoToken failed', {
          message,
          status,
          url,
        });
        return false;
      });

    if (!registered) {
      if (lastPushSyncFailureReason === 'register_failed') {
        const isAuthenticated = await meRequest()
          .then(() => true)
          .catch((error) => {
            const status = typeof error?.status === 'number' ? error.status : undefined;
            return status !== 401 && status !== 403;
          });

        if (!isAuthenticated) {
          lastPushSyncFailureReason = 'auth_required';
        }
      }

      return null;
    }

    await storeExpoToken(token, true);

    return token;
  } catch {
    lastPushSyncFailureReason = 'unknown';
    lastPushSyncFailureContext = null;
    debug('syncExpoPushToken unexpected error');
    return null;
  }
};

export const syncExpoPushTokenIfPermitted = async (): Promise<string | null> => {
  try {
    if (Platform.OS === 'web' || !Device.isDevice || isExpoGo()) return null;

    const Notifications = loadNotificationsModule();
    if (!Notifications) return null;
    const permission = await Notifications.getPermissionsAsync();
    if (!permission.granted) return null;

    const projectId = resolveProjectId();
    const response = projectId
      ? await Notifications.getExpoPushTokenAsync({ projectId })
      : await Notifications.getExpoPushTokenAsync();
    const token = response?.data ?? null;
    if (!token) return null;

    const [stored, synced] = await Promise.all([getStoredExpoToken(), isStoredTokenSynced()]);
    if (stored === token && synced) {
      return token;
    }

    await storeExpoToken(token, false);
    const registered = await registerExpoToken(token)
      .then(() => true)
      .catch((error) => {
        debug('registerExpoToken (permitted) failed', {
          message: error?.message,
          status: error?.status,
          url: error?.url,
        });
        return false;
      });
    if (registered) {
      await storeExpoToken(token, true);
    }
    return token;
  } catch {
    return null;
  }
};

export const primeExpoPushToken = async (): Promise<string | null> => {
  try {
    lastPushSyncFailureReason = null;
    lastPushSyncFailureContext = null;

    if (Platform.OS === 'web') {
      lastPushSyncFailureReason = 'web';
      return null;
    }
    if (!Device.isDevice) {
      lastPushSyncFailureReason = 'simulator';
      return null;
    }
    if (isExpoGo()) {
      lastPushSyncFailureReason = 'expo_go';
      return null;
    }

    const Notifications = loadNotificationsModule();
    if (!Notifications) {
      lastPushSyncFailureReason = 'no_notifications_module';
      return null;
    }
    const permission = await Notifications.getPermissionsAsync();
    if (!permission.granted) {
      const request = await Notifications.requestPermissionsAsync();
      if (!request.granted) {
        lastPushSyncFailureReason = 'permission_denied';
        return null;
      }
    }

    const projectId = resolveProjectId();
    const response = projectId
      ? await Notifications.getExpoPushTokenAsync({ projectId })
      : await Notifications.getExpoPushTokenAsync();
    const token = response?.data ?? null;
    if (!token) {
      lastPushSyncFailureReason = 'no_token';
      return null;
    }

    await storeExpoToken(token, false);
    return token;
  } catch {
    lastPushSyncFailureReason = 'unknown';
    lastPushSyncFailureContext = null;
    return null;
  }
};

export const clearExpoPushToken = async (): Promise<void> => {
  try {
    if (Platform.OS === 'web') return;
    const token = await getStoredExpoToken();
    await AsyncStorage.removeItem(EXPO_TOKEN_KEY);
    await AsyncStorage.removeItem(EXPO_TOKEN_SYNCED_KEY);
    if (!token) return;
    await removeExpoToken(token).catch(() => null);
  } catch {
    // ignore cleanup failures
  }
};
