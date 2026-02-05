import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useState } from 'react';
import { SafeAreaView } from 'react-native-safe-area-context';
import { FlatList, Pressable, StyleSheet, View } from 'react-native';
import { Text } from '@/src/components/i18n/Text';
import { AddCardSheet } from '@/src/overlays/AddCardSheet';
import { EditCardSheet } from '@/src/overlays/EditCardSheet';
import { theme } from '@/src/theme';
import { usePaymentMethods } from '@/lib/paymentMethodsStore';
import { useOrders } from '@/lib/ordersStore';
import { Skeleton } from '@/src/components/ui/Skeleton';
import { formatCurrency } from '@/src/lib/formatCurrency';
import { usePreferences } from '@/src/store/preferencesStore';

const accentCycle = [theme.colors.primary, theme.colors.pink, theme.colors.sun];

export default function PaymentMethodsScreen() {
  const [addVisible, setAddVisible] = useState(false);
  const [editVisible, setEditVisible] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const { state } = usePreferences();
  const { cards } = usePaymentMethods();
  const { orders, loading, error } = useOrders();
  const transactions = orders.slice(0, 6).map((order, index) => ({
    id: `t-${order.number}`,
    total: `-${formatCurrency(order.total, state.currency, state.currency)}`,
    accent: accentCycle[index % accentCycle.length],
    number: order.number,
    date: order.placedAt ?? '—',
  }));
  const transactionRows =
    loading
      ? Array.from({ length: 4 }, (_, index) => ({ id: `sk-${index}`, skeleton: true }))
      : transactions;

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.content}>
        <Text style={styles.title}>Settings</Text>
        <Text style={styles.subtitle}>Payment Methods</Text>

        <View style={styles.cardRow}>
          <FlatList
            horizontal
            data={cards}
            keyExtractor={(item) => item.id}
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={styles.cardList}
            renderItem={({ item }) => (
              <View style={styles.card}>
                <View style={styles.cardTop}>
                  <View style={styles.cardBrand}>
                    <View style={[styles.brandDot, styles.brandRed]} />
                    <View style={[styles.brandDot, styles.brandOrange]} />
                  </View>
                  <Pressable
                    style={styles.cardSettings}
                    onPress={() => {
                      setEditingId(item.id);
                      setEditVisible(true);
                    }}
                  >
                    <Feather name="settings" size={theme.moderateScale(14)} color={theme.colors.primary} />
                  </Pressable>
                </View>
                <Text style={styles.cardNumber}>•••• •••• •••• {item.last4}</Text>
                <View style={styles.cardBottom}>
                  <Text style={styles.cardName}>{item.name}</Text>
                  <Text style={styles.cardExpiry}>{item.expiry}</Text>
                </View>
              </View>
            )}
          />
          <Pressable style={styles.addCard} onPress={() => setAddVisible(true)}>
            <Feather name="plus" size={theme.moderateScale(20)} color={theme.colors.white} />
          </Pressable>
        </View>

        <FlatList
          data={transactionRows}
          keyExtractor={(item) => item.id}
          showsVerticalScrollIndicator={false}
          contentContainerStyle={styles.list}
          renderItem={({ item }) => {
            if ('skeleton' in item) {
              return (
                <View style={styles.transaction}>
                  <Skeleton width={32} height={32} radius={16} />
                  <View style={styles.transactionInfo}>
                    <Skeleton width={120} height={10} />
                    <Skeleton width={90} height={10} style={styles.skeletonGap} />
                  </View>
                  <Skeleton width={50} height={10} />
                </View>
              );
            }

            return (
              <Pressable
                style={styles.transaction}
                onPress={() =>
                  router.push(`/payment/history-details?number=${encodeURIComponent(item.number)}`)
                }
              >
                <View style={[styles.iconWrap, { backgroundColor: theme.colors.primarySoftLight }]}>
                  <Feather name="shopping-bag" size={theme.moderateScale(16)} color={item.accent} />
                </View>
                <View style={styles.transactionInfo}>
                  <Text style={styles.transactionDate}>{item.date}</Text>
                  <Text style={styles.transactionOrder}>Order #{item.number}</Text>
                </View>
                <Text style={[styles.transactionTotal, { color: item.accent }]}>{item.total}</Text>
              </Pressable>
            );
          }}
          ListEmptyComponent={
            !loading ? (
              <View style={styles.emptyCard}>
                <Text style={styles.emptyTitle}>No transactions yet</Text>
                <Text style={styles.emptyBody}>{error ?? 'Recent payments will show up here.'}</Text>
              </View>
            ) : null
          }
        />
      </View>

      <AddCardSheet visible={addVisible} onClose={() => setAddVisible(false)} />
      <EditCardSheet
        visible={editVisible}
        onClose={() => setEditVisible(false)}
        cardId={editingId}
      />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.white,
  },
  content: {
    paddingHorizontal: theme.moderateScale(20),
    paddingTop: theme.moderateScale(10),
    paddingBottom: theme.moderateScale(24),
    flex: 1,
  },
  title: {
    fontSize: theme.moderateScale(22),
    fontWeight: '700',
    color: theme.colors.ink,
  },
  subtitle: {
    marginTop: theme.moderateScale(4),
    fontSize: theme.moderateScale(13),
    color: theme.colors.muted,
  },
  cardRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginTop: theme.moderateScale(16),
  },
  cardList: {
    paddingRight: theme.moderateScale(12),
  },
  card: {
    width: theme.moderateScale(240),
    borderRadius: theme.moderateScale(18),
    backgroundColor: theme.colors.primarySoftLight,
    padding: theme.moderateScale(16),
    marginRight: theme.moderateScale(12),
  },
  cardTop: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  cardBrand: {
    flexDirection: 'row',
  },
  brandDot: {
    width: theme.moderateScale(20),
    height: theme.moderateScale(20),
    borderRadius: theme.moderateScale(10),
  },
  brandRed: {
    backgroundColor: '#eb001b',
  },
  brandOrange: {
    backgroundColor: '#f79e1b',
    marginLeft: theme.moderateScale(-6),
  },
  cardSettings: {
    width: theme.moderateScale(28),
    height: theme.moderateScale(28),
    borderRadius: theme.moderateScale(14),
    backgroundColor: theme.colors.primarySoft,
    alignItems: 'center',
    justifyContent: 'center',
  },
  cardNumber: {
    marginTop: theme.moderateScale(18),
    fontSize: theme.moderateScale(13),
    letterSpacing: theme.moderateScale(2),
    color: theme.colors.ink,
    fontWeight: '600',
  },
  cardBottom: {
    marginTop: theme.moderateScale(12),
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  cardName: {
    fontSize: theme.moderateScale(10),
    color: theme.colors.ink,
    letterSpacing: theme.moderateScale(1),
  },
  cardExpiry: {
    fontSize: theme.moderateScale(11),
    color: theme.colors.ink,
  },
  addCard: {
    width: theme.moderateScale(52),
    height: theme.moderateScale(120),
    borderRadius: theme.moderateScale(14),
    backgroundColor: theme.colors.primary,
    alignItems: 'center',
    justifyContent: 'center',
  },
  list: {
    paddingTop: theme.moderateScale(12),
    gap: theme.moderateScale(10),
  },
  transaction: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: theme.moderateScale(12),
    borderRadius: theme.moderateScale(14),
    backgroundColor: theme.colors.primarySoftLight,
  },
  iconWrap: {
    width: theme.moderateScale(34),
    height: theme.moderateScale(34),
    borderRadius: theme.moderateScale(10),
    alignItems: 'center',
    justifyContent: 'center',
  },
  transactionInfo: {
    marginLeft: theme.moderateScale(10),
    flex: 1,
  },
  transactionDate: {
    fontSize: theme.moderateScale(10),
    color: theme.colors.muted,
  },
  transactionOrder: {
    marginTop: theme.moderateScale(2),
    fontSize: theme.moderateScale(12),
    fontWeight: '600',
    color: theme.colors.ink,
  },
  transactionTotal: {
    fontSize: theme.moderateScale(12),
    fontWeight: '700',
  },
  skeletonGap: {
    marginTop: theme.moderateScale(6),
  },
  emptyCard: {
    padding: theme.moderateScale(16),
    borderRadius: theme.moderateScale(16),
    borderWidth: 1,
    borderColor: theme.colors.primarySoftLight,
    backgroundColor: theme.colors.white,
  },
  emptyTitle: {
    fontSize: theme.moderateScale(12),
    fontWeight: '700',
    color: theme.colors.ink,
  },
  emptyBody: {
    marginTop: theme.moderateScale(6),
    fontSize: theme.moderateScale(11),
    color: theme.colors.muted,
  },
});
