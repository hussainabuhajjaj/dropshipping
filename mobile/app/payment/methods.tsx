import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
import { usePaymentMethods } from '@/lib/paymentMethodsStore';

export default function PaymentMethodsScreen() {
  const { cards, selectedCard, selectCard } = usePaymentMethods();

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
      <View style={styles.headerRow}>
        <Pressable style={styles.iconButton} onPress={() => router.back()}>
          <Feather name="arrow-left" size={16} color={theme.colors.inkDark} />
        </Pressable>
        <Text style={styles.title}>Payment method</Text>
        <Pressable style={styles.iconButton} onPress={() => router.push('/payment/methods-2')}>
          <Feather name="plus" size={16} color={theme.colors.inkDark} />
        </Pressable>
      </View>

      <View style={styles.list}>
        {cards.map((card) => (
          <Pressable
            key={card.id}
            style={styles.card}
            onPress={() => selectCard(card.id)}
          >
            <View style={styles.cardIcon}>
              <Feather name="credit-card" size={18} color={theme.colors.inkDark} />
            </View>
            <View style={styles.cardInfo}>
              <Text style={styles.cardTitle}>{card.brand === 'visa' ? 'Visa' : 'Mastercard'}</Text>
              <Text style={styles.cardBody}>•••• {card.last4}</Text>
            </View>
            {selectedCard?.id === card.id ? (
              <View style={styles.checkCircle}>
                <Feather name="check" size={14} color={theme.colors.inkDark} />
              </View>
            ) : (
              <View style={styles.checkCircleInactive} />
            )}
          </Pressable>
        ))}
      </View>

      <Pressable style={styles.primaryButton} onPress={() => router.back()}>
        <Text style={styles.primaryText}>Confirm</Text>
      </Pressable>
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
    paddingTop: 10,
    paddingBottom: 24,
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
    width: 32,
    height: 32,
    borderRadius: 16,
    backgroundColor: theme.colors.gray100,
    alignItems: 'center',
    justifyContent: 'center',
  },
  list: {
    gap: 12,
  },
  card: {
    padding: 16,
    borderRadius: 18,
    backgroundColor: theme.colors.sand,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  cardIcon: {
    width: 42,
    height: 42,
    borderRadius: 16,
    backgroundColor: theme.colors.white,
    alignItems: 'center',
    justifyContent: 'center',
  },
  cardInfo: {
    flex: 1,
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
  checkCircle: {
    width: 22,
    height: 22,
    borderRadius: 11,
    backgroundColor: theme.colors.sun,
    alignItems: 'center',
    justifyContent: 'center',
  },
  checkCircleInactive: {
    width: 22,
    height: 22,
    borderRadius: 11,
    borderWidth: 1,
    borderColor: '#c7c7c7',
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
});
