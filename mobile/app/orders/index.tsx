import { Feather } from '@expo/vector-icons';
import { router, type Href } from 'expo-router';
import { Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
import { useOrders } from '@/lib/ordersStore';
import { Skeleton } from '@/src/components/ui/Skeleton';
import { SafeAreaView } from 'react-native-safe-area-context';
import type { Order } from '@/src/types/orders';

const ACTIVE_KEYS = ['received', 'processing', 'dispatched', 'in_transit', 'out_for_delivery'];

const statusBadge = (key: string): { label: string; bg: string; fg: string } => {
  switch (key) {
    case 'received':     return { label: 'Received',        bg: theme.colors.blueSoft,    fg: '#3a5bd9' };
    case 'processing':   return { label: 'Processing',      bg: theme.colors.primarySoft, fg: theme.colors.primaryDark };
    case 'dispatched':   return { label: 'Dispatched',      bg: '#e6f7ee',                fg: theme.colors.green };
    case 'in_transit':   return { label: 'In Transit',      bg: '#e6f7ee',                fg: theme.colors.green };
    case 'out_for_delivery': return { label: 'Out for Delivery', bg: '#e6f7ee',           fg: theme.colors.green };
    case 'delivered':    return { label: 'Delivered',       bg: '#e6f7ee',                fg: theme.colors.green };
    case 'issue_detected': return { label: 'Issue',         bg: theme.colors.dangerSoft,  fg: theme.colors.danger };
    case 'refunded':     return { label: 'Refunded',        bg: theme.colors.dangerSoft,  fg: theme.colors.danger };
    default:             return { label: 'Processing',      bg: theme.colors.primarySoft, fg: theme.colors.primaryDark };
  }
};

function StatusBadge({ statusKey }: { statusKey: string }) {
  const badge = statusBadge(statusKey);
  return (
    <View style={[badgeStyles.pill, { backgroundColor: badge.bg }]}>
      <Text style={[badgeStyles.text, { color: badge.fg }]}>{badge.label}</Text>
    </View>
  );
}

const badgeStyles = StyleSheet.create({
  pill: {
    alignSelf: 'flex-start',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 20,
  },
  text: {
    fontSize: 11,
    fontWeight: '600',
  },
});

export default function MyActivityScreen() {
  const { orders, loading, error, refreshOrders } = useOrders();
  const activeOrders = orders.filter((o) => ACTIVE_KEYS.includes(o.statusKey ?? ''));
  const deliveredOrders = orders.filter((o) => o.statusKey === 'delivered');
  const recentOrders = orders.slice(0, 6);

  const activityLinks: Array<{ label: string; icon: string; count: number; href: Href }> = [
    { label: 'To Receive', icon: 'package',   count: activeOrders.length,   href: '/orders/to-receive' },
    { label: 'To Review',  icon: 'star',      count: deliveredOrders.length, href: '/orders/review-option' },
    { label: 'History',    icon: 'clock',     count: orders.length,          href: '/orders/history' },
  ];

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView
        style={styles.scroll}
        contentContainerStyle={styles.content}
        showsVerticalScrollIndicator={false}
      >
        {/* Header */}
        <View style={styles.headerRow}>
          <Pressable style={styles.iconButton} onPress={() => router.back()}>
            <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
          </Pressable>
          <Text style={styles.title}>My Orders</Text>
          <Pressable style={styles.iconButton} onPress={refreshOrders}>
            <Feather name="refresh-cw" size={15} color={theme.colors.inkDark} />
          </Pressable>
        </View>

        {/* Stat cards */}
        <View style={styles.statsRow}>
          {activityLinks.map((item) => (
            <Pressable key={item.label} style={styles.statCard} onPress={() => router.push(item.href)}>
              <Feather name={item.icon as any} size={18} color={theme.colors.primary} />
              <Text style={styles.statValue}>{item.count}</Text>
              <Text style={styles.statLabel}>{item.label}</Text>
            </Pressable>
          ))}
        </View>

        {/* Track banner */}
        <Pressable style={styles.trackBanner} onPress={() => router.push('/orders/track')}>
          <View style={styles.trackBannerLeft}>
            <Feather name="map-pin" size={18} color={theme.colors.inkDark} />
            <View style={styles.trackBannerText}>
              <Text style={styles.trackBannerTitle}>Track an order</Text>
              <Text style={styles.trackBannerBody}>Get real-time updates on your shipment.</Text>
            </View>
          </View>
          <View style={styles.trackBtn}>
            <Text style={styles.trackBtnText}>Track</Text>
          </View>
        </Pressable>

        {/* Recent orders */}
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Recent orders</Text>
          <View style={styles.orderList}>
            {loading
              ? [0, 1, 2].map((i) => (
                  <View key={`sk-${i}`} style={styles.orderRow}>
                    <View style={styles.orderSkeleton}>
                      <Skeleton width="55%" height={12} />
                      <Skeleton width="35%" height={10} style={styles.skeletonGap} />
                    </View>
                    <Skeleton width={68} height={22} radius={11} />
                  </View>
                ))
              : recentOrders.map((order: Order) => (
                  <Pressable
                    key={order.number}
                    style={styles.orderRow}
                    onPress={() =>
                      router.push({ pathname: '/orders/[number]', params: { number: order.number } })
                    }
                  >
                    <View style={styles.orderMeta}>
                      <Text style={styles.orderNumber}>#{order.number}</Text>
                      <Text style={styles.orderDate}>{order.placedAt ?? '—'}</Text>
                    </View>
                    <View style={styles.orderRight}>
                      <StatusBadge statusKey={order.statusKey ?? 'processing'} />
                      <Feather name="chevron-right" size={15} color={theme.colors.mutedLight} style={styles.chevron} />
                    </View>
                  </Pressable>
                ))}

            {!loading && recentOrders.length === 0 && (
              <View style={styles.emptyCard}>
                <Feather name="shopping-bag" size={28} color={theme.colors.mutedSoft} />
                <Text style={styles.emptyTitle}>No orders yet</Text>
                <Text style={styles.emptyBody}>
                  {error ?? 'Once you place an order, it will appear here.'}
                </Text>
              </View>
            )}
          </View>
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: theme.colors.white },
  scroll: { flex: 1 },
  content: { paddingHorizontal: 20, paddingTop: 12, paddingBottom: 40 },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 20,
  },
  title: { fontSize: 20, fontWeight: '700', color: theme.colors.inkDark },
  iconButton: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: theme.colors.sand,
    alignItems: 'center',
    justifyContent: 'center',
  },
  statsRow: { flexDirection: 'row', gap: 10 },
  statCard: {
    flex: 1,
    paddingVertical: 14,
    borderRadius: 20,
    backgroundColor: theme.colors.white,
    alignItems: 'center',
    gap: 4,
    borderWidth: 1,
    borderColor: theme.colors.sand,
  },
  statValue: { fontSize: 17, fontWeight: '700', color: theme.colors.inkDark },
  statLabel: { fontSize: 11, color: theme.colors.muted, fontWeight: '500' },
  trackBanner: {
    marginTop: 16,
    padding: 14,
    borderRadius: 20,
    backgroundColor: theme.colors.sand,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  trackBannerLeft: { flexDirection: 'row', alignItems: 'center', gap: 10, flex: 1 },
  trackBannerText: { flex: 1 },
  trackBannerTitle: { fontSize: 13, fontWeight: '700', color: theme.colors.inkDark },
  trackBannerBody: { fontSize: 11, color: theme.colors.mutedDark, marginTop: 2 },
  trackBtn: {
    paddingHorizontal: 14,
    paddingVertical: 7,
    borderRadius: 20,
    backgroundColor: theme.colors.sun,
  },
  trackBtnText: { fontSize: 12, fontWeight: '700', color: theme.colors.inkDark },
  section: { marginTop: 24 },
  sectionTitle: { fontSize: 15, fontWeight: '700', color: theme.colors.inkDark, marginBottom: 12 },
  orderList: { gap: 10 },
  orderRow: {
    paddingHorizontal: 14,
    paddingVertical: 12,
    borderRadius: 18,
    backgroundColor: theme.colors.white,
    borderWidth: 1,
    borderColor: theme.colors.sand,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  orderMeta: { flex: 1 },
  orderNumber: { fontSize: 13, fontWeight: '700', color: theme.colors.inkDark },
  orderDate: { marginTop: 3, fontSize: 11, color: theme.colors.muted },
  orderRight: { flexDirection: 'row', alignItems: 'center' },
  chevron: { marginLeft: 6 },
  orderSkeleton: { flex: 1 },
  skeletonGap: { marginTop: 6 },
  emptyCard: {
    alignItems: 'center',
    paddingVertical: 32,
    paddingHorizontal: 16,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: theme.colors.sand,
    gap: 8,
  },
  emptyTitle: { fontSize: 14, fontWeight: '700', color: theme.colors.inkDark },
  emptyBody: { fontSize: 12, color: theme.colors.mutedDark, textAlign: 'center' },
});
