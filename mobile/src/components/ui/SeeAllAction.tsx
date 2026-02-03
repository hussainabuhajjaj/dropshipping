import { Pressable, StyleSheet, Text, View } from 'react-native';
import { CircleIconButton } from './CircleIconButton';
import { theme } from '@/src/theme';

type SeeAllActionProps = {
  label?: string;
  onPress?: () => void;
};

export function SeeAllAction({ label = 'See All', onPress }: SeeAllActionProps) {
  return (
    <Pressable style={styles.wrap} onPress={onPress} accessibilityRole="button">
      <Text style={styles.text}>{label}</Text>
      <CircleIconButton icon="arrow-right" size={theme.moderateScale(26)} variant="filled" />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  wrap: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.moderateScale(8),
  },
  text: {
    fontSize: theme.moderateScale(12),
    fontWeight: '600',
    color: theme.colors.ink,
  },
});
