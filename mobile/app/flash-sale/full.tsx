import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useEffect, useMemo, useState } from 'react';
import { Image, Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { Skeleton } from '@/src/components/ui/Skeleton';
import { fetchProducts } from '@/src/api/catalog';
import { useToast } from '@/src/overlays/ToastProvider';
import { theme } from '@/src/theme';
import type { Product } from '@/src/types/storefront';

const filters = ['All', 'Dresses', 'Tops', 'Bottoms', 'Shoes'];

export default function FlashSaleFullScreen() {
  const { show } = useToast();
  const [items, setItems] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const listItems = useMemo(() => {
    if (loading) {
      return Array.from({ length: 6 }, (_, index) => ({ id: `sk-${index}`, skeleton: true }));
    }
    return items;
  }, [items, loading]);

  useEffect(() => {
    let active = true;
    setLoading(true);
    setError(null);
    fetchProducts({ per_page: 12 })
      .then(({ items: payload }) => {
        if (!active) return;
        const saleItems = payload.filter((item) => item.compareAt && item.compareAt > item.price);
        setItems(saleItems.length > 0 ? saleItems : payload);
      })
      .catch((err: any) => {
        if (!active) return;
        const message = err?.message ?? 'Unable to load flash sale items.';
        setError(message);
        show({ type: 'error', message });
        setItems([]);
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => {
      active = false;
    };
  }, [show]);

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
      <View style={styles.headerRow}>
        <Pressable style={styles.iconButton} onPress={() => router.back()}>
          <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
        </Pressable>
        <Text style={styles.title}>Flash Sale</Text>
        <Pressable style={styles.iconButton} onPress={() => router.push('/flash-sale/live')}>
          <Feather name="video" size={16} color={theme.colors.inkDark} />
        </Pressable>
      </View>

      <View style={styles.sortRow}>
        <Text style={styles.sortLabel}>Sort by: Popular</Text>
        <Pressable style={styles.sortButton} onPress={() => router.push('/shop/categories-filter')}>
          <Feather name="sliders" size={14} color={theme.colors.inkDark} />
          <Text style={styles.sortText}>Filter</Text>
        </Pressable>
      </View>

      <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.filterRow}>
        {filters.map((filter, index) => (
          <View key={filter} style={[styles.filterChip, index === 0 ? styles.filterActive : null]}>
            <Text style={[styles.filterText, index === 0 ? styles.filterTextActive : null]}>{filter}</Text>
          </View>
        ))}
      </ScrollView>

      <View style={styles.grid}>
        {listItems.map((item) => {
          if ('skeleton' in item) {
            return (
              <View key={item.id} style={styles.card}>
                <Skeleton height={theme.moderateScale(120)} radius={theme.moderateScale(14)} />
                <Skeleton height={theme.moderateScale(10)} radius={theme.moderateScale(5)} width="75%" />
                <Skeleton height={theme.moderateScale(12)} radius={theme.moderateScale(6)} width="45%" />
              </View>
            );
          }

          const image = item.image || item.media?.[0];
          const hasCompare = item.compareAt && item.compareAt > item.price;
          const discount = hasCompare ? Math.round(100 - (item.price / (item.compareAt as number)) * 100) : null;
          const badgeText = discount ? `-${discount}%` : item.badge ?? null;

          return (
            <Pressable key={item.id} style={styles.card} onPress={() => router.push(`/products/${item.slug}`)}>
              <View style={styles.cardImage}>
                {image ? (
                  <Image source={{ uri: image }} style={styles.cardImageFill} />
                ) : (
                  <View style={styles.cardImageFill} />
                )}
              </View>
              {badgeText ? (
                <View style={styles.discountBadge}>
                  <Text style={styles.discountText}>{badgeText}</Text>
                </View>
              ) : null}
              <Text style={styles.cardTitle} numberOfLines={1}>
                {item.name}
              </Text>
              <Text style={styles.cardPrice}>${item.price.toFixed(2)}</Text>
            </Pressable>
          );
        })}
      </View>
      {!loading && items.length === 0 ? (
        <View style={styles.emptyCard}>
          <Text style={styles.emptyTitle}>{error ?? 'No flash sale items yet.'}</Text>
          <Text style={styles.emptyBody}>Check back soon for new deals.</Text>
        </View>
      ) : null}
    </ScrollView>
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
  sortRow: {
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
  filterRow: {
    marginBottom: 16,
  },
  filterChip: {
    marginRight: 10,
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 18,
    backgroundColor: theme.colors.sand,
  },
  filterActive: {
    backgroundColor: theme.colors.sun,
  },
  filterText: {
    fontSize: 12,
    fontWeight: '600',
    color: theme.colors.inkDark,
  },
  filterTextActive: {
    color: theme.colors.white,
  },
  grid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
  },
  card: {
    width: '48%',
    backgroundColor: theme.colors.white,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: theme.colors.sand,
    padding: 10,
  },
  cardImage: {
    height: 120,
    borderRadius: 14,
    backgroundColor: theme.colors.gray200,
    marginBottom: 10,
    overflow: 'hidden',
  },
  cardImageFill: {
    width: '100%',
    height: '100%',
    borderRadius: 14,
    backgroundColor: theme.colors.gray200,
  },
  discountBadge: {
    position: 'absolute',
    top: 18,
    left: 18,
    backgroundColor: theme.colors.rose,
    borderRadius: 10,
    paddingHorizontal: 6,
    paddingVertical: 4,
  },
  discountText: {
    fontSize: 10,
    color: theme.colors.white,
    fontWeight: '700',
  },
  cardTitle: {
    fontSize: 12,
    color: theme.colors.inkDark,
    fontWeight: '600',
  },
  cardPrice: {
    marginTop: 4,
    fontSize: 13,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  emptyCard: {
    marginTop: 20,
    padding: 18,
    borderRadius: 18,
    backgroundColor: theme.colors.sand,
    alignItems: 'center',
  },
  emptyTitle: {
    fontSize: 14,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  emptyBody: {
    marginTop: 6,
    fontSize: 12,
    color: theme.colors.mutedDark,
    textAlign: 'center',
  },
});
