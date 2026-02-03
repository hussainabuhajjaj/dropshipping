import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
import { usePreferences } from '@/src/store/preferencesStore';
import { StatusDialog } from '@/src/overlays/StatusDialog';

export default function SettingsFullScreen() {
  const { state, setNotification } = usePreferences();
  const [notice, setNotice] = useState(false);

  const toggles = [
    { id: 'push' as const, label: 'Push notifications', active: state.notifications.push },
    { id: 'email' as const, label: 'Email updates', active: state.notifications.email },
    { id: 'sms' as const, label: 'SMS alerts', active: state.notifications.sms },
  ];

  return (
    <>
      <ScrollView style={styles.container} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
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
          <Text style={styles.title}>Preferences</Text>
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
                  setNotification(item.id, next);
                  if (item.id === 'push' && next) {
                    setNotice(true);
                  }
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
          <Text style={styles.primaryText}>Save</Text>
        </Pressable>
      </ScrollView>

      <StatusDialog
        visible={notice}
        variant="info"
        title="Push notifications"
        message="Notifications will ask for permission when the feature is enabled. You can change notification permissions in your device settings anytime."
        primaryLabel="OK"
        onPrimary={() => setNotice(false)}
        onClose={() => setNotice(false)}
      />
    </>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.white,
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
