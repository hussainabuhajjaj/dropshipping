import { Feather } from '@expo/vector-icons';
import { StyleSheet, Text, View } from 'react-native';
import { theme } from '@/constants/theme';

const badges = [
  { id: 'secure', label: 'Secure pay', icon: 'shield' },
  { id: 'dispatch', label: 'Fast dispatch', icon: 'zap' },
  { id: 'buyer', label: 'Buyer protection', icon: 'check-circle' },
];

export const TrustBadges = () => {
  return (
    <View style={styles.row}>
      {badges.map((badge) => (
        <View key={badge.id} style={styles.badge}>
          <Feather name={badge.icon as any} size={14} color={theme.colors.ink} />
          <Text style={styles.label}>{badge.label}</Text>
        </View>
      ))}
    </View>
  );
};

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    gap: theme.spacing.sm,
    flexWrap: 'wrap',
  },
  badge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: theme.radius.pill,
    backgroundColor: theme.colors.surface,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  label: {
    fontSize: 11,
    fontWeight: '600',
    color: theme.colors.ink,
  },
});
