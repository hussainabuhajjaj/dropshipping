import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
import { usePreferences } from '@/src/store/preferencesStore';
import { SafeAreaView } from 'react-native-safe-area-context';
const fallbackSizes = ['US', 'EU', 'UK', 'Asia'];

export default function SizesTypesScreen() {
  const { state, setSize } = usePreferences();
  const selected = state.size;
  const sizeTypes = state.lookups.sizes.length > 0 ? state.lookups.sizes : fallbackSizes;

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView style={styles.scroll} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.headerRow}>
          <Pressable style={styles.iconButton} onPress={() => router.back()}>
            <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
          </Pressable>
          <Text style={styles.title}>Sizes types</Text>
          <Pressable style={styles.iconButton} onPress={() => router.push('/(tabs)/home')}>
            <Feather name="x" size={16} color={theme.colors.inkDark} />
          </Pressable>
        </View>

        <View style={styles.list}>
          {sizeTypes.map((size) => (
            <Pressable
              key={size}
              style={styles.row}
              onPress={() => {
                setSize(size);
                router.back();
              }}
            >
              <Text style={styles.rowText}>{size}</Text>
              {selected === size ? <Feather name="check" size={16} color={theme.colors.inkDark} /> : null}
            </Pressable>
          ))}
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.white,
  },
  scroll: {
    flex: 1,
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
