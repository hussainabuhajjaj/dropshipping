import { Pressable, StyleSheet, View } from 'react-native';
import { Text } from '@/src/components/i18n/Text';
import { Feather } from '@expo/vector-icons';
import { theme } from '@/src/theme';

type OptionPillProps = {
  label: string;
  selected?: boolean;
  backgroundColor: string;
  onPress?: () => void;
};

export function OptionPill({ label, selected, backgroundColor, onPress }: OptionPillProps) {
  return (
    <Pressable
      style={[
        styles.container,
        { backgroundColor },
        selected ? styles.selected : null,
      ]}
      onPress={onPress}
      accessibilityRole="button"
    >
      <Text style={styles.label}>{label}</Text>
      <View style={[styles.circle, selected ? styles.circleSelected : null]}>
        {selected ? <Feather name="check" size={12} color={theme.colors.white} /> : null}
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  container: {
    height: theme.moderateScale(44),
    borderRadius: theme.moderateScale(22),
    paddingHorizontal: theme.moderateScale(18),
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  selected: {
    borderWidth: 1,
    borderColor: theme.colors.primary,
  },
  label: {
    fontSize: theme.moderateScale(14),
    fontWeight: '600',
    color: theme.colors.ink,
  },
  circle: {
    width: theme.moderateScale(22),
    height: theme.moderateScale(22),
    borderRadius: theme.moderateScale(11),
    borderWidth: 1,
    borderColor: theme.colors.primary,
    backgroundColor: theme.colors.white,
    alignItems: 'center',
    justifyContent: 'center',
  },
  circleSelected: {
    backgroundColor: theme.colors.primary,
  },
});
