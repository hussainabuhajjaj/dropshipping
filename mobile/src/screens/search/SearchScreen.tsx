import { router } from 'expo-router';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { SafeAreaView } from 'react-native-safe-area-context';
import { FlatList, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';
import { Text } from '@/src/components/i18n/Text';
import { CircleIconButton } from '@/src/components/ui/CircleIconButton';
import { Chip } from '@/src/components/ui/Chip';
import { ProductCard } from '@/src/components/ui/ProductCard';
import { SearchBar } from '@/src/components/ui/SearchBar';
import { theme } from '@/src/theme';
import { fetchProducts } from '@/src/api/catalog';
import { searchRequest } from '@/src/api/search';
import { useToast } from '@/src/overlays/ToastProvider';
import type { Category, Product } from '@/src/types/storefront';
import { addSearchHistory, clearSearchHistory, loadSearchHistory } from '@/src/lib/searchHistory';
import { usePullToRefresh } from '@/src/hooks/usePullToRefresh';
import { formatCurrency } from '@/src/lib/formatCurrency';
import { usePreferences } from '@/src/store/preferencesStore';

const fallbackRecommendations: string[] = [];

export default function SearchScreen() {
  const { show } = useToast();
  const { state } = usePreferences();
  const [discoverItems, setDiscoverItems] = useState<Product[]>([]);
  const [loadingDiscover, setLoadingDiscover] = useState(true);
  const [query, setQuery] = useState('');
  const [history, setHistory] = useState<string[]>([]);
  const [recommendations, setRecommendations] = useState<string[]>(fallbackRecommendations);
  const [categorySuggestions, setCategorySuggestions] = useState<Category[]>([]);
  const [productSuggestions, setProductSuggestions] = useState<Product[]>([]);
  const [loadingSuggestions, setLoadingSuggestions] = useState(false);
  const discoverRequestId = useRef(0);

  const goToResults = async (term: string) => {
    const trimmed = term.trim();
    if (!trimmed) return;
    setHistory(await addSearchHistory(trimmed));
    router.push(`/search/results?query=${encodeURIComponent(trimmed)}`);
  };

  const loadDiscover = useCallback(async () => {
    const id = ++discoverRequestId.current;
    setLoadingDiscover(true);
    try {
      const { items } = await fetchProducts({ per_page: 6 });
      if (id !== discoverRequestId.current) return;
      setDiscoverItems(items);
    } catch (err: any) {
      if (id !== discoverRequestId.current) return;
      show({ type: 'error', message: err?.message ?? 'Unable to load discovery items.' });
      setDiscoverItems([]);
    } finally {
      if (id === discoverRequestId.current) setLoadingDiscover(false);
    }
  }, [show]);

  const loadHistory = useCallback(async () => {
    const items = await loadSearchHistory();
    setHistory(items);
  }, []);

  const refreshSuggestions = useCallback(async (term: string) => {
    const trimmed = term.trim();
    if (trimmed.length <= 1) {
      setCategorySuggestions([]);
      setProductSuggestions([]);
      setRecommendations(fallbackRecommendations);
      return;
    }
    setLoadingSuggestions(true);
    try {
      const { categories, products } = await searchRequest({
        q: trimmed,
        categories_limit: 6,
        per_page: 6,
      });
      const labels = categories
        .map((item) => item.name)
        .filter((value): value is string => typeof value === 'string' && value.trim().length > 0);
      setCategorySuggestions(categories);
      setProductSuggestions(products.slice(0, 6));
      setRecommendations(labels.length ? labels : fallbackRecommendations);
    } catch {
      setCategorySuggestions([]);
      setProductSuggestions([]);
      setRecommendations(fallbackRecommendations);
    } finally {
      setLoadingSuggestions(false);
    }
  }, []);

  useEffect(() => {
    loadDiscover();
    return () => {
      discoverRequestId.current += 1;
    };
  }, [loadDiscover]);

  useEffect(() => {
    loadHistory();
  }, [loadHistory]);

  useEffect(() => {
    let active = true;
    const timer = setTimeout(() => {
      const term = query.trim();
      if (term.length <= 1) {
        setCategorySuggestions([]);
        setProductSuggestions([]);
        setRecommendations(fallbackRecommendations);
        return;
      }

      refreshSuggestions(term).catch(() => {});
    }, 250);

    return () => {
      active = false;
      clearTimeout(timer);
    };
  }, [query]);

  const historyChips = useMemo(() => history.slice(0, 6), [history]);
  const recommendationChips = useMemo(() => recommendations, [recommendations]);
  const showCategorySuggestions = query.trim().length > 1 && categorySuggestions.length > 0;
  const showProductSuggestions = query.trim().length > 1 && productSuggestions.length > 0;
  const { refreshing, onRefresh } = usePullToRefresh(async () => {
    await Promise.all([loadDiscover(), loadHistory()]);
    if (query.trim().length > 1) {
      await refreshSuggestions(query);
    }
  });

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView
        contentContainerStyle={styles.content}
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={onRefresh}
            tintColor={theme.colors.primary}
            colors={[theme.colors.primary]}
          />
        }
      >
        <View style={styles.headerRow}>
          <Text style={styles.title}>Search</Text>
          <SearchBar
            placeholder="Search for products"
            value={query}
            onChangeText={setQuery}
            onClear={() => setQuery('')}
            onSubmitEditing={() => goToResults(query)}
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
            onPress={async () => {
              await clearSearchHistory();
              setHistory([]);
            }}
          />
        </View>
        <View style={styles.chipWrap}>
          {historyChips.map((chip) => (
            <Chip key={chip} label={chip} onPress={() => goToResults(chip)} />
          ))}
          {historyChips.length === 0 ? (
            <Text style={styles.emptyHint}>No recent searches yet.</Text>
          ) : null}
        </View>

        {showCategorySuggestions ? (
          <>
            <Text style={[styles.sectionTitle, styles.sectionSpacing]}>Category suggestions</Text>
            <View style={styles.chipWrap}>
              {categorySuggestions.map((category) => (
                <Chip
                  key={category.id}
                  label={category.name}
                  onPress={() =>
                    router.push(`/(tabs)/categories/results?category=${encodeURIComponent(category.slug || category.name)}`)
                  }
                />
              ))}
            </View>
          </>
        ) : recommendationChips.length > 0 ? (
          <>
            <Text style={[styles.sectionTitle, styles.sectionSpacing]}>Recommendations</Text>
            <View style={styles.chipWrap}>
              {recommendationChips.map((chip) => (
                <Chip key={chip} label={chip} onPress={() => goToResults(chip)} />
              ))}
            </View>
          </>
        ) : null}

        {showProductSuggestions ? (
          <>
            <Text style={[styles.sectionTitle, styles.sectionSpacing]}>Top products</Text>
            <FlatList
              horizontal
              data={productSuggestions}
              keyExtractor={(item) => item.id}
              showsHorizontalScrollIndicator={false}
              contentContainerStyle={styles.discoverRow}
              renderItem={({ item }) => (
                <ProductCard
                  title={item.name}
                  price={formatCurrency(item.price, item.currency, state.currency)}
                  width={theme.moderateScale(140)}
                  imageHeight={theme.moderateScale(120)}
                  image={item.image ? { uri: item.image } : undefined}
                  onPress={() => router.push(`/products/${item.slug}`)}
                />
              )}
            />
            {loadingSuggestions ? (
              <View style={styles.suggestionsLoading}>
                <Text style={styles.suggestionsText}>Loading suggestions...</Text>
              </View>
            ) : null}
          </>
        ) : null}

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
                price={formatCurrency(item.price, item.currency, state.currency)}
                width={theme.moderateScale(140)}
                imageHeight={theme.moderateScale(120)}
                image={item.image ? { uri: item.image } : undefined}
                onPress={() => router.push(`/products/${item.slug}`)}
              />
            );
          }}
        />
      </ScrollView>
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
  emptyHint: {
    fontSize: theme.moderateScale(12),
    color: theme.colors.mutedDark,
  },
  discoverRow: {
    paddingTop: theme.moderateScale(10),
    paddingBottom: theme.moderateScale(6),
    gap: theme.moderateScale(14),
  },
  suggestionsLoading: {
    marginTop: theme.moderateScale(8),
  },
  suggestionsText: {
    fontSize: theme.moderateScale(12),
    color: theme.colors.mutedDark,
  },
});
