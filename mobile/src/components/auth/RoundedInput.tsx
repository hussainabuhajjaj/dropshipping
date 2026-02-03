import { ReactNode } from 'react';
import {
  KeyboardTypeOptions,
  StyleSheet,
  TextInput,
  TextInputProps,
  TextStyle,
  View,
  ViewStyle,
} from 'react-native';
import { theme } from '@/src/theme';

type RoundedInputProps = {
  placeholder: string;
  keyboardType?: KeyboardTypeOptions;
  secureTextEntry?: boolean;
  left?: ReactNode;
  right?: ReactNode;
  containerStyle?: ViewStyle;
  inputStyle?: TextStyle;
  inputProps?: TextInputProps;
};

export function RoundedInput({
  placeholder,
  keyboardType,
  secureTextEntry,
  left,
  right,
  containerStyle,
  inputStyle,
  inputProps,
}: RoundedInputProps) {
  return (
    <View style={[styles.container, containerStyle]}>
      {left ? <View style={styles.side}>{left}</View> : null}
      <TextInput
        style={[styles.input, inputStyle, inputProps?.style]}
        placeholder={placeholder}
        placeholderTextColor={theme.colors.mutedLight}
        autoCapitalize="none"
        keyboardType={keyboardType}
        secureTextEntry={secureTextEntry}
        {...inputProps}
      />
      {right ? <View style={styles.side}>{right}</View> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    height: theme.moderateScale(52),
    borderRadius: theme.moderateScale(26),
    backgroundColor: theme.colors.input,
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: theme.moderateScale(18),
    gap: theme.moderateScale(10),
  },
  input: {
    flex: 1,
    fontSize: theme.moderateScale(14),
    color: theme.colors.ink,
  },
  side: {
    alignItems: 'center',
    justifyContent: 'center',
  },
});
