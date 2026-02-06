import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Feather } from '@expo/vector-icons';
import { router, Stack, type Href, useLocalSearchParams, usePathname } from 'expo-router';
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  StyleSheet,
  Text,
  View,
  useWindowDimensions,
} from '@/src/utils/responsiveStyleSheet';
import { SafeAreaView } from 'react-native-safe-area-context';
import { ProductFilterChips } from '@/src/components/products/ProductFilterChips';
import { ProductTile } from '@/src/components/products/ProductTile';
import { fetchProducts } from '@/src/api/catalog';
import type { Product } from '@/src/types/storefront';
import { useToast } from '@/src/overlays/ToastProvider';
import { theme } from '@/src/theme';
import { usePullToRefresh } from '@/src/hooks/usePullToRefresh';
import { useTranslations } from '@/src/i18n/TranslationsProvider';

type ProductsScreenProps = {
  filterRoute?: string;
};

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

const parsePaginationMeta = (meta: Record<string, unknown> | null | undefined): PaginationMeta | null => {
  if (!meta) return null;
  const currentPage = toMetaInt(meta.currentPage ?? meta.current_page);
  const lastPage = toMetaInt(meta.lastPage ?? meta.last_page);
  const perPage = toMetaInt(meta.perPage ?? meta.per_page);
  const total = toMetaInt(meta.total);

  if (currentPage === null || lastPage === null || perPage === null || total === null) return null;
  return { currentPage, lastPage, perPage, total };
};

