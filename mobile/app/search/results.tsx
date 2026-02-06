import { Feather } from '@expo/vector-icons';
import { router, useLocalSearchParams, usePathname } from 'expo-router';
import { useEffect, useState } from 'react';
import { Image, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from '@/src/utils/responsiveStyleSheet';
import { Skeleton } from '@/src/components/ui/Skeleton';
import { searchRequest } from '@/src/api/search';
import { theme } from '@/src/theme';
import { useToast } from '@/src/overlays/ToastProvider';
import type { Product } from '@/src/types/storefront';
import { addSearchHistory } from '@/src/lib/searchHistory';
import { KeyboardAvoidingView, Platform } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
export default function SearchResultsScreen() {
  const params = useLocalSearchParams();
  const query = typeof params.query === 'string' ? params.query : '';
  const category = typeof params.category === 'string' ? params.category : '';
  const minPriceParam = typeof params.min_price === 'string' ? Number(params.min_price) : undefined;
  const maxPriceParam = typeof params.max_price === 'string' ? Number(params.max_price) : undefined;
  const sortParam = typeof params.sort === 'string' ? params.sort : '';
  const { show } = useToast();
  const [items, setItems] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState(query);
  const pathname = usePathname();

  useEffect(() => {
    setSearchTerm(query);
  }, [query]);

  useEffect(() => {
    if (query.trim().length === 0) return;
    addSearchHistory(query);
  }, [query]);

  useEffect(() => {
    let active = true;
    const hasFilters =
      query.trim().length > 0 ||
      category.length > 0 ||
      Number.isFinite(minPriceParam) ||
      Number.isFinite(maxPriceParam);
    if (!hasFilters) {
      setItems([]);
      setLoading(false);
      return () => {
        active = false;
      };
    }

    setLoading(true);
    searchRequest({
      q: query || undefined,
      category: category || undefined,
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
        show({ type: 'error', message: err?.message ?? 'Unable to load search results.' });
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
      <KeyboardAvoidingView
        style={styles.keyboard}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        keyboardVerticalOffset={Platform.OS === 'ios' ? theme.moderateScale(20) : 0}
      >
        <ScrollView
          style={styles.scroll}
          contentContainerStyle={styles.content}
          showsVerticalScrollIndicator={false}
          keyboardShouldPersistTaps="handled"
          keyboardDismissMode="interactive"
          automaticallyAdjustKeyboardInsets
        >
          <View style={styles.header}>
            <Text style={styles.title}>Shop</Text>
            <View style={styles.searchRow}>
              <View style={styles.searchInput}>
                <TextInput
                  style={styles.searchText}
                  value={searchTerm}
                  onChangeText={setSearchTerm}
                  placeholder="Search"
                  placeholderTextColor="#8f97a4"
                  returnKeyType="search"
                  onSubmitEditing={() => {
                    const trimmed = searchTerm.trim();
                    if (!trimmed) return;
                    if (trimmed === query) return;
                    router.replace({
                      pathname: '/search/results',
                      params: {
                        query: trimmed,
                        category: category || undefined,
                        min_price: Number.isFinite(minPriceParam) ? String(minPriceParam) : undefined,
                        max_price: Number.isFinite(maxPriceParam) ? String(maxPriceParam) : undefined,
                        sort: sortParam || undefined,
                      },
                    });
                  }}
                />
                <Pressable
                  onPress={() => {
                    if (searchTerm.trim().length === 0) {
                      router.back();
                      return;
                    }
                    setSearchTerm('');
                  }}
                >
                  <Feather name="x" size={14} color="#0042e0" />
                </Pressable>
                <Pressable onPress={() => router.push('/image-search')}>
                  <Feather name="image" size={16} color="#0042e0" />
                </Pressable>
              </View>
              <Pressable
                style={styles.filterButton}
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
              >
                <Feather name="sliders" size={14} color={theme.colors.inkDark} />
              </Pressable>
            </View>
          </View>

          <View style={styles.grid}>
            {loading
              ? Array.from({ length: 6 }, (_, index) => (
                  <View key={`sk-${index}`} style={styles.card}>
                    <Skeleton height={181} radius={16} />
                    <Skeleton height={10} radius={5} style={styles.skeletonGap} />
                    <Skeleton height={12} radius={6} width="40%" style={styles.skeletonGap} />
                  </View>
                ))
              : items.map((item) => (
                  <Pressable
                    key={`${item.id}-${item.price}`}
                    style={styles.card}
                    onPress={() => router.push(`/products/${item.slug}`)}
                  >
                    {item.image ? (
                      <Image source={{ uri: item.image }} style={styles.cardImage} />
                    ) : (
                      <View style={styles.cardImageFallback} />
                    )}
                    <Text style={styles.cardTitle} numberOfLines={2}>
                      {item.name}
                    </Text>
                    <Text style={styles.cardPrice}>${item.price.toFixed(2)}</Text>
                  </Pressable>
                ))}
            {!loading && items.length === 0 ? (
              <View style={styles.emptyCard}>
                <Text style={styles.emptyTitle}>
                  {query.trim().length > 0 ? 'No matches found' : 'Start searching'}
                </Text>
                <Text style={styles.emptyBody}>
                  {query.trim().length > 0
                    ? 'Try another keyword or adjust filters.'
                    : 'Enter a keyword to explore products.'}
                </Text>
              </View>
            ) : null}
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.white,
  },
  keyboard: {
    flex: 1,
  },
  scroll: {
    flex: 1,
  },
  content: {
    paddingHorizontal: 20,
    paddingBottom: 24,
  },
  header: {
    paddingTop: 10,
    paddingBottom: 14,
  },
  title: {
    fontSize: 22,
    fontWeight: '700',
    color: theme.colors.inkDark,
    marginBottom: 10,
  },
  searchRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  searchInput: {
    flex: 1,
    height: 36,
    borderRadius: 18,
    backgroundColor: theme.colors.gray100,
    paddingHorizontal: 12,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  searchText: {
    flex: 1,
    fontSize: 14,
    color: '#0042e0',
    fontWeight: '600',
  },
  filterButton: {
    width: 25,
    height: 25,
    borderRadius: 8,
    backgroundColor: theme.colors.white,
    borderWidth: 1,
    borderColor: theme.colors.sand,
    alignItems: 'center',
    justifyContent: 'center',
  },
  grid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
    rowGap: 18,
  },
  card: {
    width: '48%',
  },
  cardImage: {
    width: '100%',
    height: 181,
    borderRadius: 16,
    backgroundColor: theme.colors.gray200,
  },
  cardTitle: {
    marginTop: 8,
    fontSize: 12,
    color: theme.colors.black,
  },
  cardPrice: {
    marginTop: 4,
    fontSize: 14,
    color: theme.colors.inkDark,
    fontWeight: '600',
  },
  skeletonGap: {
    marginTop: 8,
  },
  cardImageFallback: {
    width: '100%',
    height: 181,
    borderRadius: 16,
    backgroundColor: theme.colors.gray200,
  },
  emptyCard: {
    width: '100%',
    padding: 16,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: theme.colors.sand,
    backgroundColor: theme.colors.white,
  },
  emptyTitle: {
    fontSize: 13,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  emptyBody: {
    marginTop: 6,
    fontSize: 12,
    color: theme.colors.mutedDark,
  },
});
