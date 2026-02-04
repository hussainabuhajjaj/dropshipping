import { ScrollView, StyleSheet, View } from 'react-native';
import { Chip } from '@/src/components/ui/Chip';
import { theme } from '@/src/theme';

type ProductFilterChipsProps = {
  active: string;
  onSelect: (value: string) => void;
  options?: string[];
};

const defaultOptions = ['Trending', 'New', 'Sale', 'Top Rated'];

export function ProductFilterChips({
  active,
  onSelect,
  options = defaultOptions,
}: ProductFilterChipsProps) {
  return (
    <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.row}>
      {options.map((option) => (
        <Chip
          key={option}
          label={option}
          active={active === option}
          onPress={() => onSelect(option)}
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

