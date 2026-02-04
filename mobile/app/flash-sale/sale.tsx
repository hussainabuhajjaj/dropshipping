import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useEffect, useMemo, useState } from 'react';
import { Image, Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { Skeleton } from '@/src/components/ui/Skeleton';
import { fetchProducts } from '@/src/api/catalog';
import { useToast } from '@/src/overlays/ToastProvider';
import { theme } from '@/src/theme';
import type { Product } from '@/src/types/storefront';

export default function FlashSaleListScreen() {
  const { show } = useToast();
  const [items, setItems] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const listItems = useMemo(() => {
    if (loading) {
      return Array.from({ length: 4 }, (_, index) => ({ id: `sk-${index}`, skeleton: true }));
    }
    return items;
  }, [items, loading]);

  useEffect(() => {
    let active = true;
    setLoading(true);
    setError(null);
    fetchProducts({ per_page: 8 })
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
        <Pressable style={styles.iconButton} onPress={() => router.push('/flash-sale/full')}>
          <Feather name="filter" size={16} color={theme.colors.inkDark} />
        </Pressable>
      </View>

      <View style={styles.timerRow}>
        <Text style={styles.sectionTitle}>Sale ends in</Text>
        <View style={styles.timer}>
          <View style={styles.timerChip}>
            <Text style={styles.timerText}>00</Text>
          </View>
          <View style={styles.timerChip}>
            <Text style={styles.timerText}>58</Text>
          </View>
          <View style={styles.timerChip}>
            <Text style={styles.timerText}>26</Text>
          </View>
        </View>
      </View>

      <View style={styles.list}>
        {listItems.map((item) => {
          if ('skeleton' in item) {
            return (
              <View key={item.id} style={styles.card}>
                <Skeleton height={theme.moderateScale(110)} width={theme.moderateScale(90)} radius={theme.moderateScale(16)} />
                <View style={styles.cardBody}>
                  <Skeleton height={theme.moderateScale(12)} radius={theme.moderateScale(6)} width="70%" />
                  <Skeleton height={theme.moderateScale(12)} radius={theme.moderateScale(6)} width="40%" />
                  <View style={styles.cardMeta}>
                    <Skeleton height={theme.moderateScale(18)} radius={theme.moderateScale(9)} width={theme.moderateScale(60)} />
                    <Skeleton height={theme.moderateScale(10)} radius={theme.moderateScale(5)} width={theme.moderateScale(48)} />
                  </View>
                </View>
              </View>
            );
          }

          const image = item.image || item.media?.[0];
          const hasCompare = item.compareAt && item.compareAt > item.price;
          const discount = hasCompare ? Math.round(100 - (item.price / (item.compareAt as number)) * 100) : null;
          const badgeText = discount ? `${discount}% off` : item.badge ?? 'Deal';
          const stock = item.variants?.find((variant) => typeof variant.stock_on_hand === 'number')?.stock_on_hand;
          const leftText = typeof stock === 'number' ? `${stock} left` : 'Limited stock';

          return (
            <Pressable key={item.id} style={styles.card} onPress={() => router.push(`/products/${item.slug}`)}>
              <View style={styles.cardImage}>
                {image ? (
                  <Image source={{ uri: image }} style={styles.cardImageFill} />
                ) : (
                  <View style={styles.cardImageFill} />
                )}
              </View>
              <View style={styles.cardBody}>
                <Text style={styles.cardTitle}>{item.name}</Text>
                <Text style={styles.cardPrice}>${item.price.toFixed(2)}</Text>
                <View style={styles.cardMeta}>
                  <View style={styles.badge}>
                    <Text style={styles.badgeText}>{badgeText}</Text>
                  </View>
                  <Text style={styles.leftText}>{leftText}</Text>
                </View>
              </View>
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

      <Pressable style={styles.primaryButton} onPress={() => router.push('/flash-sale/full')}>
        <Text style={styles.primaryText}>View full flash sale</Text>
      </Pressable>
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
  timerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 16,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  timer: {
    flexDirection: 'row',
    gap: 8,
  },
  timerChip: {
    backgroundColor: theme.colors.sand,
    borderRadius: 12,
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  timerText: {
    fontSize: 12,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  list: {
    gap: 12,
  },
  card: {
    flexDirection: 'row',
    gap: 14,
    padding: 14,
    borderRadius: 18,
    backgroundColor: theme.colors.sand,
  },
  cardImage: {
    width: 90,
    height: 110,
    borderRadius: 16,
    backgroundColor: '#e2e6f5',
    overflow: 'hidden',
  },
  cardImageFill: {
    width: '100%',
    height: '100%',
    borderRadius: 16,
    backgroundColor: theme.colors.gray200,
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
    marginTop: 4,
    fontSize: 14,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  cardMeta: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  badge: {
    backgroundColor: theme.colors.rose,
    borderRadius: 12,
    paddingHorizontal: 8,
    paddingVertical: 4,
  },
  badgeText: {
    fontSize: 10,
    fontWeight: '700',
    color: theme.colors.white,
  },
  leftText: {
    fontSize: 11,
    color: theme.colors.mutedDark,
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
  primaryButton: {
    marginTop: 20,
    backgroundColor: theme.colors.sun,
    borderRadius: 24,
    paddingVertical: 14,
    alignItems: 'center',
  },
  primaryText: {
    fontSize: 14,
    fontWeight: '700',
    color: theme.colors.white,
  },
});
