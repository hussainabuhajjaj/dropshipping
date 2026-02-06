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

export default function ImageSearchResultsScreen() {
  const { show } = useToast();
  const [matches, setMatches] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let active = true;
    setLoading(true);
    fetchProducts({ per_page: 8 })
      .then(({ items }) => {
        if (!active) return;
        setMatches(items);
      })
      .catch((err: any) => {
        if (!active) return;
        show({ type: 'error', message: err?.message ?? 'Unable to load image matches.' });
        setMatches([]);
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
      <ScrollView style={styles.scroll} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.headerRow}>
          <Pressable style={styles.iconButton} onPress={() => router.back()}>
            <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
          </Pressable>
          <Text style={styles.title}>Image results</Text>
          <Pressable style={styles.iconButton} onPress={() => router.push('/image-search/filter')}>
            <Feather name="sliders" size={16} color={theme.colors.inkDark} />
          </Pressable>
        </View>

        <View style={styles.matchRow}>
          <View style={styles.matchImage} />
          <View>
            <Text style={styles.matchTitle}>Matching 128 items</Text>
            <Text style={styles.matchBody}>Based on your uploaded image.</Text>
          </View>
        </View>

        <View style={styles.grid}>
          {loading
            ? Array.from({ length: 8 }, (_, index) => (
                <View key={`sk-${index}`} style={styles.card}>
                  <Skeleton height={170} radius={18} />
                  <Skeleton height={10} radius={5} style={styles.skeletonGap} />
                  <Skeleton height={12} radius={6} width="40%" style={styles.skeletonGap} />
                </View>
              ))
            : matches.map((item) => (
                <Pressable key={item.id} style={styles.card} onPress={() => router.push(`/products/${item.slug}`)}>
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
          {!loading && matches.length === 0 ? (
            <View style={styles.emptyCard}>
              <Text style={styles.emptyTitle}>No matches found</Text>
              <Text style={styles.emptyBody}>Try a different image or adjust filters.</Text>
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
    fontSize: 18,
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
  matchRow: {
    flexDirection: 'row',
    gap: 12,
    padding: 12,
    borderRadius: 18,
    backgroundColor: theme.colors.sand,
    marginBottom: 16,
    alignItems: 'center',
  },
  matchImage: {
    width: 56,
    height: 56,
    borderRadius: 14,
    backgroundColor: theme.colors.blueSoftMuted,
  },
  matchTitle: {
    fontSize: 14,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  matchBody: {
    marginTop: 4,
    fontSize: 12,
    color: theme.colors.mutedDark,
  },
  grid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
  },
  card: {
    width: '48%',
  },
  cardImage: {
    width: '100%',
    height: 170,
    borderRadius: 18,
    backgroundColor: theme.colors.gray200,
  },
  cardTitle: {
    marginTop: 8,
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
  skeletonGap: {
    marginTop: 8,
  },
  cardImageFallback: {
    width: '100%',
    height: 170,
    borderRadius: 18,
    backgroundColor: theme.colors.gray200,
  },
  emptyCard: {
    width: '100%',
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
