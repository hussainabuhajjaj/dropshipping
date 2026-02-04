import { Feather } from '@expo/vector-icons';
import { Pressable, StyleSheet, ViewStyle } from 'react-native';
import { theme } from '@/src/theme';

type CircleIconButtonProps = {
  icon: keyof typeof Feather.glyphMap;
  onPress?: () => void;
  size?: number;
  variant?: 'filled' | 'outlined';
  style?: ViewStyle;
  iconColor?: string;
  backgroundColor?: string;
  borderColor?: string;
  accessibilityLabel?: string;
};

export function CircleIconButton({
  icon,
  onPress,
  size,
  variant = 'outlined',
  style,
  iconColor,
  backgroundColor,
  borderColor,
  accessibilityLabel,
}: CircleIconButtonProps) {
  const resolvedSize = size ?? theme.moderateScale(32);
  const isFilled = variant === 'filled';
  const resolvedBackground = backgroundColor ?? (isFilled ? theme.colors.primary : theme.colors.white);
  const resolvedBorder = borderColor ?? (isFilled ? theme.colors.primary : theme.colors.border);
  const resolvedIcon = iconColor ?? (isFilled ? theme.colors.white : theme.colors.ink);

  return (
    <Pressable
      style={[
        styles.base,
        {
          width: resolvedSize,
          height: resolvedSize,
          borderRadius: resolvedSize / 2,
          backgroundColor: resolvedBackground,
          borderColor: resolvedBorder,
        },
        style,
      ]}
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel}
      hitSlop={8}
    >
      <Feather
        name={icon}
        size={Math.max(14, resolvedSize * 0.5)}
        color={resolvedIcon}
      />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  base: {
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
