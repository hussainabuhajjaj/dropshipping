import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
import { useOrders } from '@/lib/ordersStore';
import { Skeleton } from '@/src/components/ui/Skeleton';
import { SafeAreaView } from 'react-native-safe-area-context';

export default function HistoryScreen() {
  const { orders, loading, error } = useOrders();

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView style={styles.scroll} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.headerRow}>
          <Pressable style={styles.iconButton} onPress={() => router.back()}>
            <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
          </Pressable>
          <Text style={styles.title}>History</Text>
          <Pressable style={styles.iconButton} onPress={() => router.push('/(tabs)/home')}>
            <Feather name="x" size={16} color={theme.colors.inkDark} />
          </Pressable>
        </View>

        <View style={styles.list}>
          {loading
            ? [0, 1, 2].map((index) => (
                <View key={`sk-${index}`} style={styles.card}>
                  <View style={styles.cardSkeleton}>
                    <Skeleton width="40%" height={12} />
                    <Skeleton width="55%" height={10} style={styles.skeletonGap} />
                  </View>
                  <Skeleton width={60} height={10} />
                </View>
              ))
            : orders.map((order) => (
                <Pressable
                  key={order.number}
                  style={styles.card}
                  onPress={() => router.push(`/orders/${order.number}`)}
                >
                  <View>
                    <Text style={styles.cardTitle}>#{order.number}</Text>
                    <Text style={styles.cardBody}>{order.status}</Text>
                  </View>
                  <Text style={styles.cardDate}>{order.placedAt ?? '—'}</Text>
                </Pressable>
              ))}
          {!loading && orders.length === 0 ? (
            <View style={styles.emptyCard}>
              <Text style={styles.emptyTitle}>No orders yet</Text>
              <Text style={styles.emptyBody}>{error ?? 'Your past orders will show up here.'}</Text>
            </View>
          ) : null}
        </View>
      </ScrollView>
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
    marginBottom: 16,
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
  list: {
    gap: 12,
  },
  card: {
    padding: 14,
    borderRadius: 16,
    backgroundColor: theme.colors.sand,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  cardTitle: {
    fontSize: 14,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  cardBody: {
    marginTop: 6,
    fontSize: 12,
    color: theme.colors.inkDark,
  },
  cardDate: {
    fontSize: 12,
    color: theme.colors.inkDark,
  },
  cardSkeleton: {
    flex: 1,
  },
  skeletonGap: {
    marginTop: 6,
  },
  emptyCard: {
    padding: 16,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: theme.colors.sand,
    backgroundColor: theme.colors.white,
  },
  emptyTitle: {
    fontSize: 13,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  emptyBody: {
    marginTop: 6,
    fontSize: 12,
    color: theme.colors.mutedDark,
  },
});
