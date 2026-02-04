import { StyleSheet, View } from 'react-native';
import { theme } from '@/src/theme';

type PinBoxesProps = {
  count?: number;
  size?: number;
};

export function PinBoxes({ count = 4, size }: PinBoxesProps) {
  const resolvedSize = size ?? theme.moderateScale(50);
  return (
    <View style={styles.row}>
      {Array.from({ length: count }).map((_, index) => (
        <View
          key={`box-${index}`}
          style={[
            styles.box,
            { width: resolvedSize, height: resolvedSize, borderRadius: theme.radius.md },
          ]}
        />
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    gap: theme.moderateScale(14),
  },
  box: {
    backgroundColor: theme.colors.input,
  },
});
