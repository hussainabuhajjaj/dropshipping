import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Feather } from '@expo/vector-icons';
import { router, useLocalSearchParams, usePathname } from 'expo-router';
import { FlatList, Pressable, StyleSheet, Text, View, useWindowDimensions } from '@/src/utils/responsiveStyleSheet';
import { ProductFilterChips } from '@/src/components/products/ProductFilterChips';
import { ProductTile } from '@/src/components/products/ProductTile';
import { fetchProducts } from '@/src/api/catalog';
import type { Product } from '@/src/types/storefront';
import { useToast } from '@/src/overlays/ToastProvider';
import { theme } from '@/src/theme';
import { usePullToRefresh } from '@/src/hooks/usePullToRefresh';

type ProductsScreenProps = {
  filterRoute?: string;
};

export default function ProductsScreen({ filterRoute = '/products/filters' }: ProductsScreenProps) {
  const params = useLocalSearchParams();
  const query = typeof params.q === 'string' ? params.q : '';
  const category = typeof params.category === 'string' ? params.category : '';
  const minPriceParam = typeof params.min_price === 'string' ? Number(params.min_price) : undefined;
  const maxPriceParam = typeof params.max_price === 'string' ? Number(params.max_price) : undefined;
  const sortParam = typeof params.sort === 'string' ? params.sort : '';
  const pathname = usePathname();
  const [chip, setChip] = useState('Trending');
  const [items, setItems] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const requestId = useRef(0);
  const { show } = useToast();
  const { width } = useWindowDimensions();
  const horizontalPadding = theme.moderateScale(20);
  const gridGap = theme.moderateScale(12);
  const gridItemWidth = useMemo(
    () => (width - horizontalPadding * 2 - gridGap) / 2,
    [width, horizontalPadding, gridGap]
  );
  const skeletonItems = useMemo(
    () => Array.from({ length: 6 }, (_, index) => ({ id: `skeleton-${index}` })),
    []
  );
  const listData = loading ? skeletonItems : items;
  const displayCategory = useMemo(() => {
    if (!category) return 'All products';
    if (category.includes('-') || category.includes('_')) {
      return category
        .replace(/[-_]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .replace(/\b\w/g, (char) => char.toUpperCase());
    }
    return category;
  }, [category]);

  const chipToSort: Record<string, string> = {
    Trending: 'popular',
    New: 'newest',
    Sale: 'price_desc',
    'Top Rated': 'rating',
  };
  const sortToChip: Record<string, string> = {
    popular: 'Trending',
    newest: 'New',
    price_desc: 'Sale',
    rating: 'Top Rated',
  };

  useEffect(() => {
    if (sortParam && sortToChip[sortParam] && sortToChip[sortParam] !== chip) {
      setChip(sortToChip[sortParam]);
    }
  }, [sortParam, chip]);

  const loadProducts = useCallback(async () => {
    const id = ++requestId.current;
    setLoading(true);
    setError(null);
    try {
      const { items: data } = await fetchProducts({
        q: query,
        category,
        min_price: Number.isFinite(minPriceParam) ? minPriceParam : undefined,
        max_price: Number.isFinite(maxPriceParam) ? maxPriceParam : undefined,
        sort: sortParam || chipToSort[chip],
      });
      if (id !== requestId.current) return;
      setItems(data);
    } catch (err: any) {
      if (id !== requestId.current) return;
      const message = err?.message ?? 'Unable to load products.';
      setError(message);
      show({ type: 'error', message });
    } finally {
      if (id === requestId.current) setLoading(false);
    }
  }, [query, category, minPriceParam, maxPriceParam, sortParam, chip, show]);

  useEffect(() => {
    loadProducts();
    return () => {
      requestId.current += 1;
    };
  }, [loadProducts]);

  const { refreshing, onRefresh } = usePullToRefresh(loadProducts);

  return (
    <View style={styles.container}>
      <FlatList
        data={listData}
        keyExtractor={(item) => String(item.id)}
        numColumns={2}
        showsVerticalScrollIndicator={false}
        refreshing={refreshing}
        onRefresh={onRefresh}
        columnWrapperStyle={styles.column}
        contentContainerStyle={styles.content}
        ListHeaderComponent={
          <View>
            <View style={styles.headerRow}>
              <Pressable style={styles.iconButton} onPress={() => router.back()}>
                <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
              </Pressable>
              <Text style={styles.title}>{displayCategory}</Text>
              <Pressable
                style={styles.iconButton}
                onPress={() =>
                  router.push({
                    pathname: filterRoute || '/products/filters',
                    params: {
                      returnTo: pathname,
                      q: query || undefined,
                      category: category || undefined,
                      min_price: Number.isFinite(minPriceParam) ? String(minPriceParam) : undefined,
                      max_price: Number.isFinite(maxPriceParam) ? String(maxPriceParam) : undefined,
                      sort: sortParam || chipToSort[chip],
                    },
                  })
                }
              >
                <Feather name="sliders" size={16} color={theme.colors.inkDark} />
              </Pressable>
            </View>
            <Text style={styles.subtitle}>{query ? `Results for "${query}"` : 'Fresh picks updated daily.'}</Text>
            <View style={styles.chips}>
              <ProductFilterChips
                active={chip}
                onSelect={(value) => {
                  setChip(value);
                  const sort = chipToSort[value] ?? 'popular';
                  router.replace({
                    pathname,
                    params: {
                      q: query || undefined,
                      category: category || undefined,
                      min_price: Number.isFinite(minPriceParam) ? String(minPriceParam) : undefined,
                      max_price: Number.isFinite(maxPriceParam) ? String(maxPriceParam) : undefined,
                      sort,
                    },
                  });
                }}
              />
            </View>
            {items.length === 0 && !loading ? (
              <View style={styles.emptyCard}>
                <Text style={styles.emptyTitle}>{error ? 'Unable to load products' : 'No products found'}</Text>
                <Text style={styles.emptyBody}>
                  {error ? 'Please check your connection and try again.' : 'Try adjusting filters or search for a new term.'}
                </Text>
              </View>
            ) : null}
          </View>
        }
        renderItem={({ item }) => {
          const isSkeleton = !('slug' in item);
          return (
            <View style={[styles.gridItem, { width: gridItemWidth }]}>
              {isSkeleton ? (
                <ProductTile loading mode="grid" />
              ) : (
                <ProductTile product={item as Product} mode="grid" />
              )}
            </View>
          );
        }}
      />
    </View>
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
  subtitle: {
    fontSize: 13,
    color: theme.colors.mutedDark,
    marginBottom: 14,
  },
  chips: {
    marginBottom: 14,
  },
  emptyCard: {
    backgroundColor: theme.colors.white,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: theme.colors.sand,
    padding: 18,
    alignItems: 'center',
  },
  emptyTitle: {
    fontSize: 14,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  emptyBody: {
    fontSize: 12,
    color: theme.colors.mutedDark,
    marginTop: 6,
    textAlign: 'center',
  },
  column: {
    justifyContent: 'space-between',
    marginBottom: 12,
  },
  gridItem: {
    marginBottom: 12,
  },
});
