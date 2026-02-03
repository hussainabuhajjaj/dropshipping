import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { Image, Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { useOrders } from '@/lib/ordersStore';
import { theme } from '@/src/theme';
import { Skeleton } from '@/src/components/ui/Skeleton';
export default function ToReceiveScreen() {
  const { orders, loading, error } = useOrders();
  const items = orders.filter((order) => order.status !== 'Delivered');

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
      <View style={styles.headerRow}>
        <Pressable style={styles.iconButton} onPress={() => router.back()}>
          <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
        </Pressable>
        <Text style={styles.title}>To Receive</Text>
        <Pressable style={styles.iconButton} onPress={() => router.push('/orders/history')}>
          <Feather name="clock" size={16} color={theme.colors.inkDark} />
        </Pressable>
      </View>

      <View style={styles.list}>
        {loading
          ? [0, 1].map((index) => (
              <View key={`sk-${index}`} style={styles.card}>
                <Skeleton width={90} height={90} radius={14} />
                <View style={styles.cardInfo}>
                  <Skeleton width="70%" height={12} />
                  <Skeleton width="50%" height={10} style={styles.skeletonGap} />
                  <Skeleton width={60} height={24} radius={12} style={styles.skeletonGap} />
                </View>
              </View>
            ))
          : items.map((order) => {
              const firstItem = order.items[0];
              const imageSource = firstItem?.image ?? null;
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
                      <Text style={styles.cardImageText}>{order.number.slice(0, 2).toUpperCase()}</Text>
                    </View>
                  )}
                  <View style={styles.cardInfo}>
                    <Text style={styles.cardTitle} numberOfLines={2}>
                      {firstItem?.name ?? `Order #${order.number}`}
                    </Text>
                    <Text style={styles.cardBody}>Estimated delivery: 7-14 days</Text>
                    <Pressable
                      style={styles.trackButton}
                      onPress={(event) => {
                        event.stopPropagation();
                        router.push(`/orders/track?number=${encodeURIComponent(order.number)}`);
                      }}
                    >
                      <Text style={styles.trackText}>Track</Text>
                    </Pressable>
                  </View>
                </Pressable>
              );
            })}
        {!loading && items.length === 0 ? (
          <View style={styles.emptyCard}>
            <Text style={styles.emptyTitle}>No orders in transit</Text>
            <Text style={styles.emptyBody}>{error ?? 'Your active deliveries will appear here.'}</Text>
          </View>
        ) : null}
      </View>
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
    padding: 12,
    borderRadius: 16,
    backgroundColor: theme.colors.sand,
    flexDirection: 'row',
    gap: 12,
  },
  cardImage: {
    width: 90,
    height: 90,
    borderRadius: 14,
    backgroundColor: theme.colors.gray200,
  },
  cardImageFallback: {
    width: 90,
    height: 90,
    borderRadius: 14,
    backgroundColor: theme.colors.gray200,
    alignItems: 'center',
    justifyContent: 'center',
  },
  cardImageText: {
    fontSize: 16,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  cardInfo: {
    flex: 1,
  },
  cardTitle: {
    fontSize: 13,
    color: theme.colors.inkDark,
    fontWeight: '600',
  },
  cardBody: {
    marginTop: 6,
    fontSize: 12,
    color: theme.colors.inkDark,
  },
  trackButton: {
    marginTop: 10,
    alignSelf: 'flex-start',
    paddingHorizontal: 14,
    paddingVertical: 6,
    borderRadius: 14,
    backgroundColor: theme.colors.sun,
  },
  trackText: {
    fontSize: 12,
    color: theme.colors.gray200,
    fontWeight: '600',
  },
  skeletonGap: {
    marginTop: 8,
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
