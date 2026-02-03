import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
import { usePreferences } from '@/src/store/preferencesStore';
const fallbackCurrencies = ['USD ($)', 'EUR (€)', 'GBP (£)', 'JPY (¥)'];

export default function ChooseCurrencyScreen() {
  const { state, setCurrency } = usePreferences();
  const selected = state.currency;
  const currencies = state.lookups.currencies.length > 0 ? state.lookups.currencies : fallbackCurrencies;

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
      <View style={styles.headerRow}>
        <Pressable style={styles.iconButton} onPress={() => router.back()}>
          <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
        </Pressable>
        <Text style={styles.title}>Choose currency</Text>
        <Pressable style={styles.iconButton} onPress={() => router.push('/(tabs)/home')}>
          <Feather name="x" size={16} color={theme.colors.inkDark} />
        </Pressable>
      </View>

      <View style={styles.list}>
        {currencies.map((currency, index) => (
          <Pressable
            key={currency}
            style={styles.row}
            onPress={() => {
              setCurrency(currency);
              router.back();
            }}
          >
            <Text style={styles.rowText}>{currency}</Text>
            {selected === currency ? <Feather name="check" size={16} color={theme.colors.inkDark} /> : null}
          </Pressable>
        ))}
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
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: 14,
    borderRadius: 18,
    backgroundColor: theme.colors.sand,
  },
  rowText: {
    fontSize: 14,
    fontWeight: '600',
    color: theme.colors.inkDark,
  },
});
