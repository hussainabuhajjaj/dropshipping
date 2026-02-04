import { Feather } from '@expo/vector-icons';
import { Image, ImageSourcePropType, StyleSheet, View } from 'react-native';
import { theme } from '@/src/theme';

type AvatarBadgeProps = {
  size?: number;
  imageSource?: ImageSourcePropType;
  innerColor?: string;
};

export function AvatarBadge({ size, imageSource, innerColor = '#f4d6e6' }: AvatarBadgeProps) {
  const resolvedSize = size ?? theme.moderateScale(86);
  const innerSize = resolvedSize * 0.7;

  return (
    <View
      style={[
        styles.outer,
        {
          width: resolvedSize,
          height: resolvedSize,
          borderRadius: resolvedSize / 2,
        },
      ]}
    >
      <View
        style={[
          styles.inner,
          {
            width: innerSize,
            height: innerSize,
            borderRadius: innerSize / 2,
            backgroundColor: innerColor,
          },
        ]}
      >
        {imageSource ? (
          <Image source={imageSource} style={styles.image} resizeMode="cover" />
        ) : (
          <Feather name="user" size={Math.round(innerSize * 0.45)} color={theme.colors.ink} />
        )}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  outer: {
    backgroundColor: theme.colors.white,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: theme.moderateScale(4),
    borderColor: theme.colors.white,
    ...theme.shadows.sm,
  },
  inner: {
    alignItems: 'center',
    justifyContent: 'center',
    overflow: 'hidden',
  },
  image: {
    width: '100%',
    height: '100%',
  },
});
