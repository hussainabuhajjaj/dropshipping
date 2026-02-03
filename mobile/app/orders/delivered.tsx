import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { Image, Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { useOrders } from '@/lib/ordersStore';
import { theme } from '@/src/theme';
import { Skeleton } from '@/src/components/ui/Skeleton';
export default function DeliveredScreen() {
  const { orders, loading, error } = useOrders();
  const order = orders.find((item) => item.status === 'Delivered') ?? orders[0];
  const firstItem = order?.items[0];
  const imageSource = firstItem?.image ?? null;

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
      <View style={styles.headerRow}>
        <Pressable style={styles.iconButton} onPress={() => router.back()}>
          <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
        </Pressable>
        <Text style={styles.title}>Delivered</Text>
        <Pressable style={styles.iconButton} onPress={() => router.push('/(tabs)/home')}>
          <Feather name="x" size={16} color={theme.colors.inkDark} />
        </Pressable>
      </View>

      {loading ? (
        <View style={styles.card}>
          <Skeleton width={90} height={90} radius={14} />
          <View style={styles.cardInfo}>
            <Skeleton width="70%" height={12} />
            <Skeleton width="45%" height={10} style={styles.skeletonGap} />
          </View>
        </View>
      ) : order ? (
        <>
          <View style={styles.card}>
            {imageSource ? (
              <Image source={{ uri: imageSource }} style={styles.cardImage} />
            ) : (
              <View style={styles.cardImageFallback}>
                <Text style={styles.cardImageText}>{order.number.slice(0, 2).toUpperCase()}</Text>
              </View>
            )}
            <View style={styles.cardInfo}>
              <Text style={styles.cardTitle}>{firstItem?.name ?? `Order #${order.number}`}</Text>
              <Text style={styles.cardBody}>Delivered • Today</Text>
            </View>
          </View>

          <Pressable style={styles.primaryButton} onPress={() => router.push('/orders/review')}>
            <Text style={styles.primaryText}>Leave a review</Text>
          </Pressable>
          <Pressable style={styles.secondaryButton} onPress={() => router.push(`/orders/${order.number}`)}>
            <Text style={styles.secondaryText}>View order</Text>
          </Pressable>
        </>
      ) : (
        <View style={styles.emptyCard}>
          <Text style={styles.emptyTitle}>No deliveries yet</Text>
          <Text style={styles.emptyBody}>{error ?? 'Delivered orders will appear here.'}</Text>
        </View>
      )}
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
  card: {
    padding: 12,
    borderRadius: 18,
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
    justifyContent: 'center',
  },
  cardTitle: {
    fontSize: 14,
    fontWeight: '600',
    color: theme.colors.inkDark,
  },
  cardBody: {
    marginTop: 6,
    fontSize: 12,
    color: theme.colors.inkDark,
  },
  primaryButton: {
    marginTop: 24,
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
  secondaryButton: {
    marginTop: 12,
    backgroundColor: theme.colors.sand,
    borderRadius: 24,
    paddingVertical: 14,
    alignItems: 'center',
  },
  secondaryText: {
    fontSize: 14,
    color: theme.colors.inkDark,
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
