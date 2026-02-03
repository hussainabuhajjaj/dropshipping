import { Feather } from '@expo/vector-icons';
import { StyleSheet, TextInput, View } from 'react-native';
import { theme } from '@/constants/theme';

export const SearchBar = ({
  value,
  onChangeText,
  placeholder = 'Search products',
  onFocus,
}: {
  value: string;
  onChangeText: (text: string) => void;
  placeholder?: string;
  onFocus?: () => void;
}) => {
  return (
    <View style={styles.wrapper}>
      <Feather name="search" size={18} color={theme.colors.muted} />
      <TextInput
        value={value}
        onChangeText={onChangeText}
        placeholder={placeholder}
        placeholderTextColor={theme.colors.muted}
        style={styles.input}
        onFocus={onFocus}
      />
    </View>
  );
};

const styles = StyleSheet.create({
  wrapper: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.sm,
    backgroundColor: theme.colors.surface,
    borderRadius: theme.radius.lg,
    paddingHorizontal: theme.spacing.md,
    paddingVertical: 10,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  input: {
    flex: 1,
    fontSize: 14,
    color: theme.colors.ink,
  },
});
