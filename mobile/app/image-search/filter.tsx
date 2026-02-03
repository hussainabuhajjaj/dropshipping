import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
const categories = [
  { id: 'dresses', label: 'Dresses', tone: '#f6d9c8' },
  { id: 'pants', label: 'Pants', tone: '#f2caa4' },
  { id: 'skirts', label: 'Skirts', tone: '#f1d58a' },
  { id: 'shorts', label: 'Shorts', tone: '#f4a24f' },
  { id: 'jackets', label: 'Jackets', tone: '#f3d1c0' },
  { id: 'hoodies', label: 'Hoodies', tone: '#f0b59b' },
  { id: 'shirts', label: 'Shirts', tone: '#f6d0a8' },
  { id: 'polo', label: 'Polo', tone: '#eac18f' },
  { id: 'tshirts', label: 'T-Shirts', tone: '#f3b9a3' },
  { id: 'tunics', label: 'Tunics', tone: '#f0d1bf' },
];
const sizes = ['XS', 'S', 'M', 'L', 'XL', '2XL'];
const colors = [theme.colors.white, '#1f1f1f', theme.colors.sun, '#ff4d4d', '#24b7c4', '#f4b328', '#9a55ff'];
const sortOptions = ['Popular', 'Newest', 'Price High to Low', 'Price Low to High'];

