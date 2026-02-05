import { Pressable, StyleProp, StyleSheet, TextStyle, ViewStyle } from 'react-native';
import { Text } from '@/src/components/i18n/Text';
import { theme } from '@/src/theme';

type PrimaryButtonProps = {
  label: string;
  onPress?: () => void;
  disabled?: boolean;
  style?: StyleProp<ViewStyle>;
  textStyle?: StyleProp<TextStyle>;
};

export function PrimaryButton({ label, onPress, disabled, style, textStyle }: PrimaryButtonProps) {
  return (
    <Pressable
      style={[styles.button, disabled ? styles.buttonDisabled : null, style]}
      onPress={onPress}
      accessibilityRole="button"
      accessibilityState={{ disabled: Boolean(disabled) }}
      disabled={disabled}
    >
      <Text style={[styles.text, textStyle]}>{label}</Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  button: {
    width: '100%',
    backgroundColor: theme.colors.primary,
    borderRadius: theme.radius.xl,
    paddingVertical: theme.spacing.md,
    alignItems: 'center',
  },
  buttonDisabled: {
    opacity: 0.5,
  },
  text: {
    color: theme.colors.white,
    fontSize: theme.moderateScale(16),
    fontWeight: '600',
  },
});
