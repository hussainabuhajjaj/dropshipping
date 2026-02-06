import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useEffect, useState } from 'react';
import { Image, Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { Skeleton } from '@/src/components/ui/Skeleton';
import { fetchProductsBySlugs } from '@/src/api/catalog';
import { useRecentlyViewed } from '@/lib/recentlyViewedStore';
import { formatCurrency } from '@/src/lib/formatCurrency';
import { theme } from '@/src/theme';
import { useToast } from '@/src/overlays/ToastProvider';
import type { Product } from '@/src/types/storefront';
import { SafeAreaView } from 'react-native-safe-area-context';
import { usePreferences } from '@/src/store/preferencesStore';
export default function RecentlyViewedScreen() {
  const { slugs } = useRecentlyViewed();
  const { show } = useToast();
  const { state } = usePreferences();
  const [items, setItems] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let active = true;
    setLoading(true);
    if (slugs.length === 0) {
      setItems([]);
      setLoading(false);
      return () => {
        active = false;
      };
    }

    fetchProductsBySlugs(slugs.slice(0, 6))
      .then((products) => {
        if (!active) return;
        setItems(products);
      })
      .catch((err: any) => {
        if (!active) return;
        show({ type: 'error', message: err?.message ?? 'Unable to load recently viewed items.' });
        setItems([]);
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => {
      active = false;
    };
  }, [slugs, show]);

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView style={styles.scroll} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.headerRow}>
          <Text style={styles.title}>Recently viewed</Text>
          <Pressable style={styles.iconButton} onPress={() => router.push('/account/recent-date')}>
            <Feather name="calendar" size={16} color={theme.colors.inkDark} />
          </Pressable>
        </View>

        <View style={styles.grid}>
          {loading
            ? Array.from({ length: 6 }, (_, index) => (
                <View key={`sk-${index}`} style={styles.card}>
                  <Skeleton height={180} radius={16} />
                  <Skeleton height={10} radius={5} style={styles.skeletonGap} />
                  <Skeleton height={12} radius={6} width="40%" style={styles.skeletonGap} />
                </View>
              ))
            : items.map((item) => (
                <Pressable
                  key={item.id}
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
                  <Text style={styles.cardPrice}>{formatCurrency(item.price, item.currency, state.currency)}</Text>
                </Pressable>
              ))}
          {!loading && items.length === 0 ? (
            <View style={styles.emptyCard}>
              <Text style={styles.emptyTitle}>No recent items</Text>
              <Text style={styles.emptyBody}>Start browsing to build your recently viewed list.</Text>
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
    paddingTop: 10,
    paddingBottom: 24,
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 14,
  },
  title: {
    fontSize: 22,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  iconButton: {
    width: 32,
    height: 32,
    borderRadius: 16,
    backgroundColor: theme.colors.gray100,
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
    height: 180,
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
    height: 180,
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
