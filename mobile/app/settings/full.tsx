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
import { clearExpoPushToken, syncExpoPushToken } from '@/src/lib/pushTokens';

export default function SettingsFullScreen() {
  const { state, setNotification } = usePreferences();
  const [activeDialog, setActiveDialog] = useState<null | 'push_confirm' | 'push_processing' | 'push_failed'>(null);
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
          const token = await syncExpoPushToken();
          setNotification('push', true);
          setActiveDialog(token ? null : 'push_failed');
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
        title={t('Enable notifications in Settings', 'Enable notifications in Settings')}
        message={t(
          'To receive push notifications, allow notifications for Simbazu in your device settings.',
          'To receive push notifications, allow notifications for Simbazu in your device settings.'
        )}
        primaryLabel={t('Open settings', 'Open settings')}
        onPrimary={() => {
          Linking.openSettings().catch(() => {});
          setActiveDialog(null);
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
});
