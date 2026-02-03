import AsyncStorage from '@react-native-async-storage/async-storage';
import Constants from 'expo-constants';
import * as Device from 'expo-device';
import { Platform } from 'react-native';

import { registerExpoToken, removeExpoToken } from '@/src/api/notifications';

const EXPO_TOKEN_KEY = 'push.expo.token';

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

export const getStoredExpoToken = async (): Promise<string | null> => {
  try {
    return await AsyncStorage.getItem(EXPO_TOKEN_KEY);
  } catch {
    return null;
  }
};

export const syncExpoPushToken = async (): Promise<string | null> => {
  try {
    if (Platform.OS === 'web') return null;
    if (!Device.isDevice) return null;
    if (isExpoGo()) return null;

    const Notifications = loadNotificationsModule();
    if (!Notifications) return null;
    const permission = await Notifications.getPermissionsAsync();
    if (!permission.granted) {
      const request = await Notifications.requestPermissionsAsync();
      if (!request.granted) {
        return null;
      }
    }

    const projectId = resolveProjectId();
    const response = projectId
      ? await Notifications.getExpoPushTokenAsync({ projectId })
      : await Notifications.getExpoPushTokenAsync();
    const token = response?.data ?? null;
    if (!token) return null;

    const stored = await getStoredExpoToken();
    if (stored === token) {
      return token;
    }

    await registerExpoToken(token);
    await AsyncStorage.setItem(EXPO_TOKEN_KEY, token);
    return token;
  } catch {
    return null;
  }
};

export const primeExpoPushToken = async (): Promise<string | null> => {
  try {
    if (Platform.OS === 'web') return null;
    if (!Device.isDevice) return null;
    if (isExpoGo()) return null;

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

    await AsyncStorage.setItem(EXPO_TOKEN_KEY, token);
    return token;
  } catch {
    return null;
  }
};

export const clearExpoPushToken = async (): Promise<void> => {
  try {
    if (Platform.OS === 'web') return;
    const token = await getStoredExpoToken();
    if (!token) return;
    await removeExpoToken(token);
    await AsyncStorage.removeItem(EXPO_TOKEN_KEY);
  } catch {
    // ignore cleanup failures
  }
};
