import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
import { SafeAreaView } from 'react-native-safe-area-context';
const tags = ['Denim', 'Wide leg', 'Blue', 'High waist'];

export default function ImageRecognizedScreen() {
  return (
    <SafeAreaView style={styles.container}>
      <ScrollView style={styles.scroll} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.headerRow}>
          <Pressable style={styles.iconButton} onPress={() => router.back()}>
            <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
          </Pressable>
          <Text style={styles.title}>Image recognized</Text>
          <Pressable style={styles.iconButton} onPress={() => router.push('/(tabs)/home')}>
            <Feather name="x" size={16} color={theme.colors.inkDark} />
          </Pressable>
        </View>

        <View style={styles.previewCard}>
          <View style={styles.previewImage} />
          <View style={styles.checkBadge}>
            <Feather name="check" size={14} color={theme.colors.inkDark} />
          </View>
        </View>

        <Text style={styles.sectionTitle}>We found these details</Text>
        <View style={styles.tagRow}>
          {tags.map((tag) => (
            <View key={tag} style={styles.tag}>
              <Text style={styles.tagText}>{tag}</Text>
            </View>
          ))}
        </View>

        <Pressable style={styles.primaryButton} onPress={() => router.push('/image-search/results')}>
          <Text style={styles.primaryText}>See matching items</Text>
        </Pressable>
        <Pressable style={styles.secondaryButton} onPress={() => router.push('/image-search/recognizing')}>
          <Text style={styles.secondaryText}>Rescan image</Text>
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
    marginBottom: 20,
  },
  title: {
    fontSize: 18,
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
  previewCard: {
    height: 280,
    borderRadius: 24,
    backgroundColor: theme.colors.blueSoftPale,
    overflow: 'hidden',
    justifyContent: 'center',
  },
  previewImage: {
    flex: 1,
    backgroundColor: theme.colors.blueSoftMuted,
  },
  checkBadge: {
    position: 'absolute',
    right: 18,
    bottom: 18,
    width: 32,
    height: 32,
    borderRadius: 16,
    backgroundColor: '#2cc36b',
    alignItems: 'center',
    justifyContent: 'center',
  },
  sectionTitle: {
    marginTop: 20,
    fontSize: 14,
    fontWeight: '700',
    color: theme.colors.inkDark,
    marginBottom: 12,
  },
  tagRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
  },
  tag: {
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 18,
    backgroundColor: theme.colors.sand,
  },
  tagText: {
    fontSize: 12,
    fontWeight: '600',
    color: theme.colors.inkDark,
  },
  primaryButton: {
    marginTop: 24,
    backgroundColor: theme.colors.sun,
    borderRadius: 24,
    paddingVertical: 14,
    alignItems: 'center',
  },
  primaryText: {
    fontSize: 14,
    fontWeight: '700',
    color: theme.colors.white,
  },
  secondaryButton: {
    marginTop: 12,
    backgroundColor: theme.colors.sand,
    borderRadius: 24,
    paddingVertical: 14,
    alignItems: 'center',
  },
  secondaryText: {
    fontSize: 13,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
});
