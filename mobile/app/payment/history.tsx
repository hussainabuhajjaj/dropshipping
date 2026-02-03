import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
import { useOrders } from '@/lib/ordersStore';
import { Skeleton } from '@/src/components/ui/Skeleton';

export default function PaymentHistoryScreen() {
  const { orders, loading, error } = useOrders();
  const payments = orders.map((order) => ({
    id: `pay-${order.number}`,
    title: `Order #${order.number}`,
    date: order.placedAt ?? '—',
    amount: `$${order.total.toFixed(2)}`,
    status: order.status,
    number: order.number,
  }));

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
      <View style={styles.headerRow}>
        <Pressable style={styles.iconButton} onPress={() => router.back()}>
          <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
        </Pressable>
        <Text style={styles.title}>Payment history</Text>
        <Pressable style={styles.iconButton} onPress={() => router.push('/(tabs)/home')}>
          <Feather name="x" size={16} color={theme.colors.inkDark} />
        </Pressable>
      </View>

      <View style={styles.list}>
        {loading
          ? [0, 1, 2].map((index) => (
              <View key={`sk-${index}`} style={styles.card}>
                <View style={styles.cardBody}>
                  <Skeleton width="60%" height={12} />
                  <Skeleton width="40%" height={10} style={styles.skeletonGap} />
                </View>
                <View style={styles.amountGroup}>
                  <Skeleton width={50} height={12} />
                  <Skeleton width={40} height={10} style={styles.skeletonGap} />
                </View>
              </View>
            ))
          : payments.map((payment) => (
              <Pressable
                key={payment.id}
                style={styles.card}
                onPress={() => router.push(`/payment/history-details?number=${encodeURIComponent(payment.number)}`)}
              >
                <View style={styles.cardBody}>
                  <Text style={styles.cardTitle}>{payment.title}</Text>
                  <Text style={styles.cardSub}>{payment.date}</Text>
                </View>
                <View style={styles.amountGroup}>
                  <Text style={styles.amountText}>{payment.amount}</Text>
                  <Text style={styles.statusText}>{payment.status}</Text>
                </View>
              </Pressable>
            ))}
        {!loading && payments.length === 0 ? (
          <View style={styles.emptyCard}>
            <Text style={styles.emptyTitle}>No payments yet</Text>
            <Text style={styles.emptyBody}>{error ?? 'Completed payments will appear here.'}</Text>
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
  list: {
    gap: 12,
  },
  card: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: 14,
    borderRadius: 18,
    backgroundColor: theme.colors.sand,
  },
  cardBody: {
    flex: 1,
  },
  cardTitle: {
    fontSize: 13,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  cardSub: {
    marginTop: 4,
    fontSize: 11,
    color: theme.colors.mutedDark,
  },
  amountGroup: {
    alignItems: 'flex-end',
  },
  amountText: {
    fontSize: 13,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  statusText: {
    marginTop: 4,
    fontSize: 11,
    color: theme.colors.mutedDark,
  },
  skeletonGap: {
    marginTop: 6,
  },
  emptyCard: {
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
