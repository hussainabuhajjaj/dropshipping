import { Feather } from '@expo/vector-icons';
import { Pressable, StyleSheet, View } from 'react-native';
import { Text } from '@/src/components/i18n/Text';
import { Skeleton } from '@/src/components/ui/Skeleton';
import { theme } from '@/src/theme';

type PopularCardProps = {
  label?: string;
  count?: string;
  onPress?: () => void;
  loading?: boolean;
};

export function PopularCard({ label, count, onPress, loading = false }: PopularCardProps) {
  if (loading) {
    return (
      <View style={styles.card}>
        <Skeleton height={theme.moderateScale(90)} radius={theme.moderateScale(16)} />
        <View style={styles.meta}>
          <Skeleton height={theme.moderateScale(10)} radius={theme.moderateScale(5)} width="35%" />
          <Skeleton height={theme.moderateScale(10)} radius={theme.moderateScale(5)} width="45%" />
        </View>
      </View>
    );
  }

  return (
    <Pressable
      style={styles.card}
      onPress={onPress}
      accessibilityRole={onPress ? 'button' : undefined}
    >
      <View style={styles.image} />
      <View style={styles.meta}>
        <Text style={styles.count}>{count ?? ''}</Text>
        <Text style={styles.label}>{label ?? ''}</Text>
        <Feather name="heart" size={12} color={theme.colors.primary} />
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  card: {
    width: theme.moderateScale(90),
  },
  image: {
    width: theme.moderateScale(90),
    height: theme.moderateScale(90),
    borderRadius: theme.moderateScale(16),
    backgroundColor: theme.colors.primarySoft,
  },
  meta: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginTop: theme.moderateScale(6),
  },
  count: {
    fontSize: theme.moderateScale(12),
    fontWeight: '700',
    color: theme.colors.ink,
  },
  label: {
    fontSize: theme.moderateScale(12),
    color: theme.colors.ink,
  },
});
