import { Feather } from '@expo/vector-icons';
import {
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  TextInputProps,
  View,
  ViewStyle,
} from 'react-native';
import { theme } from '@/src/theme';

type SearchBarProps = {
  placeholder?: string;
  value?: string;
  onChangeText?: (text: string) => void;
  onClear?: () => void;
  onRightPress?: () => void;
  onPress?: () => void;
  onFocus?: TextInputProps['onFocus'];
  onSubmitEditing?: TextInputProps['onSubmitEditing'];
  returnKeyType?: TextInputProps['returnKeyType'];
  autoFocus?: TextInputProps['autoFocus'];
  rightIcon?: keyof typeof Feather.glyphMap;
  rightIconColor?: string;
  rightIconBackground?: string;
  rightIconBorder?: string;
  style?: ViewStyle;
  readOnly?: boolean;
  showSearchIcon?: boolean;
};

export function SearchBar({
  placeholder = 'Search',
  value,
  onChangeText,
  onClear,
  onRightPress,
  onPress,
  onFocus,
  onSubmitEditing,
  returnKeyType,
  autoFocus,
  rightIcon = 'camera',
  rightIconColor = theme.colors.primary,
  rightIconBackground = theme.colors.white,
  rightIconBorder = theme.colors.primarySoft,
  style,
  readOnly,
  showSearchIcon = true,
}: SearchBarProps) {
  const TextContainer = onPress ? Pressable : View;

  return (
    <View style={[styles.container, style]}>
      {showSearchIcon ? (
        <Feather name="search" size={14} color={theme.colors.mutedLight} />
      ) : null}
      {readOnly ? (
        <TextContainer
          style={styles.textWrap}
          onPress={onPress}
          accessibilityRole={onPress ? 'button' : undefined}
        >
          <Text style={styles.valueText}>{value || placeholder}</Text>
        </TextContainer>
      ) : (
        <TextInput
          style={styles.input}
          placeholder={placeholder}
          placeholderTextColor={theme.colors.mutedLight}
          value={value}
          onChangeText={onChangeText}
          onFocus={onFocus}
          onSubmitEditing={onSubmitEditing}
          returnKeyType={returnKeyType}
          autoFocus={autoFocus}
        />
      )}
      {value ? (
        <Pressable onPress={onClear} style={styles.clearButton}>
          <Feather name="x" size={12} color={theme.colors.primary} />
        </Pressable>
      ) : null}
      <Pressable
        onPress={onRightPress}
        style={[
          styles.rightButton,
          { backgroundColor: rightIconBackground, borderColor: rightIconBorder },
        ]}
      >
        <Feather name={rightIcon} size={16} color={rightIconColor} />
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    height: theme.moderateScale(36),
    borderRadius: theme.moderateScale(18),
    backgroundColor: theme.colors.primarySoftLight,
    paddingHorizontal: theme.moderateScale(12),
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.moderateScale(8),
  },
  input: {
    flex: 1,
    fontSize: theme.moderateScale(12),
    color: theme.colors.ink,
  },
  textWrap: {
    flex: 1,
  },
  valueText: {
    fontSize: theme.moderateScale(12),
    color: theme.colors.primary,
    fontWeight: '600',
  },
  clearButton: {
    width: theme.moderateScale(20),
    height: theme.moderateScale(20),
    borderRadius: theme.moderateScale(10),
    backgroundColor: theme.colors.primarySoft,
    alignItems: 'center',
    justifyContent: 'center',
  },
  rightButton: {
    width: theme.moderateScale(24),
    height: theme.moderateScale(24),
    borderRadius: theme.moderateScale(12),
    backgroundColor: theme.colors.white,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: theme.colors.primarySoft,
  },
});
