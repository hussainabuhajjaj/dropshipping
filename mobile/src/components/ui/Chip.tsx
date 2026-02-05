import { Pressable, StyleSheet, ViewStyle } from 'react-native';
import { Text } from '@/src/components/i18n/Text';
import { theme } from '@/src/theme';

type ChipProps = {
  label: string;
  active?: boolean;
  onPress?: () => void;
  style?: ViewStyle;
};

export function Chip({ label, active, onPress, style }: ChipProps) {
  return (
    <Pressable
      style={[styles.base, active ? styles.active : null, style]}
      onPress={onPress}
      accessibilityRole="button"
    >
      <Text style={[styles.text, active ? styles.textActive : null]}>{label}</Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  base: {
    paddingHorizontal: theme.moderateScale(14),
    paddingVertical: theme.moderateScale(8),
    borderRadius: theme.moderateScale(18),
    backgroundColor: theme.colors.chip,
  },
  active: {
    backgroundColor: theme.colors.primarySoft,
    borderWidth: 1,
    borderColor: theme.colors.primary,
  },
  text: {
    fontSize: theme.moderateScale(12),
    color: theme.colors.ink,
    fontWeight: '500',
  },
  textActive: {
    color: theme.colors.primary,
    fontWeight: '600',
  },
});
