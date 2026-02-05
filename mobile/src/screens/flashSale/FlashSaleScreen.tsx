import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useEffect, useMemo, useState } from 'react';
import { SafeAreaView } from 'react-native-safe-area-context';
import { FlatList, StyleSheet, View } from 'react-native';
import { Text } from '@/src/components/i18n/Text';
import { Chip } from '@/src/components/ui/Chip';
import { CircleIconButton } from '@/src/components/ui/CircleIconButton';
import { PopularCard } from '@/src/components/ui/PopularCard';
import { ProductCard } from '@/src/components/ui/ProductCard';
import { theme } from '@/src/theme';
import { fetchProducts } from '@/src/api/catalog';
import { useToast } from '@/src/overlays/ToastProvider';
import type { Product } from '@/src/types/storefront';
import { formatCurrency } from '@/src/lib/formatCurrency';
import { usePreferences } from '@/src/store/preferencesStore';

const discounts = ['All', '10%', '20%', '30%', '40%', '50%'];
const mostPopular = [
  { id: 'popular-1', label: 'New', count: '1780' },
  { id: 'popular-2', label: 'Sale', count: '1780' },
  { id: 'popular-3', label: 'Hot', count: '1780' },
  { id: 'popular-4', label: 'Trend', count: '1780' },
];