export default function ProductsScreen({ filterRoute = '/products/filters' }: ProductsScreenProps) {
  const params = useLocalSearchParams();
  const query =
    typeof params.q === 'string' ? params.q : typeof params.query === 'string' ? params.query : '';
  const category = typeof params.category === 'string' ? params.category : '';
  const categoryTitle = typeof params.title === 'string' ? params.title : '';
  const minPriceParam = typeof params.min_price === 'string' ? Number(params.min_price) : undefined;
  const maxPriceParam = typeof params.max_price === 'string' ? Number(params.max_price) : undefined;
  const sortParam = typeof params.sort === 'string' ? params.sort : '';
  const pathname = usePathname();
  const { t } = useTranslations();
  const [chip, setChip] = useState('trending');
  const [items, setItems] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [paginationMeta, setPaginationMeta] = useState<PaginationMeta | null>(null);
  const requestId = useRef(0);
  const loadingMoreRef = useRef(false);
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
    if (!category) {
      if (query.trim()) return t('Search results', 'Search results');
      return t('All products', 'All products');
    }
    if (categoryTitle.trim()) return categoryTitle.trim();
    if (category.includes('-') || category.includes('_')) {
      return category
        .replace(/[-_]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .replace(/\b\w/g, (char) => char.toUpperCase());
    }
    return category;
  }, [category, categoryTitle, t]);

  const canLoadMore = useMemo(() => {
    if (!paginationMeta) return false;
    return paginationMeta.currentPage < paginationMeta.lastPage;
  }, [paginationMeta]);

  const chipOptions = useMemo(
    () => [
      { key: 'trending', label: t('Trending', 'Trending') },
      { key: 'new', label: t('New', 'New') },
      { key: 'sale', label: t('Sale', 'Sale') },
      { key: 'top_rated', label: t('Top Rated', 'Top Rated') },
    ],
    [t]
  );

  const chipToSort: Record<string, string> = {
    trending: 'popular',
    new: 'newest',
    sale: 'price_desc',
    top_rated: 'rating',
  };
  const sortToChip: Record<string, string> = {
    popular: 'trending',
    newest: 'new',
    price_desc: 'sale',
    rating: 'top_rated',
  };

  useEffect(() => {
    if (sortParam && sortToChip[sortParam] && sortToChip[sortParam] !== chip) {
      setChip(sortToChip[sortParam]);
    }
  }, [sortParam, chip]);

  const loadProducts = useCallback(async (page = 1, mode: 'replace' | 'append' = 'replace') => {
    const id = ++requestId.current;
    const isAppend = mode === 'append';
    if (isAppend) {
      setLoadingMore(true);
    } else {
      setLoading(true);
      setLoadingMore(false);
      loadingMoreRef.current = false;
      setPaginationMeta(null);
      setError(null);
    }
    try {
      const { items: data, meta } = await fetchProducts({
        q: query,
        category,
        min_price: Number.isFinite(minPriceParam) ? minPriceParam : undefined,
        max_price: Number.isFinite(maxPriceParam) ? maxPriceParam : undefined,
        sort: sortParam || chipToSort[chip],
        page,
      });
      if (id !== requestId.current) return;

      const nextMeta = parsePaginationMeta(meta);
      setPaginationMeta(nextMeta);
      setItems((prev) => (isAppend ? [...prev, ...data] : data));
    } catch (err: any) {
      if (id !== requestId.current) return;
      const message = err?.message ?? 'Unable to load products.';
      show({ type: 'error', message });
      if (!isAppend) {
        setError(message);
        setItems([]);
      }
    } finally {
      if (id === requestId.current) {
        if (isAppend) {
          setLoadingMore(false);
        } else {
          setLoading(false);
        }
      }
      if (isAppend) {
        loadingMoreRef.current = false;
      }
    }
  }, [query, category, minPriceParam, maxPriceParam, sortParam, chip, show]);

  useEffect(() => {
    loadProducts();
    return () => {
      requestId.current += 1;
    };
  }, [loadProducts]);

  const loadMore = useCallback(() => {
    if (loading) return;
    if (!canLoadMore || !paginationMeta) return;
    if (loadingMoreRef.current || loadingMore) return;

    const nextPage = paginationMeta.currentPage + 1;
    if (nextPage > paginationMeta.lastPage) return;

    loadingMoreRef.current = true;
    loadProducts(nextPage, 'append');
  }, [loading, canLoadMore, paginationMeta, loadingMore, loadProducts]);

  const { refreshing, onRefresh } = usePullToRefresh(() => loadProducts(1, 'replace'));

  return (
    <SafeAreaView style={styles.container}>
      <FlatList
        data={listData}
        keyExtractor={(item) => String(item.id)}
        numColumns={2}
        showsVerticalScrollIndicator={false}
        refreshing={refreshing}
        onRefresh={onRefresh}
        onEndReached={loadMore}
        onEndReachedThreshold={0.55}
        columnWrapperStyle={styles.column}
        contentContainerStyle={styles.content}
        ListHeaderComponent={
          <View>
            <Stack.Screen options={{ headerShown: false }} />
            <View style={styles.headerRow}>
              <Pressable
                style={styles.iconButton}
                onPress={() => {
                  if (router.canGoBack()) {
                    router.back();
                    return;
                  }
                  router.replace('/(tabs)/home');
                }}
              >
                <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
              </Pressable>
              <Text style={styles.title}>{displayCategory}</Text>
              <Pressable
                style={styles.iconButton}
                onPress={() =>
                  router.push(
                    {
                      pathname: filterRoute || '/products/filters',
                      params: {
                        returnTo: pathname,
                        q: query || undefined,
                        category: category || undefined,
                        title: categoryTitle.trim() ? categoryTitle.trim() : undefined,
                        min_price: Number.isFinite(minPriceParam) ? String(minPriceParam) : undefined,
                        max_price: Number.isFinite(maxPriceParam) ? String(maxPriceParam) : undefined,
                        sort: sortParam || chipToSort[chip],
                      },
                    } as Href
                  )
                }
              >
                <Feather name="sliders" size={16} color={theme.colors.inkDark} />
              </Pressable>
            </View>
            <Text style={styles.subtitle}>
              {query
                ? `${t('Results for', 'Results for')} "${query}"`
                : t('Fresh picks updated daily.', 'Fresh picks updated daily.')}
            </Text>
            <View style={styles.chips}>
              <ProductFilterChips
                active={chip}
                options={chipOptions}
                onSelect={(value) => {
                  setChip(value);
                  const sort = chipToSort[value] ?? 'popular';
                  router.replace(
                    {
                      pathname,
                      params: {
                        q: query || undefined,
                        category: category || undefined,
                        title: categoryTitle.trim() ? categoryTitle.trim() : undefined,
                        min_price: Number.isFinite(minPriceParam) ? String(minPriceParam) : undefined,
                        max_price: Number.isFinite(maxPriceParam) ? String(maxPriceParam) : undefined,
                        sort,
                      },
                    } as Href
                  );
                }}
              />
            </View>
            {items.length === 0 && !loading ? (
              <View style={styles.emptyCard}>
                <Text style={styles.emptyTitle}>
                  {error
                    ? t('Unable to load products', 'Unable to load products')
                    : t('No products found', 'No products found')}
                </Text>
                <Text style={styles.emptyBody}>
                  {error
                    ? t('Please check your connection and try again.', 'Please check your connection and try again.')
                    : t('Try adjusting filters or search for a new term.', 'Try adjusting filters or search for a new term.')}
                </Text>
              </View>
            ) : null}
          </View>
        }
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
    </SafeAreaView>
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
  footerLoading: {
    paddingVertical: 18,
    alignItems: 'center',
    justifyContent: 'center',
  },
  footerSpacer: {
    height: 24,
  },
});
