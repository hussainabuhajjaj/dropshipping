import { DimensionValue, StyleSheet, View, ViewStyle } from 'react-native';
import { theme } from '@/src/theme';

type SkeletonProps = {
  width?: DimensionValue;
  height?: number;
  radius?: number;
  style?: ViewStyle;
};

export function Skeleton({ width = '100%', height = theme.moderateScale(12), radius = 8, style }: SkeletonProps) {
  return <View style={[styles.base, { width, height, borderRadius: radius }, style]} />;
}

const styles = StyleSheet.create({
  base: {
    backgroundColor: theme.colors.gray200,
  },
});
