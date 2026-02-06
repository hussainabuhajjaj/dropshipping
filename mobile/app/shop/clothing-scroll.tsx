import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useEffect, useState } from 'react';
import { Image, Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { Skeleton } from '@/src/components/ui/Skeleton';
import { fetchProducts } from '@/src/api/catalog';
import { theme } from '@/src/theme';
import { useToast } from '@/src/overlays/ToastProvider';
import type { Product } from '@/src/types/storefront';
import { SafeAreaView } from 'react-native-safe-area-context';

export default function ShopClothingScrollScreen() {
  const { show } = useToast();
  const [items, setItems] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);
  const category = 'clothing';

  useEffect(() => {
    let active = true;
    setLoading(true);
    fetchProducts({ category, per_page: 12 })
      .then(({ items: payload }) => {
        if (!active) return;
        setItems(payload);
      })
      .catch((err: any) => {
        if (!active) return;
        show({ type: 'error', message: err?.message ?? 'Unable to load clothing items.' });
        setItems([]);
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => {
      active = false;
    };
  }, [category, show]);

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView style={styles.scroll} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
      <View style={styles.headerRow}>
        <Pressable style={styles.iconButton} onPress={() => router.back()}>
          <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
        </Pressable>
        <Text style={styles.title}>Clothing</Text>
        <Pressable style={styles.iconButton} onPress={() => router.push('/shop/categories-filter')}>
          <Feather name="filter" size={16} color={theme.colors.inkDark} />
        </Pressable>
      </View>

      <View style={styles.heroCard}>
        <View>
          <Text style={styles.heroTitle}>Casual edit</Text>
          <Text style={styles.heroSubtitle}>Soft colors, easy fits</Text>
        </View>
        <Pressable style={styles.heroButton} onPress={() => router.push('/flash-sale')}>
          <Text style={styles.heroButtonText}>Shop</Text>
        </Pressable>
      </View>

      <View style={styles.sortRow}>
        <Text style={styles.sortLabel}>Sort by: New in</Text>
        <Pressable style={styles.sortButton} onPress={() => router.push('/shop/categories-filter')}>
          <Feather name="sliders" size={14} color={theme.colors.inkDark} />
          <Text style={styles.sortText}>Filter</Text>
        </Pressable>
      </View>

      <View style={styles.list}>
        {loading
          ? Array.from({ length: 6 }, (_, index) => (
              <View key={`sk-${index}`} style={styles.card}>
                <Skeleton width={96} height={120} radius={16} />
                <View style={styles.cardBody}>
                  <Skeleton width="80%" height={12} />
                  <Skeleton width="40%" height={12} style={styles.skeletonGap} />
                  <View style={styles.cardMeta}>
                    <Skeleton width={30} height={10} />
                    <Skeleton width={50} height={10} />
                  </View>
                </View>
              </View>
            ))
          : items.map((item) => (
              <Pressable key={item.id} style={styles.card} onPress={() => router.push(`/products/${item.slug}`)}>
                {item.image ? (
                  <Image source={{ uri: item.image }} style={styles.cardImage} />
                ) : (
                  <View style={styles.cardImageFallback} />
                )}
                <View style={styles.cardBody}>
                  <Text style={styles.cardTitle} numberOfLines={2}>
                    {item.name}
                  </Text>
                  <Text style={styles.cardPrice}>${item.price.toFixed(2)}</Text>
                  <View style={styles.cardMeta}>
                    <Text style={styles.metaText}>{item.rating.toFixed(1)}</Text>
                    <Text style={styles.metaText}>{item.reviews} reviews</Text>
                  </View>
                </View>
              </Pressable>
            ))}
        {!loading && items.length === 0 ? (
          <View style={styles.emptyCard}>
            <Text style={styles.emptyTitle}>No items yet</Text>
            <Text style={styles.emptyBody}>Try another category or check back soon.</Text>
          </View>
        ) : null}
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
    marginBottom: 16,
  },
  title: {
    fontSize: 20,
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
  heroCard: {
    borderRadius: 20,
    padding: 18,
    backgroundColor: '#e7efff',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  heroTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  heroSubtitle: {
    marginTop: 6,
    fontSize: 12,
    color: theme.colors.mutedDark,
  },
  heroButton: {
    backgroundColor: theme.colors.sun,
    borderRadius: 16,
    paddingHorizontal: 14,
    paddingVertical: 8,
  },
  heroButtonText: {
    fontSize: 12,
    fontWeight: '700',
    color: theme.colors.white,
  },
  sortRow: {
    marginTop: 16,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 12,
  },
  sortLabel: {
    fontSize: 13,
    color: theme.colors.mutedDark,
  },
  sortButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: theme.colors.sand,
    borderRadius: 14,
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  sortText: {
    fontSize: 12,
    fontWeight: '600',
    color: theme.colors.inkDark,
  },
  list: {
    gap: 12,
  },
  card: {
    flexDirection: 'row',
    gap: 14,
    padding: 12,
    borderRadius: 18,
    backgroundColor: theme.colors.sand,
  },
  cardImage: {
    width: 96,
    height: 120,
    borderRadius: 16,
    backgroundColor: theme.colors.sand,
  },
  cardImageFallback: {
    width: 96,
    height: 120,
    borderRadius: 16,
    backgroundColor: theme.colors.sand,
  },
  cardBody: {
    flex: 1,
    justifyContent: 'space-between',
  },
  cardTitle: {
    fontSize: 14,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  cardPrice: {
    marginTop: 6,
    fontSize: 14,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  cardMeta: {
    flexDirection: 'row',
    gap: 12,
    marginTop: 6,
  },
  metaText: {
    fontSize: 11,
    color: theme.colors.mutedDark,
  },
  skeletonGap: {
    marginTop: 6,
  },
  emptyCard: {
    padding: 16,
    borderRadius: 18,
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
