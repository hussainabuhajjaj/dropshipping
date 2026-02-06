import { Feather } from '@expo/vector-icons';
import { router, useLocalSearchParams } from 'expo-router';
import { useEffect, useState } from 'react';
import { Image, Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { SafeAreaView } from 'react-native-safe-area-context';
import { fetchOrderDetail } from '@/src/api/orders';
import type { Order } from '@/src/types/orders';
import { useOrders } from '@/lib/ordersStore';
import { usePreferences } from '@/src/store/preferencesStore';
import { formatCurrency } from '@/src/lib/formatCurrency';
import { theme } from '@/src/theme';
import { Skeleton } from '@/src/components/ui/Skeleton';
export default function OrderDetailScreen() {
  const params = useLocalSearchParams();
  const number = typeof params.number === 'string' ? params.number : '';
  const { getOrderByNumber, updateOrder } = useOrders();
  const { state } = usePreferences();
  const [order, setOrder] = useState<Order | null>(() => getOrderByNumber(number) ?? null);
  const [loading, setLoading] = useState(() => !getOrderByNumber(number));
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let active = true;
    if (!number) {
      setLoading(false);
      setError('Order not found.');
      return;
    }

    const local = getOrderByNumber(number);
    if (local) {
      setOrder(local);
      const needsDetail = local.items.length === 0;
      if (!needsDetail) {
        setError(null);
        setLoading(false);
        return () => {
          active = false;
        };
      }
    }

    setLoading(true);
    setError(null);
    fetchOrderDetail(number)
      .then((payload) => {
        if (!active) return;
        setOrder(payload);
        updateOrder(number, payload);
      })
      .catch((err: any) => {
        if (!active) return;
        setError(err?.message ?? 'Unable to load order.');
      })
      .finally(() => {
        if (active) {
          setLoading(false);
        }
      });

    return () => {
      active = false;
    };
  }, [number, getOrderByNumber, updateOrder]);

  if (loading) {
    return (
      <SafeAreaView style={styles.safeArea}>
        <View style={styles.container}>
          <View style={styles.headerRow}>
            <Pressable style={styles.iconButton} onPress={() => router.back()}>
              <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
            </Pressable>
            <Text style={styles.title}>Order</Text>
            <Pressable style={styles.iconButton} onPress={() => router.push('/(tabs)/home')}>
              <Feather name="x" size={16} color={theme.colors.inkDark} />
            </Pressable>
          </View>
          <View style={styles.skeletonStack}>
            <View style={styles.card}>
              <Skeleton width="40%" height={12} />
              <Skeleton width="60%" height={16} style={styles.skeletonGap} />
              <Skeleton width="35%" height={10} style={styles.skeletonGap} />
            </View>
            <View style={styles.card}>
              <Skeleton width="45%" height={12} />
              <Skeleton width="70%" height={16} style={styles.skeletonGap} />
            </View>
            <View style={styles.card}>
              <Skeleton width="30%" height={12} />
              <View style={styles.itemList}>
                {[0, 1].map((index) => (
                  <View key={`sk-${index}`} style={styles.itemRow}>
                    <Skeleton width={46} height={46} radius={12} />
                    <View style={styles.itemInfo}>
                      <Skeleton width="80%" height={10} />
                      <Skeleton width="40%" height={10} style={styles.skeletonGap} />
                    </View>
                    <Skeleton width={40} height={10} />
                  </View>
                ))}
              </View>
            </View>
          </View>
        </View>
      </SafeAreaView>
    );
  }

  if (!order) {
    return (
      <SafeAreaView style={styles.safeArea}>
        <View style={styles.container}>
          <View style={styles.headerRow}>
            <Pressable style={styles.iconButton} onPress={() => router.back()}>
              <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
            </Pressable>
            <Text style={styles.title}>Order</Text>
            <Pressable style={styles.iconButton} onPress={() => router.push('/(tabs)/home')}>
              <Feather name="x" size={16} color={theme.colors.inkDark} />
            </Pressable>
          </View>
          <Text style={styles.subtitle}>Order not found</Text>
          <Text style={styles.bodyText}>{error ?? 'We could not locate that order.'}</Text>
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.safeArea}>
      <ScrollView style={styles.container} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.headerRow}>
          <Pressable style={styles.iconButton} onPress={() => router.back()}>
            <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
          </Pressable>
          <Text style={styles.title}>Order #{number}</Text>
          <Pressable style={styles.iconButton} onPress={() => router.push('/(tabs)/home')}>
            <Feather name="x" size={16} color={theme.colors.inkDark} />
          </Pressable>
        </View>
        <Text style={styles.subtitle}>Shipment status, tracking, and items.</Text>
        <View style={styles.card}>
          <Text style={styles.cardTitle}>Status</Text>
          <Text style={styles.cardValue}>{order.status}</Text>
          <Text style={styles.cardMeta}>Placed {order.placedAt ?? '—'}</Text>
        </View>
        <View style={styles.card}>
          <Text style={styles.cardTitle}>Tracking</Text>
          <Text style={styles.cardValue}>{order.tracking[0]?.status ?? 'Updates will appear here.'}</Text>
        </View>
        <View style={styles.card}>
          <Text style={styles.cardTitle}>Items</Text>
          <View style={styles.itemList}>
            {order.items.map((item) => (
              <View key={item.id} style={styles.itemRow}>
                {item.image ? (
                  <Image source={{ uri: item.image }} style={styles.itemImage} />
                ) : (
                  <View style={styles.itemImageFallback}>
                    <Text style={styles.itemImageText}>{item.name.slice(0, 1).toUpperCase()}</Text>
                  </View>
                )}
                <View style={styles.itemInfo}>
                  <Text style={styles.itemName}>{item.name}</Text>
                  <Text style={styles.itemMeta}>Qty {item.quantity}</Text>
                </View>
                <Text style={styles.itemPrice}>{formatCurrency(item.price, state.currency, state.currency)}</Text>
              </View>
            ))}
          </View>
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: theme.colors.white,
  },
  container: {
    flex: 1,
    backgroundColor: theme.colors.white,
    paddingHorizontal: 20,
    paddingTop: 12,
    paddingBottom: 32,
  },
  content: {
    paddingBottom: 32,
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 12,
  },
  iconButton: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: theme.colors.sand,
    alignItems: 'center',
    justifyContent: 'center',
  },
  title: {
    fontSize: 18,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  skeletonStack: {
    gap: 12,
    marginTop: 12,
  },
  skeletonGap: {
    marginTop: 8,
  },
  subtitle: {
    fontSize: 13,
    color: theme.colors.mutedDark,
    marginBottom: 18,
  },
  bodyText: {
    fontSize: 13,
    color: theme.colors.mutedDark,
  },
  card: {
    backgroundColor: theme.colors.white,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: theme.colors.sand,
    padding: 14,
    marginBottom: 12,
  },
  cardTitle: {
    fontSize: 12,
    color: theme.colors.mutedDark,
  },
  cardValue: {
    fontSize: 14,
    fontWeight: '700',
    color: theme.colors.inkDark,
    marginTop: 4,
  },
  cardMeta: {
    fontSize: 11,
    color: theme.colors.mutedDark,
    marginTop: 4,
  },
  itemList: {
    marginTop: 12,
    gap: 12,
  },
  itemRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  itemImage: {
    width: 46,
    height: 46,
    borderRadius: 12,
  },
  itemImageFallback: {
    width: 46,
    height: 46,
    borderRadius: 12,
    backgroundColor: theme.colors.sand,
    alignItems: 'center',
    justifyContent: 'center',
  },
  itemImageText: {
    fontSize: 16,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  itemInfo: {
    flex: 1,
  },
  itemName: {
    fontSize: 12,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  itemMeta: {
    fontSize: 11,
    color: theme.colors.mutedDark,
    marginTop: 2,
  },
  itemPrice: {
    fontSize: 12,
    fontWeight: '700',
    color: theme.colors.sun,
  },
});
