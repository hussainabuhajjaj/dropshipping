import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { Pressable, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
const reminders = [
  { id: 'rem-1', label: '2 days before expiry', active: true },
  { id: 'rem-2', label: '1 day before expiry', active: false },
  { id: 'rem-3', label: 'On the expiry day', active: true },
];

export default function VoucherReminderScreen() {
  return (
    <View style={styles.container}>
      <View style={styles.headerRow}>
        <Pressable style={styles.iconButton} onPress={() => router.back()}>
          <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
        </Pressable>
        <Text style={styles.title}>Voucher reminder</Text>
        <Pressable style={styles.iconButton} onPress={() => router.push('/(tabs)/home')}>
          <Feather name="x" size={16} color={theme.colors.inkDark} />
        </Pressable>
      </View>

      <View style={styles.card}>
        {reminders.map((item) => (
          <View key={item.id} style={styles.row}>
            <Text style={styles.rowText}>{item.label}</Text>
            <View style={[styles.toggle, item.active ? styles.toggleActive : null]}>
              <View style={[styles.toggleDot, item.active ? styles.toggleDotActive : null]} />
            </View>
          </View>
        ))}
      </View>

      <Pressable style={styles.primaryButton} onPress={() => router.push('/rewards')}>
        <Text style={styles.primaryText}>Save reminder</Text>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.white,
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
  toggleDotActive: {
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

