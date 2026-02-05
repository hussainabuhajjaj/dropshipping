import { DimensionValue, Image, ImageSourcePropType, Pressable, StyleSheet, View } from 'react-native';
import { Text } from '@/src/components/i18n/Text';
import { LinearGradient } from 'expo-linear-gradient';
import { Skeleton } from '@/src/components/ui/Skeleton';
import { theme } from '@/src/theme';

type ProductCardProps = {
  title?: string;
  price?: string;
  oldPrice?: string;
  badge?: string;
  image?: ImageSourcePropType;
  width: DimensionValue;
  imageHeight: number;
  onPress?: () => void;
  loading?: boolean;
};

export function ProductCard({
  title,
  price,
  oldPrice,
  badge,
  image,
  width,
  imageHeight,
  onPress,
  loading = false,
}: ProductCardProps) {
  if (loading) {
    return (
      <View style={[styles.card, { width }]}>
        <Skeleton height={imageHeight} radius={theme.moderateScale(16)} />
        <Skeleton height={theme.moderateScale(12)} radius={theme.moderateScale(6)} />
        <Skeleton height={theme.moderateScale(12)} radius={theme.moderateScale(6)} width="60%" />
      </View>
    );
  }

  return (
    <Pressable
      style={[styles.card, { width }]}
      onPress={onPress}
      accessibilityRole={onPress ? 'button' : undefined}
    >
      <View style={[styles.imageWrap, { height: imageHeight }]}>
        {image ? (
          <Image source={image} style={styles.image} resizeMode="cover" />
        ) : (
          <LinearGradient
            colors={[theme.colors.primarySoft, theme.colors.primarySoftAlt]}
            style={styles.image}
          />
        )}
        {badge ? (
          <View style={styles.badge}>
            <Text style={styles.badgeText}>{badge}</Text>
          </View>
        ) : null}
      </View>
      <Text style={styles.title} numberOfLines={2}>
        {title}
      </Text>
      <View style={styles.priceRow}>
        <Text style={styles.price}>{price}</Text>
        {oldPrice ? <Text style={styles.oldPrice}>{oldPrice}</Text> : null}
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  card: {
    gap: theme.moderateScale(6),
  },
  imageWrap: {
    width: '100%',
    borderRadius: theme.moderateScale(16),
    overflow: 'hidden',
    backgroundColor: theme.colors.primarySoftLight,
  },
  image: {
    width: '100%',
    height: '100%',
  },
  badge: {
    position: 'absolute',
    top: theme.moderateScale(8),
    right: theme.moderateScale(8),
    backgroundColor: theme.colors.pink,
    borderRadius: theme.moderateScale(10),
    paddingHorizontal: theme.moderateScale(6),
    paddingVertical: theme.moderateScale(2),
  },
  badgeText: {
    fontSize: theme.moderateScale(10),
    fontWeight: '700',
    color: theme.colors.white,
  },
  title: {
    fontSize: theme.moderateScale(12),
    color: theme.colors.ink,
  },
  priceRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.moderateScale(6),
  },
  price: {
    fontSize: theme.moderateScale(13),
    fontWeight: '700',
    color: theme.colors.ink,
  },
  oldPrice: {
    fontSize: theme.moderateScale(12),
    color: theme.colors.mutedLight,
    textDecorationLine: 'line-through',
  },
});