export default function ImageSearchFilterScreen() {
  const [selectedCats, setSelectedCats] = useState<string[]>(['dresses', 'pants', 'tshirts']);
  const [selectedSize, setSelectedSize] = useState('M');
  const [selectedColor, setSelectedColor] = useState(theme.colors.white);
  const [activeSort, setActiveSort] = useState('Popular');
  const [tab, setTab] = useState<'clothes' | 'shoes'>('clothes');

  const toggleCategory = (id: string) => {
    setSelectedCats((prev) => (prev.includes(id) ? prev.filter((item) => item !== id) : [...prev, id]));
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
      <View style={styles.headerRow}>
        <Text style={styles.title}>Filter</Text>
        <Pressable style={styles.iconButton} onPress={() => router.back()}>
          <Feather name="x" size={16} color={theme.colors.inkDark} />
        </Pressable>
      </View>

      <View style={styles.categoryRow}>
        {categories.map((item) => {
          const active = selectedCats.includes(item.id);
          return (
            <Pressable key={item.id} style={styles.categoryItem} onPress={() => toggleCategory(item.id)}>
              <View style={[styles.categoryThumb, { backgroundColor: item.tone }]}>
                {active ? (
                  <View style={styles.categoryCheck}>
                    <Feather name="check" size={12} color={theme.colors.inkDark} />
                  </View>
                ) : null}
              </View>
              <Text style={styles.categoryLabel}>{item.label}</Text>
            </Pressable>
          );
        })}
      </View>

      <View style={styles.sectionHeader}>
        <Text style={styles.sectionTitle}>Size</Text>
        <View style={styles.tabRow}>
          <Pressable
            style={[styles.tabButton, tab === 'clothes' ? styles.tabActive : null]}
            onPress={() => setTab('clothes')}
          >
            <Text style={[styles.tabText, tab === 'clothes' ? styles.tabTextActive : null]}>Clothes</Text>
          </Pressable>
          <Pressable
            style={[styles.tabButton, tab === 'shoes' ? styles.tabActive : null]}
            onPress={() => setTab('shoes')}
          >
            <Text style={[styles.tabText, tab === 'shoes' ? styles.tabTextActive : null]}>Shoes</Text>
          </Pressable>
        </View>
      </View>
      <View style={styles.sizeRow}>
        {sizes.map((size) => {
          const active = selectedSize === size;
          return (
            <Pressable
              key={size}
              style={[styles.sizeChip, active ? styles.sizeActive : null]}
              onPress={() => setSelectedSize(size)}
            >
              <Text style={[styles.sizeText, active ? styles.sizeTextActive : null]}>{size}</Text>
            </Pressable>
          );
        })}
      </View>

      <Text style={styles.sectionTitle}>Color</Text>
      <View style={styles.colorRow}>
        {colors.map((color) => {
          const active = selectedColor === color;
          const checkColor = color === theme.colors.white ? theme.colors.sun : theme.colors.white;
          return (
            <Pressable key={color} onPress={() => setSelectedColor(color)} style={styles.colorWrap}>
              <View style={[styles.colorDot, { backgroundColor: color }, active ? styles.colorActive : null]}>
                {active ? <Feather name="check" size={12} color={checkColor} /> : null}
              </View>
            </Pressable>
          );
        })}
      </View>

      <View style={styles.priceHeader}>
        <Text style={styles.sectionTitle}>Price</Text>
        <Text style={styles.priceRange}>$10 — $150</Text>
      </View>
      <View style={styles.slider}>
        <View style={styles.sliderTrack} />
        <View style={styles.sliderActive} />
        <View style={[styles.sliderThumb, styles.thumbLeft]} />
        <View style={[styles.sliderThumb, styles.thumbRight]} />
      </View>

      <View style={styles.sortRow}>
        {sortOptions.slice(0, 2).map((option) => (
          <Pressable
            key={option}
            style={[styles.sortChip, activeSort === option ? styles.sortActive : null]}
            onPress={() => setActiveSort(option)}
          >
            <Text style={[styles.sortText, activeSort === option ? styles.sortTextActive : null]}>{option}</Text>
            {activeSort === option ? (
              <View style={styles.sortCheck}>
                <Feather name="check" size={10} color={theme.colors.inkDark} />
              </View>
            ) : null}
          </Pressable>
        ))}
      </View>
      <View style={styles.sortRow}>
        {sortOptions.slice(2).map((option) => (
          <Pressable
            key={option}
            style={[styles.sortChip, activeSort === option ? styles.sortActive : null]}
            onPress={() => setActiveSort(option)}
          >
            <Text style={[styles.sortText, activeSort === option ? styles.sortTextActive : null]}>{option}</Text>
          </Pressable>
        ))}
      </View>

      <View style={styles.actionRow}>
        <Pressable style={styles.clearButton}>
          <Text style={styles.clearText}>Clear</Text>
        </Pressable>
        <Pressable style={styles.applyButton} onPress={() => router.push('/image-search/results')}>
          <Text style={styles.applyText}>Apply</Text>
        </Pressable>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.white,
  },
  content: {
    paddingHorizontal: 20,
    paddingTop: 10,
    paddingBottom: 28,
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 16,
  },
  title: {
    fontSize: 22,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  iconButton: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: theme.colors.white,
    borderWidth: 1,
    borderColor: theme.colors.sand,
    alignItems: 'center',
    justifyContent: 'center',
  },
  categoryRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
    rowGap: 14,
    marginBottom: 22,
  },
  categoryItem: {
    width: '20%',
    alignItems: 'center',
  },
  categoryThumb: {
    width: 48,
    height: 48,
    borderRadius: 24,
    alignItems: 'center',
    justifyContent: 'center',
  },
  categoryCheck: {
    position: 'absolute',
    top: -2,
    right: -2,
    width: 18,
    height: 18,
    borderRadius: 9,
    backgroundColor: theme.colors.sun,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 2,
    borderColor: theme.colors.white,
  },
  categoryLabel: {
    marginTop: 6,
    fontSize: 11,
    color: theme.colors.inkDark,
    textAlign: 'center',
  },
  sectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 10,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  tabRow: {
    flexDirection: 'row',
    gap: 8,
    backgroundColor: theme.colors.sand,
    padding: 4,
    borderRadius: 16,
  },
  tabButton: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 12,
  },
  tabActive: {
    backgroundColor: theme.colors.white,
    borderWidth: 1,
    borderColor: theme.colors.sun,
  },
  tabText: {
    fontSize: 12,
    color: theme.colors.mutedDark,
    fontWeight: '600',
  },
  tabTextActive: {
    color: theme.colors.inkDark,
    fontWeight: '700',
  },
  sizeRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
    marginBottom: 20,
  },
  sizeChip: {
    width: 46,
    height: 32,
    borderRadius: 16,
    backgroundColor: theme.colors.sand,
    alignItems: 'center',
    justifyContent: 'center',
  },
  sizeActive: {
    backgroundColor: theme.colors.white,
    borderWidth: 1,
    borderColor: theme.colors.sun,
    shadowColor: theme.colors.sun,
    shadowOpacity: 0.2,
    shadowRadius: 6,
    shadowOffset: { width: 0, height: 3 },
    elevation: 2,
  },
  sizeText: {
    fontSize: 12,
    color: theme.colors.mutedDark,
    fontWeight: '600',
  },
  sizeTextActive: {
    color: theme.colors.inkDark,
    fontWeight: '700',
  },
  colorRow: {
    flexDirection: 'row',
    gap: 12,
    marginTop: 10,
    marginBottom: 22,
  },
  colorWrap: {
    width: 30,
    height: 30,
    borderRadius: 15,
    alignItems: 'center',
    justifyContent: 'center',
  },
  colorDot: {
    width: 28,
    height: 28,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: theme.colors.sand,
    alignItems: 'center',
    justifyContent: 'center',
  },
  colorActive: {
    borderWidth: 2,
    borderColor: theme.colors.sun,
  },
  priceHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  priceRange: {
    fontSize: 13,
    color: theme.colors.inkDark,
  },
  slider: {
    marginTop: 12,
    marginBottom: 18,
    height: 24,
    justifyContent: 'center',
  },
  sliderTrack: {
    height: 4,
    borderRadius: 2,
    backgroundColor: theme.colors.sand,
  },
  sliderActive: {
    position: 'absolute',
    left: '20%',
    right: '25%',
    height: 4,
    borderRadius: 2,
    backgroundColor: theme.colors.sun,
  },
  sliderThumb: {
    position: 'absolute',
    width: 24,
    height: 24,
    borderRadius: 12,
    backgroundColor: theme.colors.white,
    borderWidth: 2,
    borderColor: theme.colors.sun,
    shadowColor: theme.colors.sun,
    shadowOpacity: 0.2,
    shadowRadius: 6,
    shadowOffset: { width: 0, height: 3 },
    elevation: 2,
  },
  thumbLeft: {
    left: '16%',
  },
  thumbRight: {
    right: '22%',
  },
  sortRow: {
    flexDirection: 'row',
    gap: 12,
    marginBottom: 12,
  },
  sortChip: {
    flex: 1,
    borderRadius: 16,
    paddingVertical: 10,
    alignItems: 'center',
    backgroundColor: theme.colors.sand,
    flexDirection: 'row',
    justifyContent: 'center',
    gap: 6,
  },
  sortActive: {
    backgroundColor: theme.colors.white,
    borderWidth: 1,
    borderColor: theme.colors.sun,
  },
  sortText: {
    fontSize: 12,
    color: theme.colors.inkDark,
    fontWeight: '600',
  },
  sortTextActive: {
    color: theme.colors.inkDark,
    fontWeight: '700',
  },
  sortCheck: {
    width: 18,
    height: 18,
    borderRadius: 9,
    backgroundColor: theme.colors.sun,
    alignItems: 'center',
    justifyContent: 'center',
  },
  actionRow: {
    flexDirection: 'row',
    gap: 12,
    marginTop: 8,
  },
  clearButton: {
    flex: 1,
    borderWidth: 1,
    borderColor: theme.colors.sun,
    borderRadius: 20,
    paddingVertical: 12,
    alignItems: 'center',
    backgroundColor: theme.colors.white,
  },
  clearText: {
    fontSize: 14,
    fontWeight: '700',
    color: theme.colors.sun,
  },
  applyButton: {
    flex: 1,
    backgroundColor: theme.colors.sun,
    borderRadius: 20,
    paddingVertical: 12,
    alignItems: 'center',
  },
  applyText: {
    fontSize: 14,
    fontWeight: '700',
    color: theme.colors.white,
  },
});

