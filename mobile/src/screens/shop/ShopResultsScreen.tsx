import { router, useLocalSearchParams, usePathname } from 'expo-router';
import { useEffect, useState } from 'react';
import { SafeAreaView } from 'react-native-safe-area-context';
import { FlatList, StyleSheet, Text, View } from 'react-native';
import { CircleIconButton } from '@/src/components/ui/CircleIconButton';
import { ProductCard } from '@/src/components/ui/ProductCard';
import { SearchBar } from '@/src/components/ui/SearchBar';
import { theme } from '@/src/theme';
import { searchRequest } from '@/src/api/search';
import { useToast } from '@/src/overlays/ToastProvider';
import type { Product } from '@/src/types/storefront';

export default function ShopResultsScreen() {
  const { show } = useToast();
  const params = useLocalSearchParams();
  const query = typeof params.q === 'string' ? params.q : '';
  const category = typeof params.category === 'string' ? params.category : '';
  const minPriceParam = typeof params.min_price === 'string' ? Number(params.min_price) : undefined;
  const maxPriceParam = typeof params.max_price === 'string' ? Number(params.max_price) : undefined;
  const sortParam = typeof params.sort === 'string' ? params.sort : '';
  const pathname = usePathname();
  const [items, setItems] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let active = true;
    setLoading(true);
    searchRequest({
      q: query,
      category,
      min_price: Number.isFinite(minPriceParam) ? minPriceParam : undefined,
      max_price: Number.isFinite(maxPriceParam) ? maxPriceParam : undefined,
      sort: sortParam || undefined,
      per_page: 12,
    })
      .then(({ products }) => {
        if (!active) return;
        setItems(products);
      })
      .catch((err: any) => {
        if (!active) return;
        show({ type: 'error', message: err?.message ?? 'Unable to load products.' });
        setItems([]);
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => {
      active = false;
    };
  }, [query, category, minPriceParam, maxPriceParam, sortParam, show]);

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.headerRow}>
        <Text style={styles.title}>Shop</Text>
        <SearchBar
          placeholder=""
          value={query}
          readOnly
          onClear={() => {}}
          onRightPress={() => router.push('/image-search')}
          onPress={() => router.push(query ? `/search/results?query=${encodeURIComponent(query)}` : '/search')}
          showSearchIcon={false}
          rightIconBorder={theme.colors.border}
          style={styles.search}
        />
        <CircleIconButton
          icon="sliders"
          size={theme.moderateScale(30)}
          variant="outlined"
          onPress={() =>
            router.push({
              pathname: '/products/filters',
              params: {
                returnTo: pathname,
                q: query || undefined,
                category: category || undefined,
                min_price: Number.isFinite(minPriceParam) ? String(minPriceParam) : undefined,
                max_price: Number.isFinite(maxPriceParam) ? String(maxPriceParam) : undefined,
                sort: sortParam || undefined,
              },
            })
          }
        />
      </View>

      <FlatList
        data={
          loading
            ? Array.from({ length: 6 }, (_, index) => ({ id: `sk-${index}`, skeleton: true }))
            : items
        }
        keyExtractor={(item) => item.id}
        numColumns={2}
        columnWrapperStyle={styles.column}
        showsVerticalScrollIndicator={false}
        contentContainerStyle={styles.listContent}
        renderItem={({ item }) => {
          if ('skeleton' in item) {
            return (
              <ProductCard
                loading
                width="48%"
                imageHeight={theme.moderateScale(140)}
              />
            );
          }
          return (
            <ProductCard
              title={item.name}
              price={`$${item.price.toFixed(2)}`}
              width="48%"
              imageHeight={theme.moderateScale(140)}
              image={item.image ? { uri: item.image } : undefined}
              onPress={() => router.push(`/products/${item.slug}`)}
            />
          );
        }}
      />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.white,
    paddingHorizontal: theme.moderateScale(20),
    paddingTop: theme.moderateScale(6),
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.moderateScale(10),
    marginBottom: theme.moderateScale(12),
  },
  title: {
    fontSize: theme.moderateScale(20),
    fontWeight: '700',
    color: theme.colors.ink,
  },
  search: {
    flex: 1,
    backgroundColor: theme.colors.white,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  listContent: {
    paddingBottom: theme.moderateScale(24),
  },
  column: {
    justifyContent: 'space-between',
    marginBottom: theme.moderateScale(16),
  },
});
