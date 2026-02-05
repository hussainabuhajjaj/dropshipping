import { Feather } from '@expo/vector-icons';
import { Pressable, StyleSheet, View } from 'react-native';
import { Text } from '@/src/components/i18n/Text';
import { theme } from '@/src/theme';

type SectionHeaderProps = {
  title: string;
  actionLabel?: string;
  onPress?: () => void;
  showArrow?: boolean;
};

export function SectionHeader({ title, actionLabel, onPress, showArrow }: SectionHeaderProps) {
  return (
    <View style={styles.row}>
      <Text style={styles.title}>{title}</Text>
      {actionLabel ? (
        <Pressable style={styles.action} onPress={onPress} accessibilityRole="button">
          <Text style={styles.actionText}>{actionLabel}</Text>
          {showArrow ? <Feather name="arrow-right" size={14} color={theme.colors.white} /> : null}
        </Pressable>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  title: {
    fontSize: theme.moderateScale(18),
    fontWeight: '700',
    color: theme.colors.ink,
  },
  action: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.moderateScale(8),
    backgroundColor: theme.colors.primary,
    borderRadius: theme.moderateScale(14),
    paddingHorizontal: theme.moderateScale(12),
    paddingVertical: theme.moderateScale(6),
  },
  actionText: {
    fontSize: theme.moderateScale(12),
    fontWeight: '600',
    color: theme.colors.white,
  },
});
