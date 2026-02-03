import FontAwesome from '@expo/vector-icons/FontAwesome';
import { DarkTheme, DefaultTheme, ThemeProvider } from '@react-navigation/native';
import { useFonts } from 'expo-font';
import { Stack } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { useEffect } from 'react';
import { LogBox, StyleSheet, Text, View } from 'react-native';
import 'react-native-reanimated';

import { useColorScheme } from '@/components/useColorScheme';
import { CartProvider } from '@/lib/cartStore';
import { OrdersProvider } from '@/lib/ordersStore';
import { AuthProvider } from '@/lib/authStore';
import { PaymentMethodsProvider } from '@/lib/paymentMethodsStore';
import { AddressesProvider } from '@/lib/addressesStore';
import { WishlistProvider } from '@/lib/wishlistStore';
import { RecentlyViewedProvider } from '@/lib/recentlyViewedStore';
import { PreferencesProvider } from '@/src/store/preferencesStore';
import { PortalHost } from '@/src/overlays/PortalHost';
import { apiBaseUrl } from '@/src/api/config';
import { requestAppPermissions } from '@/src/lib/permissions';
import { ToastProvider } from '@/src/overlays/ToastProvider';

const ROUTE_TITLE_OVERRIDES: Record<string, string> = {
  '+not-found': 'Not Found',
  'account/wishlist': 'Wish List',
  modal: 'Product',
};

const SEGMENT_TITLE_OVERRIDES: Record<string, string> = {
  wishlist: 'Wish List',
  faq: 'FAQ',
};

const DYNAMIC_TITLE_BY_PARENT: Record<string, string> = {
  products: 'Product',
  orders: 'Order Details',
  legal: 'Legal',
};

const formatRouteTitle = (routeName: string) => {
  const normalized = routeName
    .replace(/\(.*?\)\//g, '')
    .replace(/\/index$/, '')
    .replace(/^index$/, '');

  if (!normalized) {
    return 'Home';
  }

  const override = ROUTE_TITLE_OVERRIDES[normalized];
  if (override) {
    return override;
  }

  const parts = normalized.split('/').filter(Boolean);
  const last = parts[parts.length - 1] ?? normalized;

  if (last.startsWith('[') && last.endsWith(']')) {
    const parent = parts[parts.length - 2] ?? '';
    return DYNAMIC_TITLE_BY_PARENT[parent] ?? 'Details';
  }

  const cleaned = last.replace(/-\d+$/, '');
  const segmentOverride = SEGMENT_TITLE_OVERRIDES[cleaned];
  if (segmentOverride) {
    return segmentOverride;
  }

  return cleaned
    .split('-')
    .filter(Boolean)
    .map((word) => `${word.charAt(0).toUpperCase()}${word.slice(1)}`)
    .join(' ');
};

export {
  // Catch any errors thrown by the Layout component.
  ErrorBoundary,
} from 'expo-router';

export const unstable_settings = {
  // Ensure that reloading on `/modal` keeps a back button present.
  initialRouteName: 'index',
};

// Prevent the splash screen from auto-hiding before asset loading is complete.
SplashScreen.preventAutoHideAsync();

export default function RootLayout() {
  const [loaded, error] = useFonts({
    SpaceMono: require('../assets/fonts/SpaceMono-Regular.ttf'),
    ...FontAwesome.font,
  });

  useEffect(() => {
    LogBox.ignoreLogs([
      'SafeAreaView has been deprecated',
      'Clipboard has been extracted from react-native core',
      'PushNotificationIOS has been extracted from react-native core',
    ]);
  }, []);

  // Expo Router uses Error Boundaries to catch errors in the navigation tree.
  useEffect(() => {
    if (error) throw error;
  }, [error]);

  useEffect(() => {
    if (loaded) {
      SplashScreen.hideAsync();
    }
  }, [loaded]);

  useEffect(() => {
    if (loaded) {
      requestAppPermissions();
    }
  }, [loaded]);

  if (!loaded) {
    return null;
  }

  return <RootLayoutNav />;
}

function RootLayoutNav() {
  const colorScheme = useColorScheme();

  return (
    <ThemeProvider value={colorScheme === 'dark' ? DarkTheme : DefaultTheme}>
      <PortalHost>
        <ToastProvider>
          <AuthProvider>
            <PreferencesProvider>
              <CartProvider>
                <OrdersProvider>
                  <PaymentMethodsProvider>
                    <AddressesProvider>
                      <WishlistProvider>
                        <RecentlyViewedProvider>
                          <Stack
                            screenOptions={({ route }) => ({
                              title: formatRouteTitle(route.name),
                            })}
                          >
                            <Stack.Screen name="index" options={{ headerShown: false }} />
                            <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
                            <Stack.Screen name="modal" options={{ presentation: 'modal' }} />
                          </Stack>
                          {/* <DebugApiBanner /> */}
                        </RecentlyViewedProvider>
                      </WishlistProvider>
                    </AddressesProvider>
                  </PaymentMethodsProvider>
                </OrdersProvider>
              </CartProvider>
            </PreferencesProvider>
          </AuthProvider>
        </ToastProvider>
      </PortalHost>
    </ThemeProvider>
  );
}

function DebugApiBanner() {
  if (!__DEV__) return null;
  return (
    <View pointerEvents="none" style={styles.apiBanner}>
      <Text style={styles.apiBannerText}>API: {apiBaseUrl}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  apiBanner: {
    position: 'absolute',
    left: 12,
    right: 12,
    bottom: 12,
    backgroundColor: 'rgba(0,0,0,0.7)',
    paddingVertical: 6,
    paddingHorizontal: 10,
    borderRadius: 8,
  },
  apiBannerText: {
    color: '#fff',
    fontSize: 11,
  },
});
