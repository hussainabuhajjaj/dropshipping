import { Feather } from '@expo/vector-icons';
import { router, type Href, useLocalSearchParams } from 'expo-router';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Pressable, ScrollView, StyleSheet, Text, TextInput, View } from '@/src/utils/responsiveStyleSheet';
import { Chip } from '@/src/components/ui/Chip';
import { Skeleton } from '@/src/components/ui/Skeleton';
import { PrimaryButton } from '@/src/components/buttons/PrimaryButton';
import { fetchCategories } from '@/src/api/catalog';
import { useToast } from '@/src/overlays/ToastProvider';
import { theme } from '@/src/theme';
import type { Category } from '@/src/types/storefront';

type SortOption = { label: string; value: string };

const sortOptions: SortOption[] = [
  { label: 'Newest', value: 'newest' },
  { label: 'Price Low to High', value: 'price_asc' },
  { label: 'Price High to Low', value: 'price_desc' },
  { label: 'Top Rated', value: 'rating' },
  { label: 'Most Popular', value: 'popular' },
];

export default function ProductsFilterScreen() {
  const params = useLocalSearchParams();
  const returnTo = typeof params.returnTo === 'string' ? params.returnTo : '/products';
  const query = typeof params.q === 'string' ? params.q : '';
  const initialCategory = typeof params.category === 'string' ? params.category : '';
  const initialTitle = typeof params.title === 'string' ? params.title : '';
  const initialSort = typeof params.sort === 'string' ? params.sort : '';
  const initialMin = typeof params.min_price === 'string' ? params.min_price : '';
  const initialMax = typeof params.max_price === 'string' ? params.max_price : '';
  const [categories, setCategories] = useState<Category[]>([]);
  const [loadingCategories, setLoadingCategories] = useState(true);
  const [selectedCategory, setSelectedCategory] = useState(initialCategory);
  const [minPrice, setMinPrice] = useState(initialMin);
  const [maxPrice, setMaxPrice] = useState(initialMax);
  const [sort, setSort] = useState(initialSort);
  const [expanded, setExpanded] = useState<string[]>([]);
  const { show } = useToast();

  useEffect(() => {
    let active = true;
    setLoadingCategories(true);
    fetchCategories()
      .then((items) => {
        if (!active) return;
        setCategories(items);
      })
      .catch((err: any) => {
        if (!active) return;
        show({ type: 'error', message: err?.message ?? 'Unable to load categories.' });
        setCategories([]);
      })
      .finally(() => {
        if (active) setLoadingCategories(false);
      });

    return () => {
      active = false;
    };
  }, [show]);

  const categoryList = useMemo(() => {
    if (loadingCategories) {
      return Array.from({ length: 6 }, (_, index) => ({ id: `sk-${index}`, name: '' }));
    }
    return categories;
  }, [categories, loadingCategories]);

  const resolveCategoryTitle = useCallback(
    (value: string) => {
      const normalized = String(value || '').trim();
      if (!normalized) return '';

      for (const parent of categories) {
        const parentValue = parent.slug || parent.name;
        if (parentValue === normalized) return parent.name;
        const children = parent.children ?? [];
        for (const child of children) {
          const childValue = child.slug || child.name;
          if (childValue === normalized) return child.name;
        }
      }

      if (normalized === initialCategory && initialTitle.trim()) {
        return initialTitle.trim();
      }

      return '';
    },
    [categories, initialCategory, initialTitle]
  );

  const applyFilters = () => {
    const nextParams: Record<string, string> = {};
    if (query) nextParams.q = query;
    if (selectedCategory) {
      nextParams.category = selectedCategory;
      const title = resolveCategoryTitle(selectedCategory);
      if (title) nextParams.title = title;
    }
    const minValue = Number(minPrice);
    if (Number.isFinite(minValue) && minPrice.trim() !== '') nextParams.min_price = String(minValue);
    const maxValue = Number(maxPrice);
    if (Number.isFinite(maxValue) && maxPrice.trim() !== '') nextParams.max_price = String(maxValue);
    if (sort) nextParams.sort = sort;
    router.replace({ pathname: returnTo, params: nextParams } as Href);
  };

  const resetFilters = () => {
    setSelectedCategory('');
    setMinPrice('');
    setMaxPrice('');
    setSort('');
  };

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.headerRow}>
          <Pressable style={styles.iconButton} onPress={() => router.back()}>
            <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
          </Pressable>
          <Text style={styles.title}>Filters</Text>
          <Pressable style={styles.resetButton} onPress={resetFilters}>
            <Text style={styles.resetText}>Reset</Text>
          </Pressable>
        </View>

        <Text style={styles.sectionTitle}>Category</Text>
        <View style={styles.categoryList}>
          {categoryList.map((item: Category | { id: string; name: string }) => {
            const id = String(item.id);
            if (loadingCategories) {
              return (
                <View key={id} style={styles.categoryGroup}>
                  <Skeleton height={theme.moderateScale(18)} radius={theme.moderateScale(6)} width="60%" />
                  <Skeleton
                    height={theme.moderateScale(12)}
                    radius={theme.moderateScale(6)}
                    width="40%"
                    style={styles.skeletonGap}
                  />
                </View>
              );
            }

            const parent = item as Category;
            const isExpanded = expanded.includes(id);
            const children = parent.children ?? [];
            const parentValue = parent.slug || parent.name;

            return (
              <View key={id} style={styles.categoryGroup}>
                <Pressable
                  style={styles.parentRow}
                  onPress={() => {
                    setExpanded((prev) =>
                      prev.includes(id) ? prev.filter((value) => value !== id) : [...prev, id]
                    );
                  }}
                >
                  <Text style={styles.parentLabel}>{parent.name}</Text>
                  <Feather
                    name={isExpanded ? 'chevron-up' : 'chevron-down'}
                    size={theme.moderateScale(16)}
                    color={theme.colors.mutedDark}
                  />
                </Pressable>
                {isExpanded ? (
                  <View style={styles.childrenWrap}>
                    <Chip
                      label={`All ${parent.name}`}
                      active={selectedCategory === parentValue}
                      onPress={() =>
                        setSelectedCategory(selectedCategory === parentValue ? '' : parentValue)
                      }
                    />
                    {children.length === 0 ? (
                      <Text style={styles.emptyChildren}>No subcategories.</Text>
                    ) : (
                      children.map((child) => {
                        const value = child.slug || child.name;
                        const active = selectedCategory === value;
                        return (
                          <Chip
                            key={child.id}
                            label={child.name}
                            active={active}
                            onPress={() => setSelectedCategory(active ? '' : value)}
                          />
                        );
                      })
                    )}
                  </View>
                ) : null}
              </View>
            );
          })}
        </View>

        <Text style={styles.sectionTitle}>Price range</Text>
        <View style={styles.priceRow}>
          <View style={styles.priceField}>
            <Text style={styles.priceLabel}>Min</Text>
            <TextInput
              value={minPrice}
              onChangeText={setMinPrice}
              keyboardType="numeric"
              placeholder="0"
              placeholderTextColor={theme.colors.mutedLight}
              style={styles.priceInput}
            />
          </View>
          <View style={styles.priceField}>
            <Text style={styles.priceLabel}>Max</Text>
            <TextInput
              value={maxPrice}
              onChangeText={setMaxPrice}
              keyboardType="numeric"
              placeholder="500"
              placeholderTextColor={theme.colors.mutedLight}
              style={styles.priceInput}
            />
          </View>
        </View>

        <Text style={styles.sectionTitle}>Sort by</Text>
        <View style={styles.chipWrap}>
          {sortOptions.map((option) => (
            <Chip
              key={option.value}
              label={option.label}
              active={sort === option.value}
              onPress={() => setSort(sort === option.value ? '' : option.value)}
            />
          ))}
        </View>
      </ScrollView>

      <View style={styles.footer}>
        <PrimaryButton label="Apply filters" onPress={applyFilters} />
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
    paddingTop: theme.moderateScale(12),
    paddingBottom: theme.moderateScale(120),
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: theme.moderateScale(16),
  },
  iconButton: {
    width: theme.moderateScale(36),
    height: theme.moderateScale(36),
    borderRadius: theme.moderateScale(18),
    backgroundColor: theme.colors.sand,
    alignItems: 'center',
    justifyContent: 'center',
  },
  title: {
    fontSize: theme.moderateScale(18),
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  resetButton: {
    paddingHorizontal: theme.moderateScale(10),
    paddingVertical: theme.moderateScale(6),
    borderRadius: theme.moderateScale(12),
    backgroundColor: theme.colors.gray100,
  },
  resetText: {
    fontSize: theme.moderateScale(12),
    color: theme.colors.inkDark,
    fontWeight: '600',
  },
  sectionTitle: {
    marginTop: theme.moderateScale(10),
    fontSize: theme.moderateScale(14),
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  chipWrap: {
    marginTop: theme.moderateScale(10),
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: theme.moderateScale(10),
  },
  categoryList: {
    marginTop: theme.moderateScale(10),
    gap: theme.moderateScale(12),
  },
  categoryGroup: {
    padding: theme.moderateScale(12),
    borderRadius: theme.moderateScale(12),
    backgroundColor: theme.colors.gray100,
  },
  parentRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  parentLabel: {
    fontSize: theme.moderateScale(14),
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  childrenWrap: {
    marginTop: theme.moderateScale(10),
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: theme.moderateScale(8),
  },
  emptyChildren: {
    fontSize: theme.moderateScale(12),
    color: theme.colors.mutedDark,
  },
  skeletonGap: {
    marginTop: theme.moderateScale(8),
  },
  priceRow: {
    marginTop: theme.moderateScale(10),
    flexDirection: 'row',
    gap: theme.moderateScale(12),
  },
  priceField: {
    flex: 1,
  },
  priceLabel: {
    fontSize: theme.moderateScale(12),
    color: theme.colors.mutedDark,
    marginBottom: theme.moderateScale(6),
  },
  priceInput: {
    height: theme.moderateScale(40),
    borderRadius: theme.moderateScale(12),
    borderWidth: 1,
    borderColor: theme.colors.border,
    paddingHorizontal: theme.moderateScale(12),
    fontSize: theme.moderateScale(13),
    color: theme.colors.inkDark,
    backgroundColor: theme.colors.white,
  },
  footer: {
    position: 'absolute',
    left: theme.moderateScale(20),
    right: theme.moderateScale(20),
    bottom: theme.moderateScale(24),
  },
});
