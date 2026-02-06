import AsyncStorage from '@react-native-async-storage/async-storage';
import * as ImagePicker from 'expo-image-picker';
import * as Location from 'expo-location';

const PERMISSIONS_KEY = 'app.permissions.requested';

export async function requestAppPermissions(): Promise<void> {
  try {
    const alreadyRequested = await AsyncStorage.getItem(PERMISSIONS_KEY);
    if (alreadyRequested === 'true') return;

    await ImagePicker.requestMediaLibraryPermissionsAsync().catch(() => null);
    await Location.requestForegroundPermissionsAsync().catch(() => null);

    await AsyncStorage.setItem(PERMISSIONS_KEY, 'true');
  } catch {
    // ignore permission errors
  }
}
