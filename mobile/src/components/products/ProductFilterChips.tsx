import { ScrollView, StyleSheet, View } from 'react-native';
import { Chip } from '@/src/components/ui/Chip';
import { theme } from '@/src/theme';

type ProductFilterChipsProps = {
  active: string;
  onSelect: (value: string) => void;
  options?: Array<{ key: string; label: string }>;
};

const defaultOptions = [
  { key: 'trending', label: 'Trending' },
  { key: 'new', label: 'New' },
  { key: 'sale', label: 'Sale' },
  { key: 'top_rated', label: 'Top Rated' },
];

export function ProductFilterChips({
  active,
  onSelect,
  options = defaultOptions,
}: ProductFilterChipsProps) {
  return (
    <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.row}>
      {options.map((option) => (
        <Chip
          key={option.key}
          label={option.label}
          active={active === option.key}
          onPress={() => onSelect(option.key)}
        />
      ))}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  row: {
    paddingVertical: theme.moderateScale(2),
    gap: theme.moderateScale(10),
  },
});
