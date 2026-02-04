import { Feather } from '@expo/vector-icons';
import { StyleSheet, Text, View } from 'react-native';
import { theme } from '@/constants/theme';

export const TopStripCard = ({
  icon,
  title,
  subtitle,
}: {
  icon: string;
  title: string;
  subtitle: string;
}) => {
  const trimmedIcon = icon?.trim();
  const isFeatherIcon =
    !!trimmedIcon && Object.prototype.hasOwnProperty.call(Feather.glyphMap, trimmedIcon);

  return (
    <View style={styles.card}>
      <View style={styles.iconWrap}>
        {isFeatherIcon ? (
          <Feather name={trimmedIcon as any} size={16} color={theme.colors.ink} />
        ) : (
          <Text style={styles.iconFallback}>{trimmedIcon || '?'}</Text>
        )}
      </View>
      <View style={styles.copy}>
        <Text style={styles.title}>{title}</Text>
        <Text style={styles.subtitle}>{subtitle}</Text>
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  card: {
    flexDirection: 'row',
    gap: theme.spacing.sm,
    padding: theme.spacing.sm,
    backgroundColor: theme.colors.surface,
    borderRadius: theme.radius.lg,
    borderWidth: 1,
    borderColor: theme.colors.border,
    alignItems: 'center',
  },
  iconWrap: {
    width: 40,
    height: 40,
    borderRadius: theme.radius.md,
    backgroundColor: theme.colors.chip,
    alignItems: 'center',
    justifyContent: 'center',
  },
  iconFallback: {
    fontSize: 16,
    fontWeight: '700',
    color: theme.colors.ink,
  },
  copy: {
    flex: 1,
  },
  title: {
    fontSize: 12,
    fontWeight: '700',
    color: theme.colors.ink,
  },
  subtitle: {
    fontSize: 11,
    color: theme.colors.muted,
  },
});
