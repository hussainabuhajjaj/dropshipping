import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
const steps = [
  { title: 'Order confirmed', time: '08:24 AM' },
  { title: 'Package shipped', time: 'Yesterday' },
  { title: 'In transit', time: 'Today' },
  { title: 'Out for delivery', time: 'Tomorrow' },
];

export default function ToReceiveProgressScreen() {
  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
      <View style={styles.headerRow}>
        <Pressable style={styles.iconButton} onPress={() => router.back()}>
          <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
        </Pressable>
        <Text style={styles.title}>Tracking</Text>
        <Pressable style={styles.iconButton} onPress={() => router.push('/(tabs)/home')}>
          <Feather name="x" size={16} color={theme.colors.inkDark} />
        </Pressable>
      </View>

      <View style={styles.timeline}>
        {steps.map((step, index) => (
          <View key={step.title} style={styles.stepRow}>
            <View style={styles.stepIndicator}>
              <View style={[styles.dot, index <= 2 && styles.dotActive]} />
              {index < steps.length - 1 ? <View style={styles.line} /> : null}
            </View>
            <View style={styles.stepInfo}>
              <Text style={styles.stepTitle}>{step.title}</Text>
              <Text style={styles.stepTime}>{step.time}</Text>
            </View>
          </View>
        ))}
      </View>

      <Pressable style={styles.primaryButton} onPress={() => router.push('/orders/to-receive-failed')}>
        <Text style={styles.primaryText}>Report issue</Text>
      </Pressable>
    </ScrollView>
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
    marginBottom: 18,
  },
  title: {
    fontSize: 20,
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
  timeline: {
    gap: 18,
  },
  stepRow: {
    flexDirection: 'row',
    gap: 14,
  },
  stepIndicator: {
    alignItems: 'center',
  },
  dot: {
    width: 14,
    height: 14,
    borderRadius: 7,
    backgroundColor: theme.colors.sand,
  },
  dotActive: {
    backgroundColor: theme.colors.sun,
  },
  line: {
    width: 2,
    flex: 1,
    backgroundColor: theme.colors.sand,
    marginTop: 4,
  },
  stepInfo: {
    flex: 1,
  },
  stepTitle: {
    fontSize: 14,
    fontWeight: '600',
    color: theme.colors.inkDark,
  },
  stepTime: {
    marginTop: 6,
    fontSize: 12,
    color: theme.colors.inkDark,
  },
  primaryButton: {
    marginTop: 30,
    backgroundColor: theme.colors.sun,
    borderRadius: 24,
    paddingVertical: 14,
    alignItems: 'center',
  },
  primaryText: {
    fontSize: 14,
    color: theme.colors.gray200,
    fontWeight: '700',
  },
});

