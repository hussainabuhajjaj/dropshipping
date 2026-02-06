import { Feather } from '@expo/vector-icons';
import { router, useLocalSearchParams } from 'expo-router';
import { Pressable, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { useEffect, useState } from 'react';
import { useOrders } from '@/lib/ordersStore';
import { fetchOrderDetail } from '@/src/api/orders';
import type { Order } from '@/src/types/orders';
import { theme } from '@/src/theme';
import { Skeleton } from '@/src/components/ui/Skeleton';
import { SafeAreaView } from 'react-native-safe-area-context';
export default function PaymentHistoryDetailsScreen() {
  const params = useLocalSearchParams();
  const number = typeof params.number === 'string' ? params.number : '';
  const { getOrderByNumber, updateOrder } = useOrders();
  const [order, setOrder] = useState<Order | null>(() => getOrderByNumber(number) ?? null);
  const [loading, setLoading] = useState(() => !getOrderByNumber(number));
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let active = true;
    if (!number) {
      setLoading(false);
      setError('Payment not found.');
      return;
    }

    const local = getOrderByNumber(number);
    if (local) {
      setOrder(local);
      setError(null);
      setLoading(false);
      return () => {
        active = false;
      };
    }

    setLoading(true);
    fetchOrderDetail(number)
      .then((payload) => {
        if (!active) return;
        setOrder(payload);
        updateOrder(number, payload);
      })
      .catch((err: any) => {
        if (!active) return;
        setError(err?.message ?? 'Payment not found.');
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => {
      active = false;
    };
  }, [number, getOrderByNumber, updateOrder]);

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.headerRow}>
        <Pressable style={styles.iconButton} onPress={() => router.back()}>
          <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
        </Pressable>
        <Text style={styles.title}>Payment details</Text>
        <Pressable style={styles.iconButton} onPress={() => router.push('/(tabs)/home')}>
          <Feather name="x" size={16} color={theme.colors.inkDark} />
        </Pressable>
      </View>

      {loading ? (
        <View style={styles.card}>
          <Skeleton width="50%" height={12} />
          <Skeleton width="60%" height={10} style={styles.skeletonGap} />
          {[0, 1, 2].map((index) => (
            <View key={`sk-${index}`} style={styles.row}>
              <Skeleton width={70} height={10} />
              <Skeleton width={80} height={10} />
            </View>
          ))}
          <View style={styles.divider} />
          <View style={styles.row}>
            <Skeleton width={50} height={12} />
            <Skeleton width={80} height={12} />
          </View>
        </View>
      ) : order ? (
        <>
          <View style={styles.card}>
            <Text style={styles.cardTitle}>Order #{order.number}</Text>
            <Text style={styles.cardSub}>{order.status} • {order.placedAt ?? '—'}</Text>
            <View style={styles.row}>
              <Text style={styles.label}>Method</Text>
              <Text style={styles.value}>Korapay</Text>
            </View>
            <View style={styles.row}>
              <Text style={styles.label}>Subtotal</Text>
              <Text style={styles.value}>${order.total.toFixed(2)}</Text>
            </View>
            <View style={styles.row}>
              <Text style={styles.label}>Shipping</Text>
              <Text style={styles.value}>$0.00</Text>
            </View>
            <View style={styles.divider} />
            <View style={styles.row}>
              <Text style={styles.totalLabel}>Total</Text>
              <Text style={styles.totalValue}>${order.total.toFixed(2)}</Text>
            </View>
          </View>

          <Pressable style={styles.primaryButton} onPress={() => router.push(`/orders/${order.number}`)}>
            <Text style={styles.primaryText}>View order</Text>
          </Pressable>
        </>
      ) : (
        <View style={styles.emptyCard}>
          <Text style={styles.emptyTitle}>Payment not found</Text>
          <Text style={styles.emptyBody}>{error ?? 'We could not locate that payment.'}</Text>
        </View>
      )}
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
    padding: 18,
  },
  cardTitle: {
    fontSize: 15,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  cardSub: {
    marginTop: 4,
    fontSize: 11,
    color: theme.colors.mutedDark,
  },
  row: {
    marginTop: 12,
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  label: {
    fontSize: 12,
    color: theme.colors.mutedDark,
  },
  value: {
    fontSize: 12,
    fontWeight: '600',
    color: theme.colors.inkDark,
  },
  divider: {
    height: 1,
    backgroundColor: '#e4e6ed',
    marginTop: 12,
  },
  totalLabel: {
    fontSize: 13,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  totalValue: {
    fontSize: 13,
    fontWeight: '700',
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
    fontWeight: '700',
    color: theme.colors.white,
  },
  skeletonGap: {
    marginTop: 8,
  },
  emptyCard: {
    marginTop: 20,
    padding: 16,
    borderRadius: 18,
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
