import { FontAwesome } from '@expo/vector-icons';
import { Image, Pressable, StyleSheet, View } from 'react-native';
import { Text } from '@/src/components/i18n/Text';
import { router } from 'expo-router';
import { useCart } from '@/lib/cartStore';
import { useWishlist } from '@/lib/wishlistStore';
import { Skeleton } from '@/src/components/ui/Skeleton';
import { theme } from '@/src/theme';
import { useToast } from '@/src/overlays/ToastProvider';
import type { Product } from '@/src/types/storefront';
import { formatCurrency } from '@/src/lib/formatCurrency';
import { usePreferences } from '@/src/store/preferencesStore';

type ProductTileProps = {
  product?: Product;
  mode?: 'grid' | 'carousel';
  onPress?: () => void;
  onAdd?: () => void;
  loading?: boolean;
};

export function ProductTile({
  product,
  mode = 'grid',
  onPress,
  onAdd,
  loading = false,
}: ProductTileProps) {
  if (loading) {
    return (
      <View style={[styles.card, mode === 'grid' ? styles.cardGrid : null]}>
        <Skeleton height={theme.moderateScale(140)} radius={theme.moderateScale(16)} />
        <View style={styles.copy}>
          <Skeleton height={theme.moderateScale(12)} radius={theme.moderateScale(6)} />
          <Skeleton height={theme.moderateScale(12)} radius={theme.moderateScale(6)} width="70%" />
          <Skeleton height={theme.moderateScale(10)} radius={theme.moderateScale(5)} width="55%" />
        </View>
        <Skeleton height={theme.moderateScale(36)} radius={theme.moderateScale(999)} />
      </View>
    );
  }

  if (!product) {
    return null;
  }

  const { addItem } = useCart();
  const { toggle, contains } = useWishlist();
  const { show } = useToast();
  const { state } = usePreferences();
  const wishlistKey = product.id;
  const isWishlisted = contains(wishlistKey);
  const openProduct = onPress ?? (() => router.push(`/products/${product.slug}`));
  const addToCart = onAdd ?? (() => addItem(product));
  const handleToggle = async () => {
    const result = await toggle(product);
    if (!result.ok) {
      show({ type: 'error', message: result.message ?? 'Unable to update wishlist.' });
    }
  };

  return (
    <View style={[styles.card, mode === 'grid' ? styles.cardGrid : null]}>
      <Pressable style={styles.imageWrap} onPress={openProduct} accessibilityRole="button">
        {product.image ? (
          <Image source={{ uri: product.image }} style={styles.image} />
        ) : (
          <View style={styles.imageFallback}>
            <Text style={styles.imageFallbackText}>{product.name.slice(0, 1).toUpperCase()}</Text>
          </View>
        )}
        {product.badge ? (
          <View style={styles.badge}>
            <Text style={styles.badgeText}>{product.badge}</Text>
          </View>
        ) : null}
        <Pressable
          style={[styles.wishlistButton, isWishlisted ? styles.wishlistActive : null]}
          onPress={handleToggle}
          hitSlop={8}
          accessibilityRole="button"
          accessibilityLabel={isWishlisted ? 'Remove from wishlist' : 'Add to wishlist'}
        >
          <FontAwesome
            name={isWishlisted ? 'heart' : 'heart-o'}
            size={12}
            color={isWishlisted ? theme.colors.white : theme.colors.inkDark}
          />
        </Pressable>
      </Pressable>

      <Pressable onPress={openProduct} style={styles.copy} accessibilityRole="button">
        <Text style={styles.name} numberOfLines={2}>
          {product.name}
        </Text>
        <View style={styles.priceRow}>
          <Text style={styles.price}>{formatCurrency(product.price, product.currency, state.currency)}</Text>
          {product.compareAt ? (
            <Text style={styles.compare}>{formatCurrency(product.compareAt, product.currency, state.currency)}</Text>
          ) : null}
        </View>
        <View style={styles.ratingRow}>
          <FontAwesome name="star" size={12} color={theme.colors.sun} />
          <Text style={styles.rating}>{product.rating.toFixed(1)}</Text>
          <Text style={styles.reviews}>({product.reviews})</Text>
        </View>
      </Pressable>

      <Pressable style={styles.addButton} onPress={addToCart} accessibilityRole="button" accessibilityLabel="Add to cart">
        <Text style={styles.addText}>+ Add</Text>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    borderRadius: theme.moderateScale(18),
    backgroundColor: theme.colors.white,
    borderWidth: 1,
    borderColor: theme.colors.borderSoft,
    padding: theme.moderateScale(10),
  },
  cardGrid: {
    width: '100%',
  },
  imageWrap: {
    borderRadius: theme.moderateScale(16),
    overflow: 'hidden',
    position: 'relative',
  },
  image: {
    width: '100%',
    height: theme.moderateScale(140),
    backgroundColor: theme.colors.gray200,
  },
  imageFallback: {
    width: '100%',
    height: theme.moderateScale(140),
    backgroundColor: theme.colors.primarySoftLight,
    alignItems: 'center',
    justifyContent: 'center',
  },
  imageFallbackText: {
    fontSize: theme.moderateScale(20),
    fontWeight: '800',
    color: theme.colors.inkDark,
  },
  badge: {
    position: 'absolute',
    left: theme.moderateScale(8),
    top: theme.moderateScale(8),
    backgroundColor: theme.colors.pink,
    borderRadius: theme.moderateScale(999),
    paddingHorizontal: theme.moderateScale(10),
    paddingVertical: theme.moderateScale(4),
  },
  badgeText: {
    color: theme.colors.white,
    fontSize: theme.moderateScale(10),
    fontWeight: '700',
  },
  wishlistButton: {
    position: 'absolute',
    right: theme.moderateScale(8),
    top: theme.moderateScale(8),
    width: theme.moderateScale(28),
    height: theme.moderateScale(28),
    borderRadius: theme.moderateScale(14),
    backgroundColor: theme.colors.white,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: theme.colors.borderSoft,
  },
  wishlistActive: {
    backgroundColor: theme.colors.pink,
    borderColor: theme.colors.pink,
  },
  copy: {
    marginTop: theme.moderateScale(10),
    gap: theme.moderateScale(6),
  },
  name: {
    fontSize: theme.moderateScale(13),
    fontWeight: '600',
    color: theme.colors.ink,
  },
  priceRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.moderateScale(8),
  },
  price: {
    fontSize: theme.moderateScale(14),
    fontWeight: '800',
    color: theme.colors.ink,
  },
  compare: {
    fontSize: theme.moderateScale(12),
    color: theme.colors.muted,
    textDecorationLine: 'line-through',
  },
  ratingRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.moderateScale(6),
  },
  rating: {
    fontSize: theme.moderateScale(12),
    color: theme.colors.ink,
    fontWeight: '700',
  },
  reviews: {
    fontSize: theme.moderateScale(12),
    color: theme.colors.muted,
  },
  addButton: {
    marginTop: theme.moderateScale(10),
    backgroundColor: theme.colors.sun,
    borderRadius: theme.moderateScale(999),
    paddingVertical: theme.moderateScale(10),
    alignItems: 'center',
  },
  addText: {
    fontSize: theme.moderateScale(12),
    fontWeight: '800',
    color: theme.colors.inkDark,
  },
});
