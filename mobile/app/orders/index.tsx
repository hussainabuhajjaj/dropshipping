import { Feather } from '@expo/vector-icons';
import { router, type Href } from 'expo-router';
import { Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
import { useOrders } from '@/lib/ordersStore';
import { Skeleton } from '@/src/components/ui/Skeleton';
import { SafeAreaView } from 'react-native-safe-area-context';

export default function MyActivityScreen() {
  const { orders, loading, error } = useOrders();
  const toReceiveCount = orders.filter((order) => order.status !== 'Delivered').length;
  const deliveredCount = orders.filter((order) => order.status === 'Delivered').length;
  const recentOrders = orders.slice(0, 6);
  const activityLinks: Array<{ label: string; count: number; href: Href }> = [
    { label: 'To Receive', count: toReceiveCount, href: '/orders/to-receive' },
    { label: 'To Review', count: deliveredCount, href: '/orders/review-option' },
    { label: 'History', count: orders.length, href: '/orders/history' },
  ];

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView style={styles.scroll} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.headerRow}>
          <Pressable style={styles.iconButton} onPress={() => router.back()}>
            <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
          </Pressable>
          <Text style={styles.title}>My Activity</Text>
          <Pressable style={styles.iconButton} onPress={() => router.push('/orders/history')}>
            <Feather name="clock" size={16} color={theme.colors.inkDark} />
          </Pressable>
        </View>

        <View style={styles.statsRow}>
          {activityLinks.map((item) => (
            <Pressable key={item.label} style={styles.statCard} onPress={() => router.push(item.href)}>
              <Text style={styles.statValue}>{item.count}</Text>
              <Text style={styles.statLabel}>{item.label}</Text>
            </Pressable>
          ))}
        </View>

        <View style={styles.card}>
          <View>
            <Text style={styles.cardTitle}>Track an order</Text>
            <Text style={styles.cardBody}>Get real-time updates on your shipments.</Text>
          </View>
          <Pressable style={styles.primaryButton} onPress={() => router.push('/orders/track')}>
            <Text style={styles.primaryText}>Track</Text>
          </Pressable>
        </View>

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Recent orders</Text>
          <View style={styles.orderList}>
            {loading
              ? [0, 1, 2].map((index) => (
                  <View key={`sk-${index}`} style={styles.orderRow}>
                    <View style={styles.orderSkeleton}>
                      <Skeleton width="60%" height={12} />
                      <Skeleton width="40%" height={10} style={styles.skeletonGap} />
                    </View>
                    <Skeleton width={16} height={16} radius={8} />
                  </View>
                ))
              : recentOrders.map((order) => (
                  <Pressable
                    key={order.number}
                    style={styles.orderRow}
                    onPress={() =>
                      router.push({
                        pathname: '/orders/[number]',
                        params: { number: order.number },
                      })
                    }
                  >
                    <View>
                      <Text style={styles.orderTitle}>Order #{order.number}</Text>
                      <Text style={styles.orderStatus}>{order.status}</Text>
                    </View>
                    <Feather name="chevron-right" size={16} color={theme.colors.inkDark} />
                  </Pressable>
                ))}
            {!loading && recentOrders.length === 0 ? (
              <View style={styles.emptyCard}>
                <Text style={styles.emptyTitle}>No orders yet</Text>
                <Text style={styles.emptyBody}>
                  {error ?? 'Once you place an order, it will appear here.'}
                </Text>
              </View>
            ) : null}
          </View>
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
  statsRow: {
    flexDirection: 'row',
    gap: 12,
  },
  statCard: {
    flex: 1,
    paddingVertical: 14,
    borderRadius: 16,
    backgroundColor: theme.colors.sand,
    alignItems: 'center',
  },
  statValue: {
    fontSize: 16,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  statLabel: {
    marginTop: 4,
    fontSize: 12,
    color: theme.colors.inkDark,
  },
  card: {
    marginTop: 18,
    padding: 16,
    borderRadius: 18,
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
  primaryButton: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 16,
    backgroundColor: theme.colors.sun,
  },
  primaryText: {
    fontSize: 12,
    color: theme.colors.gray200,
    fontWeight: '700',
  },
  section: {
    marginTop: 22,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: theme.colors.inkDark,
    marginBottom: 12,
  },
  orderList: {
    gap: 12,
  },
  orderRow: {
    padding: 14,
    borderRadius: 16,
    backgroundColor: theme.colors.white,
    borderWidth: 1,
    borderColor: theme.colors.gray250,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  orderTitle: {
    fontSize: 13,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  orderStatus: {
    marginTop: 6,
    fontSize: 12,
    color: theme.colors.inkDark,
  },
  orderSkeleton: {
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
