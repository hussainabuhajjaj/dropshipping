import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { Image, Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { useOrders } from '@/lib/ordersStore';
import { theme } from '@/src/theme';
import { Skeleton } from '@/src/components/ui/Skeleton';
import { SafeAreaView } from 'react-native-safe-area-context';
import type { Order } from '@/src/types/orders';

const ACTIVE_KEYS = ['received', 'processing', 'dispatched', 'in_transit', 'out_for_delivery'];

const statusBadge = (key: string): { label: string; bg: string; fg: string } => {
  switch (key) {
    case 'received':         return { label: 'Received',         bg: theme.colors.blueSoft,    fg: '#3a5bd9' };
    case 'processing':       return { label: 'Processing',       bg: theme.colors.primarySoft, fg: theme.colors.primaryDark };
    case 'dispatched':       return { label: 'Dispatched',       bg: '#e6f7ee',                fg: theme.colors.green };
    case 'in_transit':       return { label: 'In Transit',       bg: '#e6f7ee',                fg: theme.colors.green };
    case 'out_for_delivery': return { label: 'Out for Delivery', bg: '#e6f7ee',                fg: theme.colors.green };
    default:                 return { label: 'Processing',       bg: theme.colors.primarySoft, fg: theme.colors.primaryDark };
  }
};

export default function ToReceiveScreen() {
  const { orders, loading, error, refreshOrders } = useOrders();
  const items = orders.filter((o: Order) => ACTIVE_KEYS.includes(o.statusKey ?? ''));

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView style={styles.scroll} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.headerRow}>
          <Pressable style={styles.iconButton} onPress={() => router.back()}>
            <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
          </Pressable>
          <Text style={styles.title}>To Receive</Text>
          <Pressable style={styles.iconButton} onPress={refreshOrders}>
            <Feather name="refresh-cw" size={15} color={theme.colors.inkDark} />
          </Pressable>
        </View>

        <View style={styles.list}>
          {loading
            ? [0, 1, 2].map((i) => (
                <View key={`sk-${i}`} style={styles.card}>
                  <Skeleton width={80} height={80} radius={14} />
                  <View style={styles.cardInfo}>
                    <Skeleton width="65%" height={12} />
                    <Skeleton width="45%" height={10} style={styles.skeletonGap} />
                    <Skeleton width={80} height={22} radius={11} style={styles.skeletonGap} />
                  </View>
                </View>
              ))
            : items.map((order: Order) => {
                const firstItem = order.items[0];
                const imageSource = firstItem?.image ?? null;
                const badge = statusBadge(order.statusKey ?? 'processing');
                return (
                  <Pressable
                    key={order.number}
                    style={styles.card}
                    onPress={() => router.push(`/orders/${order.number}`)}
                  >
                    {imageSource ? (
                      <Image source={{ uri: imageSource }} style={styles.cardImage} />
                    ) : (
                      <View style={styles.cardImageFallback}>
                        <Feather name="package" size={24} color={theme.colors.muted} />
                      </View>
                    )}
                    <View style={styles.cardInfo}>
                      <View style={styles.cardTopRow}>
                        <Text style={styles.cardNumber}>#{order.number}</Text>
                        <View style={[styles.badge, { backgroundColor: badge.bg }]}>
                          <Text style={[styles.badgeText, { color: badge.fg }]}>{badge.label}</Text>
                        </View>
                      </View>
                      <Text style={styles.cardTitle} numberOfLines={2}>
                        {firstItem?.name ?? `Order #${order.number}`}
                      </Text>
                      {order.statusExplanation ? (
                        <Text style={styles.cardExplanation} numberOfLines={2}>
                          {order.statusExplanation}
                        </Text>
                      ) : (
                        <Text style={styles.cardDate}>{order.placedAt ?? '—'}</Text>
                      )}
                      <Pressable
                        style={styles.trackButton}
                        onPress={(e) => {
                          e.stopPropagation();
                          router.push(`/orders/track?number=${encodeURIComponent(order.number)}`);
                        }}
                      >
                        <Feather name="map-pin" size={11} color={theme.colors.inkDark} />
                        <Text style={styles.trackText}>Track shipment</Text>
                      </Pressable>
                    </View>
                  </Pressable>
                );
              })}

          {!loading && items.length === 0 && (
            <View style={styles.emptyCard}>
              <Feather name="inbox" size={32} color={theme.colors.mutedSoft} />
              <Text style={styles.emptyTitle}>No active orders</Text>
              <Text style={styles.emptyBody}>{error ?? 'Your in-transit deliveries will appear here.'}</Text>
            </View>
          )}
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
  list: { gap: 12 },
  card: {
    padding: 12,
    borderRadius: 20,
    backgroundColor: theme.colors.white,
    borderWidth: 1,
    borderColor: theme.colors.sand,
    flexDirection: 'row',
    gap: 12,
  },
  cardImage: {
    width: 80,
    height: 80,
    borderRadius: 14,
    backgroundColor: theme.colors.gray200,
  },
  cardImageFallback: {
    width: 80,
    height: 80,
    borderRadius: 14,
    backgroundColor: theme.colors.sand,
    alignItems: 'center',
    justifyContent: 'center',
  },
  cardInfo: { flex: 1 },
  cardTopRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 4,
  },
  cardNumber: { fontSize: 11, color: theme.colors.muted, fontWeight: '600' },
  badge: {
    paddingHorizontal: 7,
    paddingVertical: 2,
    borderRadius: 20,
  },
  badgeText: { fontSize: 10, fontWeight: '600' },
  cardTitle: { fontSize: 13, color: theme.colors.inkDark, fontWeight: '600' },
  cardExplanation: { marginTop: 4, fontSize: 11, color: theme.colors.muted, lineHeight: 15 },
  cardDate: { marginTop: 4, fontSize: 11, color: theme.colors.muted },
  trackButton: {
    marginTop: 10,
    alignSelf: 'flex-start',
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 20,
    backgroundColor: theme.colors.sun,
  },
  trackText: { fontSize: 11, color: theme.colors.inkDark, fontWeight: '600' },
  skeletonGap: { marginTop: 8 },
  emptyCard: {
    alignItems: 'center',
    paddingVertical: 40,
    paddingHorizontal: 16,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: theme.colors.sand,
    gap: 8,
  },
  emptyTitle: { fontSize: 14, fontWeight: '700', color: theme.colors.inkDark },
  emptyBody: { fontSize: 12, color: theme.colors.mutedDark, textAlign: 'center' },
});
