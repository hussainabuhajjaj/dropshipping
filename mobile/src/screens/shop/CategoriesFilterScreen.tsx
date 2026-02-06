import { Feather } from '@expo/vector-icons';
import { useEffect, useMemo, useState } from 'react';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Image, Pressable, ScrollView, StyleSheet, View, useWindowDimensions } from 'react-native';
import { Text } from '@/src/components/i18n/Text';
import { router } from 'expo-router';
import { CategorySearchBar } from '@/src/components/ui/CategorySearchBar';
import { Skeleton } from '@/src/components/ui/Skeleton';
import { theme } from '@/src/theme';
import { fetchCategories } from '@/src/api/catalog';
import { useToast } from '@/src/overlays/ToastProvider';
import type { Category } from '@/src/types/storefront';

export default function CategoriesFilterScreen() {
  const { width } = useWindowDimensions();
  const { show } = useToast();
  const [categories, setCategories] = useState<Category[]>([]);
  const [activeCategory, setActiveCategory] = useState<string>('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const horizontalPadding = theme.moderateScale(16);
  const columnGap = theme.moderateScale(12);
  const leftColumnWidth = Math.min(
    theme.moderateScale(150),
    Math.max(theme.moderateScale(110), width * 0.34),
  );
  const rightColumnWidth = Math.max(width - horizontalPadding * 2 - leftColumnWidth - columnGap, 0);
  const tileWidth = Math.max((rightColumnWidth - columnGap * 2) / 3, theme.moderateScale(70));
  const skeletonCategories = useMemo(
    () =>
      Array.from({ length: 8 }, (_, index) => ({
        id: `skeleton-${index}`,
        name: '',
        slug: '',
        count: 0,
        image: null,
        accent: null,
      })),
    []
  );
  const listCategories = loading ? skeletonCategories : categories;
  const activeParent = useMemo(() => {
    if (loading) return null;
    return categories.find((item) => String(item.id) === activeCategory) ?? categories[0] ?? null;
  }, [activeCategory, categories, loading]);
  const activeChildren = useMemo(() => {
    if (loading) return skeletonCategories;
    return activeParent?.children ?? [];
  }, [activeParent, loading, skeletonCategories]);
  const featuredWithImages = useMemo(() => {
    const source = loading ? skeletonCategories : activeChildren;
    return source.map((item, index) => ({
      id: item.id,
      label: item.name,
      slug: item.slug,
      image: item.image ?? null,
      hot: !loading && (index < 3 || item.count > 50),
    }));
  }, [activeChildren, loading, skeletonCategories]);

  useEffect(() => {
    let active = true;
    setLoading(true);
    setError(null);
    fetchCategories()
      .then((data) => {
        if (!active) return;
        setCategories(data);
        setActiveCategory((prev) => {
          if (prev && data.some((item) => String(item.id) === prev)) return prev;
          return data[0]?.id ? String(data[0].id) : '';
        });
      })
      .catch((err: any) => {
        const message = err?.message ?? 'Unable to load categories.';
        setError(message);
        show({ type: 'error', message });
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => {
      active = false;
    };
  }, [show]);

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView
        contentContainerStyle={[styles.content, { paddingHorizontal: horizontalPadding }]}
        showsVerticalScrollIndicator={false}
      >
        <CategorySearchBar
          placeholder="office backpack for men"
          onPress={() => router.push('/search')}
          onCameraPress={() => router.push('/image-search')}
          onSearchPress={() => router.push('/search')}
        />

        <Pressable style={styles.perksRow} onPress={() => router.push('/shipping')}>
          <View style={styles.perkItem}>
            <Feather name="check" size={14} color={theme.colors.green} />
            <Text style={styles.perkText}>Fast Shipping</Text>
          </View>
          <View style={styles.perkDivider} />
          <View style={[styles.perkItem, styles.perkItemWide]}>
            <Feather name="check" size={14} color={theme.colors.green} />
            <Text style={styles.perkText}>Price adjustment within 30 days</Text>
          </View>
          <Feather name="chevron-right" size={16} color={theme.colors.muted} />
        </Pressable>

        <View style={styles.sectionHeaderRow}>
          <View style={[styles.sectionTitleWrap, { width: leftColumnWidth }]}>
            <View style={styles.sectionIndicator} />
            <Text style={styles.sectionTitle}>Featured</Text>
          </View>
          <Text style={styles.sectionTitle}>Shop by category</Text>
        </View>

        <View style={[styles.columnsRow, { gap: columnGap }]}>
          <View style={[styles.leftColumn, { width: leftColumnWidth }]}>
            {listCategories.map((item) => {
              const isActive = String(item.id) === activeCategory;
              return (
                <Pressable
                  key={item.id}
                  style={[styles.leftItem, isActive ? styles.leftItemActive : null]}
                  onPress={loading ? undefined : () => setActiveCategory(String(item.id))}
                >
                  <View style={[styles.leftIndicator, isActive ? styles.leftIndicatorActive : null]} />
                  {loading ? (
                    <Skeleton height={theme.moderateScale(12)} radius={theme.moderateScale(6)} width="70%" />
                  ) : (
                    <Text style={[styles.leftText, isActive ? styles.leftTextActive : null]}>{item.name}</Text>
                  )}
                </Pressable>
              );
            })}
          </View>

          <View style={styles.rightColumn}>
            {!loading && error ? (
              <View style={styles.statusRow}>
                <Text style={styles.statusText}>{error}</Text>
              </View>
            ) : null}
            {!loading && !error && categories.length === 0 ? (
              <View style={styles.statusRow}>
                <Text style={styles.statusText}>No categories available.</Text>
              </View>
            ) : null}
            {!loading && categories.length > 0 && activeChildren.length === 0 ? (
              <View style={styles.statusRow}>
                <Text style={styles.statusText}>No subcategories available.</Text>
              </View>
            ) : null}
            <View style={[styles.grid, { gap: columnGap }]}>
              {featuredWithImages.map((item, index) => {
                const imageSize = Math.min(tileWidth, theme.moderateScale(78));
                return (
                  <Pressable
                    key={item.id}
                    style={[styles.tile, { width: tileWidth }]}
                    disabled={loading}
                    onPress={() => {
                      const category = item.slug || item.label;
                      if (!category) return;
                      router.push({
                        pathname: '/(tabs)/categories/results',
                        params: {
                          category,
                          title: item.label || undefined,
                        },
                      });
                    }}
                  >
                    <View style={[styles.tileImageWrap, { width: imageSize, height: imageSize, borderRadius: imageSize / 2 }]}>
                      {loading ? (
                        <Skeleton height={imageSize} width={imageSize} radius={imageSize / 2} />
                      ) : item.image ? (
                        <Image source={{ uri: item.image }} style={styles.tileImage} />
                      ) : (
                        <View style={styles.tileImageFallback} />
                      )}
                      {item.hot ? (
                        <View style={styles.hotBadge}>
                          <Text style={styles.hotText}>HOT</Text>
                        </View>
                      ) : null}
                    </View>
                    {loading ? (
                      <Skeleton height={theme.moderateScale(10)} radius={theme.moderateScale(5)} width="80%" />
                    ) : (
                      <Text style={styles.tileLabel} numberOfLines={2}>
                        {item.label}
                      </Text>
                    )}
                  </Pressable>
                );
              })}
            </View>
          </View>
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
  content: {
    paddingTop: theme.moderateScale(12),
    paddingBottom: theme.moderateScale(24),
  },
  perksRow: {
    marginTop: theme.moderateScale(14),
    backgroundColor: theme.colors.primarySoftLight,
    borderRadius: theme.moderateScale(10),
    paddingHorizontal: theme.moderateScale(10),
    paddingVertical: theme.moderateScale(8),
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.moderateScale(8),
  },
  perkItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.moderateScale(6),
  },
  perkItemWide: {
    flex: 1,
  },
  perkText: {
    fontSize: theme.moderateScale(11),
    color: theme.colors.ink,
    fontWeight: '600',
  },
  perkDivider: {
    width: 1,
    height: theme.moderateScale(16),
    backgroundColor: theme.colors.border,
  },
  sectionHeaderRow: {
    marginTop: theme.moderateScale(16),
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  sectionTitleWrap: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.moderateScale(6),
  },
  sectionIndicator: {
    width: theme.moderateScale(4),
    height: theme.moderateScale(16),
    borderRadius: theme.moderateScale(2),
    backgroundColor: theme.colors.primary,
  },
  sectionTitle: {
    fontSize: theme.moderateScale(14),
    fontWeight: '700',
    color: theme.colors.ink,
  },
  columnsRow: {
    marginTop: theme.moderateScale(10),
    flexDirection: 'row',
    alignItems: 'flex-start',
  },
  leftColumn: {
    backgroundColor: theme.colors.gray200,
    borderRadius: theme.moderateScale(12),
    paddingVertical: theme.moderateScale(8),
  },
  leftItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.moderateScale(8),
    paddingVertical: theme.moderateScale(10),
    paddingHorizontal: theme.moderateScale(8),
  },
  leftItemActive: {
    backgroundColor: theme.colors.white,
  },
  leftIndicator: {
    width: theme.moderateScale(4),
    height: theme.moderateScale(18),
    borderRadius: theme.moderateScale(2),
    backgroundColor: 'transparent',
  },
  leftIndicatorActive: {
    backgroundColor: theme.colors.primary,
  },
  leftText: {
    flex: 1,
    fontSize: theme.moderateScale(12),
    color: theme.colors.ink,
    fontWeight: '600',
  },
  leftTextActive: {
    color: theme.colors.inkDark,
  },
  rightColumn: {
    flex: 1,
  },
  grid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'flex-start',
  },
  tile: {
    alignItems: 'center',
    gap: theme.moderateScale(6),
  },
  tileImageWrap: {
    backgroundColor: theme.colors.gray200,
    alignItems: 'center',
    justifyContent: 'center',
    overflow: 'hidden',
  },
  tileImage: {
    width: '100%',
    height: '100%',
  },
  tileImageFallback: {
    width: '100%',
    height: '100%',
    backgroundColor: theme.colors.primarySoft,
  },
  tileLabel: {
    fontSize: theme.moderateScale(11),
    color: theme.colors.ink,
    textAlign: 'center',
    fontWeight: '600',
  },
  hotBadge: {
    position: 'absolute',
    right: theme.moderateScale(-2),
    top: theme.moderateScale(-2),
    backgroundColor: theme.colors.orange,
    borderRadius: theme.moderateScale(8),
    paddingHorizontal: theme.moderateScale(6),
    paddingVertical: theme.moderateScale(2),
  },
  hotText: {
    fontSize: theme.moderateScale(9),
    color: theme.colors.white,
    fontWeight: '700',
  },
  statusRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.moderateScale(8),
    paddingVertical: theme.moderateScale(8),
  },
  statusText: {
    fontSize: theme.moderateScale(12),
    color: theme.colors.mutedDark,
  },
});
