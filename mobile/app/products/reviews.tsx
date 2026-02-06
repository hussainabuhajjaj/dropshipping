import { Feather } from '@expo/vector-icons';
import { router, useLocalSearchParams } from 'expo-router';
import { useEffect, useMemo, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
import { fetchProductReviews } from '@/src/api/reviews';
import type { ProductReview } from '@/src/types/reviews';
import { useToast } from '@/src/overlays/ToastProvider';
import { SafeAreaView } from 'react-native-safe-area-context';

export default function ProductReviewsScreen() {
  const params = useLocalSearchParams();
  const slug = typeof params.slug === 'string' ? params.slug : '';
  const { show } = useToast();
  const [items, setItems] = useState<ProductReview[]>([]);
  const [loading, setLoading] = useState(true);
  const [total, setTotal] = useState(0);

  useEffect(() => {
    let active = true;
    if (!slug) {
      setLoading(false);
      return;
    }
    setLoading(true);
    fetchProductReviews(slug, { per_page: 20 })
      .then(({ items, meta }) => {
        if (!active) return;
        setItems(items);
        setTotal(meta.total ?? items.length);
      })
      .catch((err: any) => {
        if (!active) return;
        show({ type: 'error', message: err?.message ?? 'Unable to load reviews.' });
        setItems([]);
        setTotal(0);
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => {
      active = false;
    };
  }, [show, slug]);

  const average = useMemo(() => {
    if (items.length === 0) return 0;
    const sum = items.reduce((acc, item) => acc + (item.rating || 0), 0);
    return Math.round((sum / items.length) * 10) / 10;
  }, [items]);

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView style={styles.scroll} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.headerRow}>
          <Text style={styles.title}>Reviews</Text>
          <Pressable style={styles.closeButton} onPress={() => router.back()}>
            <Feather name="x" size={16} color={theme.colors.inkDark} />
          </Pressable>
        </View>

        <View style={styles.summaryCard}>
          <View style={styles.ratingRow}>
            {[0, 1, 2, 3, 4].map((item) => (
              <Feather key={`star-${item}`} name="star" size={20} color={theme.colors.inkDark} />
            ))}
            <View style={styles.ratingBadge}>
              <Text style={styles.ratingBadgeText}>{average}/5</Text>
            </View>
          </View>
          <Text style={styles.summaryText}>
            {total.toLocaleString()} reviews
          </Text>
        </View>

        <View style={styles.list}>
          {!loading && items.length === 0 ? (
            <View style={styles.emptyCard}>
              <Text style={styles.emptyTitle}>No reviews yet</Text>
              <Text style={styles.emptyBody}>Be the first to share your feedback.</Text>
            </View>
          ) : null}
          {items.map((review) => (
            <View key={review.id} style={styles.reviewCard}>
              <View style={styles.avatar} />
              <View style={styles.reviewBody}>
                <Text style={styles.reviewName}>{review.author ?? 'Verified buyer'}</Text>
                <View style={styles.starsRow}>
                  {Array.from({ length: 5 }).map((_, index) => (
                    <Feather
                      key={`${review.id}-${index}`}
                      name="star"
                      size={14}
                      color={index < review.rating ? theme.colors.sun : theme.colors.sand}
                    />
                  ))}
                </View>
                <Text style={styles.reviewText}>{review.body ?? ''}</Text>
              </View>
            </View>
          ))}
        </View>

        <Pressable style={styles.primaryButton} onPress={() => router.push(`/orders/review?slug=${slug}`)}>
          <Text style={styles.primaryText}>Write a review</Text>
        </Pressable>
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.sand,
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
    fontSize: 24,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  closeButton: {
    width: 32,
    height: 32,
    borderRadius: 16,
    backgroundColor: theme.colors.white,
    borderWidth: 1,
    borderColor: theme.colors.sand,
    alignItems: 'center',
    justifyContent: 'center',
  },
  summaryCard: {
    backgroundColor: theme.colors.white,
    borderRadius: 18,
    padding: 16,
    borderWidth: 1,
    borderColor: theme.colors.sand,
  },
  ratingRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  ratingBadge: {
    marginLeft: 10,
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 10,
    backgroundColor: theme.colors.sand,
  },
  ratingBadgeText: {
    fontSize: 12,
    color: theme.colors.inkDark,
  },
  summaryText: {
    marginTop: 8,
    fontSize: 12,
    color: theme.colors.inkDark,
  },
  list: {
    marginTop: 18,
    gap: 16,
  },
  reviewCard: {
    flexDirection: 'row',
    gap: 12,
    padding: 14,
    borderRadius: 16,
    backgroundColor: theme.colors.white,
    borderWidth: 1,
    borderColor: theme.colors.sand,
  },
  avatar: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: theme.colors.sand,
  },
  reviewBody: {
    flex: 1,
  },
  reviewName: {
    fontSize: 13,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  starsRow: {
    marginTop: 6,
    flexDirection: 'row',
    gap: 4,
  },
  reviewText: {
    marginTop: 8,
    fontSize: 12,
    color: theme.colors.inkDark,
  },
  emptyCard: {
    borderRadius: 16,
    padding: 16,
    backgroundColor: theme.colors.white,
    borderWidth: 1,
    borderColor: theme.colors.sand,
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
  primaryButton: {
    marginTop: 24,
    backgroundColor: theme.colors.sun,
    borderRadius: 24,
    paddingVertical: 14,
    alignItems: 'center',
  },
  primaryText: {
    fontSize: 14,
    color: theme.colors.inkDark,
    fontWeight: '700',
  },
});
