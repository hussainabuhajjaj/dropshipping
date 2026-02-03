import { Feather } from '@expo/vector-icons';
import { Pressable, StyleSheet, Text, View, ViewStyle } from 'react-native';
import { theme } from '@/src/theme';

type CategorySearchBarProps = {
  placeholder: string;
  onSearchPress?: () => void;
  onCameraPress?: () => void;
  onPress?: () => void;
  style?: ViewStyle;
};

export function CategorySearchBar({
  placeholder,
  onSearchPress,
  onCameraPress,
  onPress,
  style,
}: CategorySearchBarProps) {
  return (
    <View style={[styles.container, style]}>
      <Pressable style={styles.inputArea} onPress={onPress} accessibilityRole="search">
        <Text style={styles.placeholder}>{placeholder}</Text>
      </Pressable>
      <Pressable
        style={styles.cameraButton}
        onPress={onCameraPress}
        accessibilityRole="button"
        accessibilityLabel="Image search"
        hitSlop={6}
      >
        <Feather name="camera" size={16} color={theme.colors.inkDark} />
      </Pressable>
      <Pressable
        style={styles.searchButton}
        onPress={onSearchPress}
        accessibilityRole="button"
        accessibilityLabel="Search"
        hitSlop={6}
      >
        <Feather name="search" size={16} color={theme.colors.white} />
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    height: theme.moderateScale(40),
    borderRadius: theme.moderateScale(20),
    borderWidth: 1,
    borderColor: theme.colors.gray300,
    backgroundColor: theme.colors.white,
    paddingHorizontal: theme.moderateScale(12),
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.moderateScale(8),
  },
  inputArea: {
    flex: 1,
    justifyContent: 'center',
  },
  placeholder: {
    fontSize: theme.moderateScale(13),
    color: theme.colors.mutedLight,
    fontWeight: '500',
  },
  cameraButton: {
    width: theme.moderateScale(28),
    height: theme.moderateScale(28),
    borderRadius: theme.moderateScale(14),
    backgroundColor: theme.colors.white,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: theme.colors.gray300,
  },
  searchButton: {
    width: theme.moderateScale(30),
    height: theme.moderateScale(30),
    borderRadius: theme.moderateScale(15),
    backgroundColor: theme.colors.inkDark,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