export default function FlashSaleScreen() {
  const { show } = useToast();
  const { state } = usePreferences();
  const [items, setItems] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);
  const listItems = useMemo(() => {
    if (loading) {
      return Array.from({ length: 8 }, (_, index) => ({ id: `sk-${index}`, skeleton: true }));
    }
    return items;
  }, [items, loading]);

  useEffect(() => {
    let active = true;
    setLoading(true);
    fetchProducts({ per_page: 8 })
      .then(({ items: payload }) => {
        if (!active) return;
        setItems(payload);
      })
      .catch((err: any) => {
        if (!active) return;
        show({ type: 'error', message: err?.message ?? 'Unable to load flash sale items.' });
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
    <SafeAreaView style={styles.container}>
      <FlatList
        data={listItems}
        keyExtractor={(item) => item.id}
        numColumns={2}
        columnWrapperStyle={styles.column}
        showsVerticalScrollIndicator={false}
        contentContainerStyle={styles.listContent}
        ListHeaderComponent={
          <View>
            <View style={styles.headerRow}>
              <View>
                <Text style={styles.title}>Flash Sale</Text>
                <Text style={styles.subtitle}>Choose Your Discount</Text>
              </View>
              <View style={styles.timer}>
                <Feather name="clock" size={theme.moderateScale(12)} color={theme.colors.white} />
                <Text style={styles.timerText}>00</Text>
                <Text style={styles.timerText}>36</Text>
                <Text style={styles.timerText}>58</Text>
              </View>
            </View>

            <FlatList
              horizontal
              data={discounts}
              keyExtractor={(item) => item}
              showsHorizontalScrollIndicator={false}
              contentContainerStyle={styles.discountRow}
              renderItem={({ item }) => (
                <Chip label={item} active={item === '20%'} />
              )}
            />

            <View style={styles.sectionRow}>
              <Text style={styles.sectionTitle}>20% Discount</Text>
              <CircleIconButton
                icon="sliders"
                size={theme.moderateScale(28)}
                variant="outlined"
                onPress={() => router.push('/shop/categories-filter')}
              />
            </View>
          </View>
        }
        renderItem={({ item }) => {
          if ('skeleton' in item) {
            return (
              <ProductCard
                loading
                width="48%"
                imageHeight={theme.moderateScale(150)}
              />
            );
          }
          const hasCompare = item.compareAt && item.compareAt > item.price;
          const discount = hasCompare
            ? Math.round(100 - (item.price / (item.compareAt as number)) * 100)
            : null;
          return (
            <ProductCard
              title={item.name}
              price={formatCurrency(item.price, item.currency, state.currency)}
              oldPrice={
                hasCompare
                  ? formatCurrency(item.compareAt as number, item.currency, state.currency)
                  : undefined
              }
              badge={discount ? `-${discount}%` : item.badge ?? undefined}
              width="48%"
              imageHeight={theme.moderateScale(150)}
              image={item.image ? { uri: item.image } : undefined}
              onPress={() => router.push(`/products/${item.slug}`)}
            />
          );
        }}
        ListFooterComponent={
          <View style={styles.footer}>
            <View style={styles.banner}>
              <Text style={styles.bannerTitle}>Big Sale</Text>
              <Text style={styles.bannerSubtitle}>Up to 50%</Text>
              <View style={styles.bannerBadge}>
                <Text style={styles.bannerBadgeText}>Happening{'\n'}Now</Text>
              </View>
            </View>

            <View style={styles.sectionRow}>
              <Text style={styles.sectionTitle}>Most Popular</Text>
              <CircleIconButton
                icon="arrow-right"
                size={theme.moderateScale(28)}
                variant="filled"
                onPress={() => router.push('/products')}
              />
            </View>

            <FlatList
              horizontal
              data={mostPopular}
              keyExtractor={(item) => item.id}
              showsHorizontalScrollIndicator={false}
              contentContainerStyle={styles.popularRow}
              renderItem={({ item }) => (
                <PopularCard
                  label={item.label}
                  count={item.count}
                  loading={loading}
                  onPress={() => router.push('/products')}
                />
              )}
            />
          </View>
        }
      />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.white,
  },
  listContent: {
    paddingHorizontal: theme.moderateScale(20),
    paddingBottom: theme.moderateScale(28),
    paddingTop: theme.moderateScale(10),
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  title: {
    fontSize: theme.moderateScale(22),
    fontWeight: '700',
    color: theme.colors.ink,
  },
  subtitle: {
    marginTop: theme.moderateScale(4),
    fontSize: theme.moderateScale(12),
    color: theme.colors.muted,
  },
  timer: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.moderateScale(6),
    backgroundColor: theme.colors.primary,
    borderRadius: theme.moderateScale(14),
    paddingHorizontal: theme.moderateScale(10),
    paddingVertical: theme.moderateScale(6),
  },
  timerText: {
    fontSize: theme.moderateScale(12),
    color: theme.colors.white,
    fontWeight: '600',
  },
  discountRow: {
    marginTop: theme.moderateScale(16),
    gap: theme.moderateScale(10),
  },
  sectionRow: {
    marginTop: theme.moderateScale(18),
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  sectionTitle: {
    fontSize: theme.moderateScale(16),
    fontWeight: '700',
    color: theme.colors.ink,
  },
  column: {
    justifyContent: 'space-between',
    marginBottom: theme.moderateScale(16),
  },
  footer: {
    marginTop: theme.moderateScale(10),
  },
  banner: {
    backgroundColor: '#f2c235',
    borderRadius: theme.moderateScale(18),
    padding: theme.moderateScale(16),
    marginBottom: theme.moderateScale(20),
  },
  bannerTitle: {
    fontSize: theme.moderateScale(20),
    fontWeight: '700',
    color: theme.colors.ink,
  },
  bannerSubtitle: {
    marginTop: theme.moderateScale(4),
    fontSize: theme.moderateScale(12),
    color: theme.colors.ink,
  },
  bannerBadge: {
    marginTop: theme.moderateScale(12),
    backgroundColor: theme.colors.primary,
    alignSelf: 'flex-start',
    borderRadius: theme.moderateScale(16),
    paddingHorizontal: theme.moderateScale(12),
    paddingVertical: theme.moderateScale(6),
  },
  bannerBadgeText: {
    fontSize: theme.moderateScale(10),
    color: theme.colors.white,
    fontWeight: '600',
    textAlign: 'center',
  },
  popularRow: {
    paddingTop: theme.moderateScale(12),
    gap: theme.moderateScale(12),
  },
});
