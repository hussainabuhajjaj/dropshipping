import { Feather } from '@expo/vector-icons';
import { Pressable, StyleSheet, ViewStyle } from 'react-native';
import { theme } from '@/src/theme';

type IconCircleButtonProps = {
  icon: keyof typeof Feather.glyphMap;
  onPress?: () => void;
  size?: number;
  style?: ViewStyle;
  accessibilityLabel?: string;
};

export function IconCircleButton({
  icon,
  onPress,
  size,
  style,
  accessibilityLabel,
}: IconCircleButtonProps) {
  const resolvedSize = size ?? theme.moderateScale(28);
  return (
    <Pressable
      style={[
        styles.button,
        { width: resolvedSize, height: resolvedSize, borderRadius: resolvedSize / 2 },
        style,
      ]}
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel}
      hitSlop={8}
    >
      <Feather name={icon} size={Math.max(12, resolvedSize * 0.5)} color={theme.colors.white} />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  button: {
    backgroundColor: theme.colors.primary,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
