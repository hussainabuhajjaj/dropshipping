import { StyleSheet, View } from 'react-native';
import { theme } from '@/src/theme';

type PaginationDotsProps = {
  total?: number;
  activeIndex?: number;
};

export function PaginationDots({ total = 4, activeIndex = 0 }: PaginationDotsProps) {
  return (
    <View style={styles.row}>
      {Array.from({ length: total }).map((_, index) => (
        <View
          key={`dot-${index}`}
          style={[styles.dot, index === activeIndex ? styles.active : null]}
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
    width: theme.moderateScale(12),
    height: theme.moderateScale(12),
    borderRadius: theme.moderateScale(6),
    backgroundColor: theme.colors.primarySoftAlt,
  },
  active: {
    backgroundColor: theme.colors.primary,
  },
});
