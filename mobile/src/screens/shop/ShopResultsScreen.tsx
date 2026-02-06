import { router, useLocalSearchParams, usePathname } from 'expo-router';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { SafeAreaView } from 'react-native-safe-area-context';
import { ActivityIndicator, FlatList, StyleSheet, View } from 'react-native';
import { Text } from '@/src/components/i18n/Text';
import { CircleIconButton } from '@/src/components/ui/CircleIconButton';
import { ProductCard } from '@/src/components/ui/ProductCard';
import { SearchBar } from '@/src/components/ui/SearchBar';
import { theme } from '@/src/theme';
import { searchRequest } from '@/src/api/search';
import { useToast } from '@/src/overlays/ToastProvider';
import type { Product } from '@/src/types/storefront';
import { formatCurrency } from '@/src/lib/formatCurrency';
import { usePreferences } from '@/src/store/preferencesStore';

type ProductGridItem = Product | { id: string; skeleton: true };

type PaginationMeta = {
  currentPage: number;
  lastPage: number;
  perPage: number;
  total: number;
};

const toMetaInt = (value: unknown): number | null => {
  if (typeof value === 'number' && Number.isFinite(value)) return Math.trunc(value);
  if (typeof value === 'string' && value.trim().length > 0) {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? Math.trunc(parsed) : null;
  }
  return null;
};

const parseSearchProductsMeta = (meta: Record<string, unknown> | null | undefined): PaginationMeta | null => {
  if (!meta) return null;
  const productsMeta = meta.products;
  if (!productsMeta || typeof productsMeta !== 'object' || Array.isArray(productsMeta)) return null;

  const record = productsMeta as Record<string, unknown>;
  const currentPage = toMetaInt(record.currentPage ?? record.current_page);
  const lastPage = toMetaInt(record.lastPage ?? record.last_page);
  const perPage = toMetaInt(record.perPage ?? record.per_page);
  const total = toMetaInt(record.total);

  if (currentPage === null || lastPage === null || perPage === null || total === null) return null;
  return { currentPage, lastPage, perPage, total };
};

export default function ShopResultsScreen() {
  const { show } = useToast();
  const { state } = usePreferences();
  const params = useLocalSearchParams();
  const query = typeof params.q === 'string' ? params.q : '';
  const category = typeof params.category === 'string' ? params.category : '';
  const minPriceParam = typeof params.min_price === 'string' ? Number(params.min_price) : undefined;
  const maxPriceParam = typeof params.max_price === 'string' ? Number(params.max_price) : undefined;
  const sortParam = typeof params.sort === 'string' ? params.sort : '';
  const pathname = usePathname();
  const [items, setItems] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);
  const [paginationMeta, setPaginationMeta] = useState<PaginationMeta | null>(null);
  const requestId = useRef(0);
  const loadingMoreRef = useRef(false);
  const perPage = 12;

  useEffect(() => {
    const id = ++requestId.current;
    setLoading(true);
    setLoadingMore(false);
    loadingMoreRef.current = false;
    setPaginationMeta(null);
    searchRequest({
      q: query,
      category,
      min_price: Number.isFinite(minPriceParam) ? minPriceParam : undefined,
      max_price: Number.isFinite(maxPriceParam) ? maxPriceParam : undefined,
      sort: sortParam || undefined,
      per_page: perPage,
      page: 1,
    })
      .then(({ products, meta }) => {
        if (id !== requestId.current) return;
        setItems(products);
        setPaginationMeta(parseSearchProductsMeta(meta));
      })
      .catch((err: any) => {
        if (id !== requestId.current) return;
        show({ type: 'error', message: err?.message ?? 'Unable to load products.' });
        setItems([]);
      })
      .finally(() => {
        if (id === requestId.current) setLoading(false);
      });

    return () => {
      requestId.current += 1;
    };
  }, [query, category, minPriceParam, maxPriceParam, sortParam, show, perPage]);

  const canLoadMore = useMemo(() => {
    if (!paginationMeta) return false;
    return paginationMeta.currentPage < paginationMeta.lastPage;
  }, [paginationMeta]);

  const loadMore = useCallback(() => {
    if (loading) return;
    if (!canLoadMore || !paginationMeta) return;
    if (loadingMoreRef.current || loadingMore) return;

    const nextPage = paginationMeta.currentPage + 1;
    if (nextPage > paginationMeta.lastPage) return;

    loadingMoreRef.current = true;
    setLoadingMore(true);
    const id = ++requestId.current;

    searchRequest({
      q: query,
      category,
      min_price: Number.isFinite(minPriceParam) ? minPriceParam : undefined,
      max_price: Number.isFinite(maxPriceParam) ? maxPriceParam : undefined,
      sort: sortParam || undefined,
      per_page: perPage,
      page: nextPage,
    })
      .then(({ products, meta }) => {
        if (id !== requestId.current) return;
        setItems((prev) => [...prev, ...products]);
        setPaginationMeta(parseSearchProductsMeta(meta));
      })
      .catch((err: any) => {
        if (id !== requestId.current) return;
        show({ type: 'error', message: err?.message ?? 'Unable to load products.' });
      })
      .finally(() => {
        if (id === requestId.current) setLoadingMore(false);
        loadingMoreRef.current = false;
      });
  }, [loading, canLoadMore, paginationMeta, loadingMore, query, category, minPriceParam, maxPriceParam, sortParam, show, perPage]);

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

      <FlatList<ProductGridItem>
        data={
          loading
            ? Array.from({ length: 6 }, (_, index) => ({ id: `sk-${index}`, skeleton: true as const }))
            : items
        }
        keyExtractor={(item) => item.id}
        numColumns={2}
        columnWrapperStyle={styles.column}
        showsVerticalScrollIndicator={false}
        contentContainerStyle={styles.listContent}
        onEndReached={loadMore}
        onEndReachedThreshold={0.55}
        ListFooterComponent={
          loadingMore ? (
            <View style={styles.footerLoading}>
              <ActivityIndicator size="small" color={theme.colors.inkDark} />
            </View>
          ) : (
            <View style={styles.footerSpacer} />
          )
        }
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
              price={formatCurrency(item.price, item.currency, state.currency)}
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
  footerLoading: {
    paddingVertical: theme.moderateScale(18),
    alignItems: 'center',
    justifyContent: 'center',
  },
  footerSpacer: {
    height: theme.moderateScale(24),
  },
});
