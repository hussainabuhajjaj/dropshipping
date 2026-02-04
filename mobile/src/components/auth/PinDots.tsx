import { StyleSheet, View } from 'react-native';
import { theme } from '@/src/theme';

type PinDotsProps = {
  total: number;
  filled?: number;
  size?: number;
  activeColor?: string;
  inactiveColor?: string;
};

export function PinDots({
  total,
  filled = 0,
  size,
  activeColor = theme.colors.primary,
  inactiveColor = theme.colors.primarySoftAlt,
}: PinDotsProps) {
  const resolvedSize = size ?? theme.moderateScale(12);
  return (
    <View style={styles.row}>
      {Array.from({ length: total }).map((_, index) => (
        <View
          key={`dot-${index}`}
          style={[
            styles.dot,
            {
              width: resolvedSize,
              height: resolvedSize,
              borderRadius: resolvedSize / 2,
              backgroundColor: index < filled ? activeColor : inactiveColor,
            },
          ]}
        />
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    gap: theme.moderateScale(12),
  },
  dot: {
    backgroundColor: theme.colors.primarySoftAlt,
  },
});
