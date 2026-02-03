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
const sizes = ['XS', 'S', 'M', 'L', 'XL'];
const colors = [theme.colors.sun, theme.colors.orange, theme.colors.inkDark, theme.colors.white];

export default function ProductVariationsScreen() {
  const params = useLocalSearchParams();
  const slug = typeof params.slug === 'string' ? params.slug : '';
  const { show } = useToast();
  const [product, setProduct] = useState<Product | null>(null);
  const [loading, setLoading] = useState(true);
  const { addItem } = useCart();
  const canBuy = !loading && product;

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

  return (
    <View style={styles.container}>
      <ScrollView style={styles.scroll} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.imageWrap}>
          {loading ? (
            <Skeleton height={260} radius={20} />
          ) : product?.image ? (
            <Image source={{ uri: product.image }} style={styles.image} />
          ) : (
            <View style={styles.imageFallback} />
          )}
        </View>

        <View style={styles.headerRow}>
          <Text style={styles.title}>Variations</Text>
          <Pressable style={styles.closeButton} onPress={() => router.back()}>
            <Feather name="x" size={16} color={theme.colors.inkDark} />
          </Pressable>
        </View>

        <View style={styles.priceRow}>
          {loading ? (
            <Skeleton width={80} height={18} />
          ) : (
            <Text style={styles.price}>${product?.price?.toFixed(2) ?? '0.00'}</Text>
          )}
          <View style={styles.stockPill}>
            <Text style={styles.stockText}>In stock</Text>
          </View>
        </View>
        <Text style={styles.subtitle}>Select color and size before adding to cart.</Text>

        <View style={styles.sectionCard}>
          <Text style={styles.sectionTitle}>Colors</Text>
          <View style={styles.colorRow}>
            {colors.map((item, index) => (
              <View key={`color-${index}`} style={styles.colorWrap}>
                <View
                  style={[styles.colorDot, { backgroundColor: item }, index === 0 ? styles.colorActive : null]}
                />
              </View>
            ))}
          </View>
        </View>

        <View style={styles.sectionCard}>
          <View style={styles.sectionHeader}>
            <Text style={styles.sectionTitle}>Sizes</Text>
            <Text style={styles.sectionNote}>Size guide</Text>
          </View>
          <View style={styles.sizeRow}>
            {sizes.map((size, index) => (
              <Pressable key={size} style={[styles.sizeChip, index === 2 && styles.sizeChipActive]}>
                <Text style={[styles.sizeText, index === 2 && styles.sizeTextActive]}>{size}</Text>
              </Pressable>
            ))}
          </View>
        </View>
      </ScrollView>

      <View style={styles.bottomBar}>
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
    backgroundColor: theme.colors.sand,
  },
  scroll: {
    flex: 1,
  },
  content: {
    paddingHorizontal: 20,
    paddingBottom: 120,
  },
  imageWrap: {
    height: 260,
    borderRadius: 20,
    backgroundColor: theme.colors.sand,
    marginTop: 12,
    overflow: 'hidden',
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
  headerRow: {
    marginTop: 18,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  title: {
    fontSize: 22,
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
  priceRow: {
    marginTop: 8,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  price: {
    fontSize: 20,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  stockPill: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 12,
    backgroundColor: theme.colors.sun,
  },
  stockText: {
    fontSize: 11,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  subtitle: {
    marginTop: 6,
    fontSize: 13,
    color: theme.colors.inkDark,
  },
  sectionCard: {
    marginTop: 18,
    backgroundColor: theme.colors.white,
    borderRadius: 20,
    padding: 16,
    borderWidth: 1,
    borderColor: theme.colors.sand,
  },
  sectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  sectionNote: {
    fontSize: 12,
    color: theme.colors.mutedDark,
  },
  colorRow: {
    marginTop: 12,
    flexDirection: 'row',
    gap: 12,
  },
  colorWrap: {
    width: 30,
    height: 30,
    borderRadius: 15,
    backgroundColor: theme.colors.white,
    borderWidth: 1,
    borderColor: theme.colors.sand,
    alignItems: 'center',
    justifyContent: 'center',
  },
  colorDot: {
    width: 20,
    height: 20,
    borderRadius: 10,
  },
  colorActive: {
    borderWidth: 2,
    borderColor: theme.colors.inkDark,
  },
  sizeRow: {
    marginTop: 12,
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
  },
  sizeChip: {
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 16,
    backgroundColor: theme.colors.sand,
  },
  sizeChipActive: {
    backgroundColor: theme.colors.sun,
  },
  sizeText: {
    fontSize: 12,
    color: theme.colors.inkDark,
    fontWeight: '600',
  },
  sizeTextActive: {
    color: theme.colors.inkDark,
  },
  bottomBar: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
    height: 84,
    borderTopWidth: 1,
    borderTopColor: theme.colors.sand,
    backgroundColor: theme.colors.white,
    paddingHorizontal: 20,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  addButton: {
    flex: 1,
    height: 44,
    borderRadius: 22,
    backgroundColor: theme.colors.sun,
    alignItems: 'center',
    justifyContent: 'center',
  },
  addText: {
    color: theme.colors.inkDark,
    fontSize: 14,
    fontWeight: '600',
  },
  buyButton: {
    flex: 1,
    height: 44,
    borderRadius: 22,
    backgroundColor: theme.colors.orange,
    alignItems: 'center',
    justifyContent: 'center',
  },
  buyText: {
    color: theme.colors.white,
    fontSize: 14,
    fontWeight: '600',
  },
});
