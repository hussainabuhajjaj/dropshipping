import { Image, Pressable, StyleSheet, Text, View } from 'react-native';
import { theme } from '@/constants/theme';
import { Category } from '@/lib/mockData';

export const CategoryPill = ({
  category,
  onPress,
}: {
  category: Category;
  onPress?: () => void;
}) => {
  return (
    <Pressable style={styles.card} onPress={onPress}>
      <View style={[styles.iconWrap, { backgroundColor: category.accent }]}>
        {category.image ? (
          <Image source={{ uri: category.image }} style={styles.icon} />
        ) : (
          <View style={styles.iconFallback}>
            <Text style={styles.iconFallbackText}>{category.name.slice(0, 1).toUpperCase()}</Text>
          </View>
        )}
      </View>
      <Text style={styles.name}>{category.name}</Text>
      <Text style={styles.count}>{category.count}+ items</Text>
    </Pressable>
  );
};

const styles = StyleSheet.create({
  card: {
    width: 140,
    padding: theme.spacing.sm,
    borderRadius: theme.radius.lg,
    backgroundColor: theme.colors.surface,
    borderWidth: 1,
    borderColor: theme.colors.border,
    marginRight: theme.spacing.sm,
  },
  iconWrap: {
    borderRadius: theme.radius.md,
    padding: theme.spacing.xs,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: theme.spacing.sm,
  },
  icon: {
    width: 60,
    height: 60,
    borderRadius: theme.radius.md,
  },
  iconFallback: {
    width: 60,
    height: 60,
    borderRadius: theme.radius.md,
    backgroundColor: theme.colors.surface,
    alignItems: 'center',
    justifyContent: 'center',
  },
  iconFallbackText: {
    fontSize: 18,
    fontWeight: '700',
    color: theme.colors.ink,
  },
  name: {
    fontSize: 13,
    fontWeight: '700',
    color: theme.colors.ink,
  },
  count: {
    fontSize: 11,
    color: theme.colors.muted,
    marginTop: 4,
  },
});
