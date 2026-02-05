import { Image, Pressable, StyleSheet, View } from 'react-native';
import { Text } from '@/src/components/i18n/Text';
import { Skeleton } from '@/src/components/ui/Skeleton';
import { theme } from '@/src/theme';

type CategoryCardProps = {
  label?: string;
  count?: number;
  previews?: Array<{ id?: string | number; image_url?: string | null }>;
  width: number;
  onPress?: () => void;
  loading?: boolean;
};

export function CategoryCard({ label, count, previews = [], width, onPress, loading = false }: CategoryCardProps) {
  if (loading) {
    return (
      <View style={[styles.card, { width }]}>
        <View style={styles.tiles}>
          {[0, 1, 2, 3].map((index) => (
            <Skeleton
              key={`tile-${index}`}
              height={theme.moderateScale(32)}
              width={theme.moderateScale(32)}
              radius={theme.moderateScale(8)}
            />
          ))}
        </View>
        <View style={styles.footer}>
          <Skeleton height={theme.moderateScale(12)} radius={theme.moderateScale(6)} width="60%" />
          <Skeleton height={theme.moderateScale(16)} radius={theme.moderateScale(10)} width={theme.moderateScale(36)} />
        </View>
      </View>
    );
  }

  return (
    <Pressable
      style={[styles.card, { width }]}
      onPress={onPress}
      accessibilityRole={onPress ? 'button' : undefined}
    >
      <View style={styles.tiles}>
        {[0, 1, 2, 3].map((index) => {
          const preview = previews[index];
          const imageUrl = preview?.image_url ?? null;
          return (
            <View key={preview?.id ?? `tile-${index}`} style={styles.tile}>
              {imageUrl ? <Image source={{ uri: imageUrl }} style={styles.tileImage} resizeMode="cover" /> : null}
            </View>
          );
        })}
      </View>
      <View style={styles.footer}>
        <Text style={styles.label}>{label ?? ''}</Text>
        <View style={styles.count}>
          <Text style={styles.countText}>{count ?? 0}</Text>
        </View>
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: theme.colors.white,
    borderRadius: theme.moderateScale(16),
    padding: theme.moderateScale(12),
    borderWidth: 1,
    borderColor: theme.colors.borderSoft,
  },
  tiles: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: theme.moderateScale(6),
  },
  tile: {
    width: theme.moderateScale(32),
    height: theme.moderateScale(32),
    borderRadius: theme.moderateScale(8),
    backgroundColor: theme.colors.primarySoft,
    overflow: 'hidden',
  },
  tileImage: {
    width: '100%',
    height: '100%',
  },
  footer: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginTop: theme.moderateScale(10),
  },
  label: {
    fontSize: theme.moderateScale(13),
    fontWeight: '600',
    color: theme.colors.ink,
  },
  count: {
    backgroundColor: theme.colors.primarySoft,
    borderRadius: theme.moderateScale(10),
    paddingHorizontal: theme.moderateScale(8),
    paddingVertical: theme.moderateScale(2),
  },
  countText: {
    fontSize: theme.moderateScale(11),
    color: theme.colors.primary,
    fontWeight: '600',
  },
});
