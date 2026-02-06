import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { Pressable, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
import { SafeAreaView } from 'react-native-safe-area-context';
const ratings = [1, 2, 3, 4, 5];

export default function RateServiceScreen() {
  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.headerRow}>
        <Pressable style={styles.iconButton} onPress={() => router.back()}>
          <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
        </Pressable>
        <Text style={styles.title}>Rate our service</Text>
        <Pressable style={styles.iconButton} onPress={() => router.push('/(tabs)/home')}>
          <Feather name="x" size={16} color={theme.colors.inkDark} />
        </Pressable>
      </View>

      <View style={styles.card}>
        <Text style={styles.cardTitle}>How was your experience?</Text>
        <Text style={styles.cardBody}>Your feedback helps us improve the shopping journey.</Text>
        <View style={styles.starRow}>
          {ratings.map((rating) => (
            <Pressable key={rating} style={styles.starButton}>
              <Feather name="star" size={22} color={rating <= 4 ? theme.colors.warning : '#d3d6de'} />
            </Pressable>
          ))}
        </View>
        <Pressable style={styles.primaryButton} onPress={() => router.push('/rewards')}>
          <Text style={styles.primaryText}>Submit rating</Text>
        </Pressable>
      </View>

      <Pressable style={styles.secondaryButton} onPress={() => router.back()}>
        <Text style={styles.secondaryText}>Maybe later</Text>
      </Pressable>
    </SafeAreaView>
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
    padding: 20,
  },
  cardTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  cardBody: {
    marginTop: 8,
    fontSize: 12,
    color: theme.colors.mutedDark,
  },
  starRow: {
    marginTop: 18,
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  starButton: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: theme.colors.white,
    alignItems: 'center',
    justifyContent: 'center',
  },
  primaryButton: {
    marginTop: 20,
    backgroundColor: theme.colors.sun,
    borderRadius: 22,
    paddingVertical: 12,
    alignItems: 'center',
  },
  primaryText: {
    fontSize: 13,
    fontWeight: '700',
    color: theme.colors.white,
  },
  secondaryButton: {
    marginTop: 18,
    alignItems: 'center',
  },
  secondaryText: {
    fontSize: 13,
    color: theme.colors.mutedDark,
    fontWeight: '600',
  },
});
