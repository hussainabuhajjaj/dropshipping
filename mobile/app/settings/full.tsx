import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { Linking } from 'react-native';
import { theme } from '@/src/theme';
import { usePreferences } from '@/src/store/preferencesStore';
import { StatusDialog } from '@/src/overlays/StatusDialog';
import { useTranslations } from '@/src/i18n/TranslationsProvider';
import { SafeAreaView } from 'react-native-safe-area-context';
import {
  clearExpoPushToken,
  getLastPushSyncFailureContext,
  getLastPushSyncFailureReason,
  primeExpoPushToken,
  type PushSyncFailureReason,
  syncExpoPushToken,
} from '@/src/lib/pushTokens';
import { mobileApiBaseUrl } from '@/src/api/config';
import { useAuth } from '@/lib/authStore';

export default function SettingsFullScreen() {
  const { state, setNotification } = usePreferences();
  const { status } = useAuth();
  const [activeDialog, setActiveDialog] = useState<null | 'push_confirm' | 'push_processing' | 'push_failed'>(null);
  const [pushFailureMessage, setPushFailureMessage] = useState<string | null>(null);
  const [pushFailureReason, setPushFailureReason] = useState<PushSyncFailureReason>(null);
  const { t } = useTranslations();

  const toggles = [
    { id: 'push' as const, label: t('Push notifications', 'Push notifications'), active: state.notifications.push },
    { id: 'email' as const, label: t('Email updates', 'Email updates'), active: state.notifications.email },
    { id: 'sms' as const, label: t('SMS alerts', 'SMS alerts'), active: state.notifications.sms },
  ];

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView style={styles.scroll} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.headerRow}>
          <Pressable
            style={styles.iconButton}
            onPress={() => router.back()}
            accessibilityRole="button"
            accessibilityLabel="Back"
            hitSlop={8}
          >
            <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
          </Pressable>
          <Text style={styles.title}>{t('Preferences', 'Preferences')}</Text>
          <Pressable
            style={styles.iconButton}
            onPress={() => router.push('/(tabs)/home')}
            accessibilityRole="button"
            accessibilityLabel="Close"
            hitSlop={8}
          >
            <Feather name="x" size={16} color={theme.colors.inkDark} />
          </Pressable>
        </View>

        <View style={styles.card}>
          {toggles.map((item) => (
            <View key={item.id} style={styles.row}>
              <Text style={styles.rowText}>{item.label}</Text>
              <Pressable
                style={[styles.toggle, item.active ? styles.toggleActive : null]}
                onPress={() => {
                  const next = !item.active;
                  if (item.id === 'push') {
                    if (next) {
                      setActiveDialog('push_confirm');
                      return;
                    }
                    setNotification('push', false);
                    clearExpoPushToken().catch(() => {});
                    return;
                  }
                  setNotification(item.id, next);
                }}
                accessibilityRole="switch"
                accessibilityState={{ checked: item.active }}
                accessibilityLabel={item.label}
                hitSlop={8}
              >
                <View style={styles.toggleDot} />
              </Pressable>
            </View>
          ))}
        </View>

        {__DEV__ ? (
          <Text style={styles.debugText}>Push API: {mobileApiBaseUrl}</Text>
        ) : null}

        <Pressable
          style={styles.primaryButton}
          onPress={() => router.push('/settings')}
          accessibilityRole="button"
          accessibilityLabel="Save preferences"
        >
          <Text style={styles.primaryText}>{t('Save', 'Save')}</Text>
        </Pressable>
      </ScrollView>

      <StatusDialog
        visible={activeDialog === 'push_confirm'}
        variant="info"
        title={t('Push notifications', 'Push notifications')}
        message={t(
          'We will ask for permission to send order updates and important account alerts. You can change this anytime in your device settings.',
          'We will ask for permission to send order updates and important account alerts. You can change this anytime in your device settings.'
        )}
        primaryLabel={t('Enable', 'Enable')}
        onPrimary={async () => {
          setActiveDialog('push_processing');
          const token = status === 'authed' ? await syncExpoPushToken() : await primeExpoPushToken();
          if (token) {
            setNotification('push', true);
            setPushFailureMessage(null);
            setPushFailureReason(null);
            setActiveDialog(null);
            return;
          }

          const reason = getLastPushSyncFailureReason();
          const context = getLastPushSyncFailureContext();
          setPushFailureReason(reason);
          const message = (() => {
            if (reason === 'simulator') {
              return t(
                'Push notifications require a physical device. Android emulator and iOS simulator are not supported for remote push.',
                'Push notifications require a physical device. Android emulator and iOS simulator are not supported for remote push.'
              );
            }
            if (reason === 'expo_go') {
              return t(
                'Push notifications are not available in Expo Go. Use a development build or production build.',
                'Push notifications are not available in Expo Go. Use a development build or production build.'
              );
            }
            if (reason === 'auth_required') {
              return t(
                'Session expired. Please sign in again, then retry enabling push notifications.',
                'Session expired. Please sign in again, then retry enabling push notifications.'
              );
            }
            if (reason === 'network') {
              const statusSuffix = context?.status ? ` (status: ${context.status})` : '';
              return t(
                `Network error while registering the push token${statusSuffix}. Check internet and API endpoint: ${mobileApiBaseUrl}`,
                `Network error while registering the push token${statusSuffix}. Check internet and API endpoint: ${mobileApiBaseUrl}`
              );
            }
            if (reason === 'register_failed') {
              const statusSuffix = context?.status ? ` (status: ${context.status})` : '';
              return t(
                `Permission is granted, but token registration with the server failed${statusSuffix}. Check API URL, login session, and network.`,
                `Permission is granted, but token registration with the server failed${statusSuffix}. Check API URL, login session, and network.`
              );
            }
            if (reason === 'permission_denied') {
              return t(
                'To receive push notifications, allow notifications for Simbazu in your device settings.',
                'To receive push notifications, allow notifications for Simbazu in your device settings.'
              );
            }
            return t(
              'Push setup failed. Please try again. If it keeps failing, check app logs for [pushTokens] details.',
              'Push setup failed. Please try again. If it keeps failing, check app logs for [pushTokens] details.'
            );
          })();

          setPushFailureMessage(message);
          setNotification('push', false);
          setActiveDialog('push_failed');
        }}
        secondaryLabel={t('Not now', 'Not now')}
        onSecondary={() => setActiveDialog(null)}
        onClose={() => setActiveDialog(null)}
      />

      <StatusDialog
        visible={activeDialog === 'push_processing'}
        variant="loading"
        title={t('Enabling notifications', 'Enabling notifications')}
        message={t('Waiting for your permission…', 'Waiting for your permission…')}
        primaryLabel={t('Hide', 'Hide')}
        onPrimary={() => setActiveDialog(null)}
        onClose={() => setActiveDialog(null)}
      />

      <StatusDialog
        visible={activeDialog === 'push_failed'}
        variant="info"
        title={
          pushFailureReason === 'permission_denied'
            ? t('Enable notifications in Settings', 'Enable notifications in Settings')
            : t('Push setup failed', 'Push setup failed')
        }
        message={
          pushFailureMessage ??
          t(
            'To receive push notifications, allow notifications for Simbazu in your device settings.',
            'To receive push notifications, allow notifications for Simbazu in your device settings.'
          )
        }
        primaryLabel={
          pushFailureReason === 'permission_denied'
            ? t('Open settings', 'Open settings')
            : t('Retry', 'Retry')
        }
        onPrimary={() => {
          if (pushFailureReason === 'permission_denied') {
            Linking.openSettings().catch(() => {});
            setActiveDialog(null);
            return;
          }

          setActiveDialog('push_confirm');
        }}
        secondaryLabel={t('OK', 'OK')}
        onSecondary={() => setActiveDialog(null)}
        onClose={() => setActiveDialog(null)}
      />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.white,
  },
  scroll: {
    flex: 1,
  },
  content: {
    paddingHorizontal: 20,
    paddingTop: 12,
    paddingBottom: 32,
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 20,
  },
  title: {
    fontSize: 18,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  iconButton: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: theme.colors.sand,
    alignItems: 'center',
    justifyContent: 'center',
  },
  card: {
    borderRadius: 20,
    backgroundColor: theme.colors.sand,
    padding: 18,
    gap: 14,
  },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  rowText: {
    fontSize: 13,
    fontWeight: '600',
    color: theme.colors.inkDark,
  },
  toggle: {
    width: 44,
    height: 24,
    borderRadius: 12,
    backgroundColor: theme.colors.blueSoftAlt,
    padding: 3,
    alignItems: 'flex-start',
  },
  toggleActive: {
    backgroundColor: theme.colors.sun,
    alignItems: 'flex-end',
  },
  toggleDot: {
    width: 18,
    height: 18,
    borderRadius: 9,
    backgroundColor: theme.colors.white,
  },
  primaryButton: {
    marginTop: 24,
    backgroundColor: theme.colors.sun,
    borderRadius: 24,
    paddingVertical: 14,
    alignItems: 'center',
  },
  primaryText: {
    color: theme.colors.white,
    fontSize: 14,
    fontWeight: '700',
  },
  debugText: {
    marginTop: 10,
    fontSize: 11,
    color: '#6b7280',
  },
});
