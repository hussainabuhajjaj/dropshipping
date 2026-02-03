import { router } from 'expo-router';
import { useEffect, useState } from 'react';
import { SafeAreaView } from 'react-native-safe-area-context';
import { FlatList, StyleSheet, Text, View } from 'react-native';
import { CircleIconButton } from '@/src/components/ui/CircleIconButton';
import { Chip } from '@/src/components/ui/Chip';
import { ProductCard } from '@/src/components/ui/ProductCard';
import { SearchBar } from '@/src/components/ui/SearchBar';
import { theme } from '@/src/theme';
import { fetchProducts } from '@/src/api/catalog';
import { useToast } from '@/src/overlays/ToastProvider';
import type { Product } from '@/src/types/storefront';

const historyChips = ['Red Dress', 'Sunglasses', 'Mustard Pants', '80-s Skirt'];
const recommendationChips = ['Skirt', 'Accessories', 'Black T-Shirt', 'Jeans', 'White Shoes'];

export default function SearchScreen() {
  const { show } = useToast();
  const [discoverItems, setDiscoverItems] = useState<Product[]>([]);
  const [loadingDiscover, setLoadingDiscover] = useState(true);
  const [query, setQuery] = useState('');

  const goToResults = (query: string) => {
    router.push(`/search/results?query=${encodeURIComponent(query)}`);
  };

  useEffect(() => {
    let active = true;
    setLoadingDiscover(true);
    fetchProducts({ per_page: 6 })
      .then(({ items }) => {
        if (!active) return;
        setDiscoverItems(items);
      })
      .catch((err: any) => {
        if (!active) return;
        show({ type: 'error', message: err?.message ?? 'Unable to load discovery items.' });
        setDiscoverItems([]);
      })
      .finally(() => {
        if (active) setLoadingDiscover(false);
      });

    return () => {
      active = false;
    };
  }, [show]);

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.content}>
        <View style={styles.headerRow}>
          <Text style={styles.title}>Search</Text>
          <SearchBar
            placeholder="Search for products"
            value={query}
            onChangeText={setQuery}
            onClear={() => setQuery('')}
            onSubmitEditing={() => {
              const trimmed = query.trim();
              if (trimmed.length === 0) return;
              goToResults(trimmed);
            }}
            returnKeyType="search"
            onRightPress={() => router.push('/image-search')}
            showSearchIcon={false}
            rightIconBorder={theme.colors.border}
            style={styles.searchBar}
          />
        </View>

        <View style={styles.sectionHeader}>
          <Text style={styles.sectionTitle}>Search history</Text>
          <CircleIconButton
            icon="trash-2"
            size={theme.moderateScale(28)}
            variant="outlined"
            iconColor={theme.colors.pink}
            borderColor={theme.colors.pinkSoft}
          />
        </View>
        <View style={styles.chipWrap}>
          {historyChips.map((chip) => (
            <Chip key={chip} label={chip} onPress={() => goToResults(chip)} />
          ))}
        </View>

        <Text style={[styles.sectionTitle, styles.sectionSpacing]}>Recommendations</Text>
        <View style={styles.chipWrap}>
          {recommendationChips.map((chip) => (
            <Chip key={chip} label={chip} onPress={() => goToResults(chip)} />
          ))}
        </View>

        <Text style={[styles.sectionTitle, styles.sectionSpacing]}>Discover</Text>
        <FlatList
          horizontal
          data={
            loadingDiscover
              ? Array.from({ length: 3 }, (_, index) => ({ id: `sk-${index}`, skeleton: true }))
              : discoverItems
          }
          keyExtractor={(item) => item.id}
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={styles.discoverRow}
          renderItem={({ item }) => {
            if ('skeleton' in item) {
              return (
                <ProductCard
                  loading
                  width={theme.moderateScale(140)}
                  imageHeight={theme.moderateScale(120)}
                />
              );
            }
            return (
              <ProductCard
                title={item.name}
                price={`$${item.price.toFixed(2)}`}
                width={theme.moderateScale(140)}
                imageHeight={theme.moderateScale(120)}
                image={item.image ? { uri: item.image } : undefined}
                onPress={() => router.push(`/products/${item.slug}`)}
              />
            );
          }}
        />
      </View>
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
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.moderateScale(12),
    marginBottom: theme.moderateScale(14),
  },
  searchBar: {
    flex: 1,
    backgroundColor: theme.colors.white,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  title: {
    fontSize: theme.moderateScale(22),
    fontWeight: '700',
    color: theme.colors.ink,
  },
  sectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginTop: theme.moderateScale(6),
  },
  sectionTitle: {
    fontSize: theme.moderateScale(14),
    fontWeight: '600',
    color: theme.colors.ink,
  },
  sectionSpacing: {
    marginTop: theme.moderateScale(18),
  },
  chipWrap: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: theme.moderateScale(10),
    marginTop: theme.moderateScale(10),
  },
  discoverRow: {
    paddingTop: theme.moderateScale(10),
    paddingBottom: theme.moderateScale(6),
    gap: theme.moderateScale(14),
  },
});
