import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
import { SafeAreaView } from 'react-native-safe-area-context';
const recentSearches = ['White sneakers', 'Ribbed top', 'Wide leg jeans', 'Mini bag'];

export default function ImageSearchScreen() {
  return (
    <SafeAreaView style={styles.container}>
      <ScrollView style={styles.scroll} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.headerRow}>
          <Pressable style={styles.iconButton} onPress={() => router.back()}>
            <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
          </Pressable>
          <Text style={styles.title}>Image Search</Text>
          <Pressable style={styles.iconButton} onPress={() => router.push('/(tabs)/home')}>
            <Feather name="x" size={16} color={theme.colors.inkDark} />
          </Pressable>
        </View>

        <View style={styles.heroCard}>
          <View style={styles.heroBadge}>
            <Feather name="image" size={14} color={theme.colors.inkDark} />
            <Text style={styles.heroBadgeText}>Search with image</Text>
          </View>
          <Text style={styles.heroTitle}>Drop a photo to find matching items.</Text>
          <Pressable style={styles.heroButton} onPress={() => router.push('/image-search/recognizing')}>
            <Text style={styles.heroButtonText}>Upload image</Text>
          </Pressable>
        </View>

        <Text style={styles.sectionTitle}>Recent searches</Text>
        <View style={styles.chipRow}>
          {recentSearches.map((item) => (
            <Pressable key={item} style={styles.chip} onPress={() => router.push('/image-search/results')}>
              <Text style={styles.chipText}>{item}</Text>
            </Pressable>
          ))}
        </View>

        <Pressable style={styles.helperCard} onPress={() => router.push('/image-search/recognizing')}>
          <View style={styles.helperIcon}>
            <Feather name="camera" size={16} color={theme.colors.inkDark} />
          </View>
          <View style={styles.helperCopy}>
            <Text style={styles.helperTitle}>Try scanning now</Text>
            <Text style={styles.helperBody}>Best results with clear lighting.</Text>
          </View>
          <Feather name="chevron-right" size={16} color={theme.colors.inkDark} />
        </Pressable>
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.white,
  },
  scroll: {
    flex: 1,
  },
  content: {
    paddingHorizontal: 20,
    paddingTop: 12,
    paddingBottom: 32,
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 16,
  },
  title: {
    fontSize: 20,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  iconButton: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: theme.colors.sand,
    alignItems: 'center',
    justifyContent: 'center',
  },
  heroCard: {
    borderRadius: 20,
    padding: 18,
    backgroundColor: theme.colors.blueSoft,
  },
  heroBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    alignSelf: 'flex-start',
    backgroundColor: theme.colors.white,
    borderRadius: 14,
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  heroBadgeText: {
    fontSize: 11,
    fontWeight: '700',
    color: theme.colors.sun,
  },
  heroTitle: {
    marginTop: 12,
    fontSize: 16,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  heroButton: {
    marginTop: 16,
    backgroundColor: theme.colors.sun,
    borderRadius: 18,
    paddingVertical: 12,
    alignItems: 'center',
  },
  heroButtonText: {
    fontSize: 13,
    fontWeight: '700',
    color: theme.colors.white,
  },
  sectionTitle: {
    marginTop: 24,
    fontSize: 14,
    fontWeight: '700',
    color: theme.colors.inkDark,
    marginBottom: 12,
  },
  chipRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
  },
  chip: {
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 18,
    backgroundColor: theme.colors.sand,
  },
  chipText: {
    fontSize: 12,
    fontWeight: '600',
    color: theme.colors.inkDark,
  },
  helperCard: {
    marginTop: 24,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    padding: 14,
    borderRadius: 18,
    backgroundColor: theme.colors.sand,
  },
  helperIcon: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: theme.colors.sun,
    alignItems: 'center',
    justifyContent: 'center',
  },
  helperCopy: {
    flex: 1,
  },
  helperTitle: {
    fontSize: 13,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  helperBody: {
    marginTop: 4,
    fontSize: 11,
    color: theme.colors.mutedDark,
  },
});
