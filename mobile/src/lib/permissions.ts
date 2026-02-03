import AsyncStorage from '@react-native-async-storage/async-storage';
import * as ImagePicker from 'expo-image-picker';
import * as Location from 'expo-location';
import { Platform } from 'react-native';
import { primeExpoPushToken } from '@/src/lib/pushTokens';
import Constants from 'expo-constants';

const PERMISSIONS_KEY = 'app.permissions.requested';

const loadNotificationsModule = () => {
  try {
    // eslint-disable-next-line @typescript-eslint/no-var-requires
    return require('expo-notifications') as typeof import('expo-notifications');
  } catch {
    return null;
  }
};

export async function requestAppPermissions(): Promise<void> {
  try {
    const alreadyRequested = await AsyncStorage.getItem(PERMISSIONS_KEY);
    if (alreadyRequested === 'true') return;

    await ImagePicker.requestMediaLibraryPermissionsAsync().catch(() => null);
    try {
      if (Platform.OS !== 'web') {
        if ((Constants as { appOwnership?: string }).appOwnership === 'expo') {
          return;
        }
        const Notifications = loadNotificationsModule();
        if (Notifications) {
          await Notifications.requestPermissionsAsync().catch(() => null);
          await primeExpoPushToken();
        }
      }
    } catch {
      // ignore if notifications module is unavailable
    }
    await Location.requestForegroundPermissionsAsync().catch(() => null);

    await AsyncStorage.setItem(PERMISSIONS_KEY, 'true');
  } catch {
    // ignore permission errors
  }
}
