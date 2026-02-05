import { Pressable, StyleSheet, TextStyle, ViewStyle } from 'react-native';
import { Text } from '@/src/components/i18n/Text';
import { theme } from '@/src/theme';

type TextButtonProps = {
  label: string;
  onPress?: () => void;
  style?: ViewStyle;
  textStyle?: TextStyle;
};

export function TextButton({ label, onPress, style, textStyle }: TextButtonProps) {
  return (
    <Pressable style={style} onPress={onPress} accessibilityRole="button">
      <Text style={[styles.text, textStyle]}>{label}</Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  text: {
    fontSize: theme.moderateScale(13),
    color: theme.colors.muted,
    fontWeight: '500',
  },
});
