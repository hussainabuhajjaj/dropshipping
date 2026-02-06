import { Feather } from '@expo/vector-icons';
import { router, useLocalSearchParams } from 'expo-router';
import { useEffect, useState } from 'react';
import { Image, Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { Skeleton } from '@/src/components/ui/Skeleton';
import { fetchProduct, fetchProducts } from '@/src/api/catalog';
import { useCart } from '@/lib/cartStore';
import { theme } from '@/src/theme';
import { useToast } from '@/src/overlays/ToastProvider';
import type { Product } from '@/src/types/storefront';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
const variations = ['Color', 'Size'];

export default function ProductSaleScreen() {
  const params = useLocalSearchParams();
  const slug = typeof params.slug === 'string' ? params.slug : '';
  const { show } = useToast();
  const [product, setProduct] = useState<Product | null>(null);
  const [loading, setLoading] = useState(true);
  const { addItem } = useCart();
  const thumbSize = 75;
  const thumbRadius = 12;
  const insets = useSafeAreaInsets();

  useEffect(() => {
    let active = true;
    setLoading(true);
    const load = async () => {
      try {
        if (slug) {
          const item = await fetchProduct(slug);
          if (active) setProduct(item);
          return;
        }
        const { items } = await fetchProducts({ per_page: 1 });
        if (active) setProduct(items[0] ?? null);
      } catch (err: any) {
        if (!active) return;
        show({ type: 'error', message: err?.message ?? 'Unable to load product.' });
        setProduct(null);
      } finally {
        if (active) setLoading(false);
      }
    };
    load();

    return () => {
      active = false;
    };
  }, [show, slug]);

  const canBuy = !loading && product;

  return (
    <View style={styles.container}>
      <ScrollView
        style={styles.scroll}
        contentContainerStyle={[
          styles.content,
          {
            paddingBottom: theme.moderateScale(120) + insets.bottom,
          },
        ]}
        showsVerticalScrollIndicator={false}
      >
        <View style={styles.imageWrap}>
          {loading ? (
            <Skeleton height={439} radius={0} />
          ) : product?.image ? (
            <Image source={{ uri: product.image }} style={styles.image} />
          ) : (
            <View style={styles.imageFallback} />
          )}
        </View>
        <View style={styles.controls}>
          <View style={styles.controlActive} />
          <View style={styles.controlDot} />
          <View style={styles.controlDot} />
          <View style={styles.controlDot} />
          <View style={styles.controlDot} />
        </View>

        <View style={styles.priceRow}>
          {loading ? (
            <View style={styles.priceBlock}>
              <Skeleton width={80} height={12} />
              <Skeleton width={60} height={16} style={styles.skeletonGap} />
            </View>
          ) : (
            <View style={styles.priceBlock}>
              {product?.compareAt ? (
                <Text style={styles.salePrice}>${product.compareAt.toFixed(2)}</Text>
              ) : null}
              <Text style={styles.price}>${product?.price?.toFixed(2) ?? '0.00'}</Text>
            </View>
          )}
          <Pressable style={styles.discountTag}>
            <Text style={styles.discountText}>
              {product?.compareAt
                ? `-${Math.round(100 - (product.price / product.compareAt) * 100)}%`
                : '-20%'}
            </Text>
          </Pressable>
        </View>

        <View style={styles.timerRow}>
          <Feather name="clock" size={16} color={theme.colors.inkDark} />
          <View style={styles.timer}>
            <Text style={styles.timerText}>00</Text>
            <Text style={styles.timerText}>36</Text>
            <Text style={styles.timerText}>58</Text>
          </View>
        </View>

        {loading ? (
          <View style={styles.descriptionSkeleton}>
            <Skeleton height={12} radius={6} width="90%" />
            <Skeleton height={12} radius={6} width="80%" style={styles.skeletonGap} />
          </View>
        ) : (
          <Text style={styles.description}>
            {product?.description ??
              'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Etiam arcu mauris, scelerisque eu mauris id, pretium pulvinar sapien.'}
          </Text>
        )}

        <View style={styles.variationHeader}>
          <Text style={styles.sectionTitle}>Variations</Text>
          <Pressable style={styles.moreButton}>
            <Feather name="more-horizontal" size={14} color={theme.colors.inkDark} />
          </Pressable>
        </View>

        <View style={styles.variationRow}>
          {variations.map((item) => (
            <View key={item} style={styles.variationChip}>
              <Text style={styles.variationText}>{item}</Text>
            </View>
          ))}
        </View>

        <View style={styles.thumbRow}>
          {[0, 1, 2].map((item) => (
            loading ? (
              <Skeleton key={`thumb-${item}`} width={thumbSize} height={thumbSize} radius={thumbRadius} />
            ) : (
              <Image
                key={`thumb-${item}`}
                source={{ uri: product?.image ?? '' }}
                style={styles.thumb}
              />
            )
          ))}
        </View>
      </ScrollView>

      <View
        style={[
          styles.bottomBar,
          {
            height: theme.moderateScale(84) + insets.bottom,
            paddingBottom: insets.bottom,
          },
        ]}
      >
        <Pressable style={styles.likeButton}>
          <Feather name="heart" size={16} color={theme.colors.inkDark} />
        </Pressable>
        <Pressable style={styles.addButton} onPress={() => product && addItem(product)} disabled={!canBuy}>
          <Text style={styles.addText}>Add to cart</Text>
        </Pressable>
        <Pressable
          style={styles.buyButton}
          onPress={() => (canBuy ? router.push('/checkout') : null)}
          disabled={!canBuy}
        >
          <Text style={styles.buyText}>Buy now</Text>
        </Pressable>
      </View>
    </View>
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
    paddingBottom: 120,
  },
  imageWrap: {
    height: 439,
    backgroundColor: theme.colors.gray200,
  },
  image: {
    width: '100%',
    height: '100%',
  },
  imageFallback: {
    width: '100%',
    height: '100%',
    backgroundColor: theme.colors.gray200,
  },
  controls: {
    flexDirection: 'row',
    gap: 8,
    justifyContent: 'center',
    marginTop: 12,
  },
  controlActive: {
    width: 40,
    height: 10,
    borderRadius: 5,
    backgroundColor: theme.colors.sun,
  },
  controlDot: {
    width: 10,
    height: 10,
    borderRadius: 5,
    backgroundColor: theme.colors.sand,
  },
  priceRow: {
    marginTop: 16,
    paddingHorizontal: 20,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  priceBlock: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  salePrice: {
    fontSize: 16,
    color: '#f1aeae',
    textDecorationLine: 'line-through',
  },
  price: {
    fontSize: 20,
    fontWeight: '700',
    color: theme.colors.black,
  },
  discountTag: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 10,
    backgroundColor: '#f81140',
  },
  discountText: {
    color: theme.colors.white,
    fontSize: 12,
    fontWeight: '700',
  },
  timerRow: {
    marginTop: 10,
    paddingHorizontal: 20,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  timer: {
    flexDirection: 'row',
    gap: 6,
  },
  timerText: {
    fontSize: 14,
    color: theme.colors.inkDark,
    fontWeight: '600',
  },
  description: {
    marginTop: 12,
    paddingHorizontal: 20,
    fontSize: 13,
    lineHeight: 20,
    color: theme.colors.black,
  },
  descriptionSkeleton: {
    marginTop: 12,
    paddingHorizontal: 20,
    gap: 6,
  },
  variationHeader: {
    marginTop: 20,
    paddingHorizontal: 20,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: theme.colors.black,
  },
  moreButton: {
    width: 30,
    height: 30,
    borderRadius: 15,
    backgroundColor: theme.colors.gray100,
    alignItems: 'center',
    justifyContent: 'center',
  },
  variationRow: {
    marginTop: 10,
    paddingHorizontal: 20,
    flexDirection: 'row',
    gap: 10,
  },
  variationChip: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 12,
    backgroundColor: theme.colors.gray100,
  },
  variationText: {
    fontSize: 12,
    color: theme.colors.black,
  },
  thumbRow: {
    marginTop: 12,
    paddingHorizontal: 20,
    flexDirection: 'row',
    gap: 10,
  },
  thumb: {
    width: 75,
    height: 75,
    borderRadius: 12,
    backgroundColor: theme.colors.gray200,
  },
  skeletonGap: {
    marginTop: 8,
  },
  bottomBar: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
    height: 84,
    backgroundColor: theme.colors.white,
    borderTopWidth: 1,
    borderTopColor: theme.colors.gray300,
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 20,
    gap: 10,
  },
  likeButton: {
    width: 47,
    height: 40,
    borderRadius: 14,
    backgroundColor: theme.colors.gray100,
    alignItems: 'center',
    justifyContent: 'center',
  },
  addButton: {
    flex: 1,
    height: 40,
    borderRadius: 20,
    backgroundColor: theme.colors.inkDark,
    alignItems: 'center',
    justifyContent: 'center',
  },
  addText: {
    color: theme.colors.gray200,
    fontSize: 14,
    fontWeight: '600',
  },
  buyButton: {
    flex: 1,
    height: 40,
    borderRadius: 20,
    backgroundColor: theme.colors.sun,
    alignItems: 'center',
    justifyContent: 'center',
  },
  buyText: {
    color: theme.colors.gray200,
    fontSize: 14,
    fontWeight: '600',
  },
});
