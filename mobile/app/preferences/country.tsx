import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
import { usePreferences } from '@/src/store/preferencesStore';
const fallbackCountries = ['United States', 'United Kingdom', 'France', 'Germany', 'Japan'];

export default function ChooseCountryScreen() {
  const { state, setCountry } = usePreferences();
  const selected = state.country;
  const countries = state.lookups.countries.length > 0 ? state.lookups.countries : fallbackCountries;

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
      <View style={styles.headerRow}>
        <Pressable style={styles.iconButton} onPress={() => router.back()}>
          <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
        </Pressable>
        <Text style={styles.title}>Choose country</Text>
        <Pressable style={styles.iconButton} onPress={() => router.push('/(tabs)/home')}>
          <Feather name="x" size={16} color={theme.colors.inkDark} />
        </Pressable>
      </View>

      <View style={styles.list}>
        {countries.map((country, index) => (
          <Pressable
            key={country}
            style={styles.row}
            onPress={() => {
              setCountry(country);
              router.back();
            }}
          >
            <Text style={styles.rowText}>{country}</Text>
            {selected === country ? <Feather name="check" size={16} color={theme.colors.inkDark} /> : null}
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
