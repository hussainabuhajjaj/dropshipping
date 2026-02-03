import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
export default function StoryBannerScreen() {
  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
      <View style={styles.progressRow}>
        {[0, 1, 2, 3].map((index) => (
          <View key={index} style={[styles.progressBar, index < 4 ? styles.progressActive : null]} />
        ))}
      </View>

      <View style={styles.headerRow}>
        <Pressable style={styles.iconButton} onPress={() => router.back()}>
          <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
        </Pressable>
        <Text style={styles.title}>Story</Text>
        <Pressable style={styles.iconButton} onPress={() => router.push('/(tabs)/home')}>
          <Feather name="x" size={16} color={theme.colors.inkDark} />
        </Pressable>
      </View>

      <View style={styles.storyCard}>
        <View style={styles.storyImage} />
        <View style={styles.bannerCard}>
          <Text style={styles.bannerEyebrow}>NEW</Text>
          <Text style={styles.bannerTitle}>Summer drop</Text>
          <Text style={styles.bannerBody}>Lightweight pieces for the heat.</Text>
          <Pressable style={styles.bannerButton} onPress={() => router.push('/flash-sale')}>
            <Text style={styles.bannerButtonText}>Shop drop</Text>
          </Pressable>
        </View>
      </View>

      <Pressable style={styles.primaryButton} onPress={() => router.push('/stories/product-2')}>
        <Text style={styles.primaryText}>Next</Text>
      </Pressable>
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
    paddingTop: 12,
    paddingBottom: 32,
  },
  progressRow: {
    flexDirection: 'row',
    gap: 6,
    marginBottom: 12,
  },
  progressBar: {
    flex: 1,
    height: 4,
    borderRadius: 2,
    backgroundColor: theme.colors.blueSoftAlt,
  },
  progressActive: {
    backgroundColor: theme.colors.sun,
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 16,
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
  storyCard: {
    height: 380,
    borderRadius: 24,
    backgroundColor: theme.colors.blueSoftPale,
    overflow: 'hidden',
  },
  storyImage: {
    flex: 1,
    backgroundColor: '#cdd6fb',
  },
  bannerCard: {
    position: 'absolute',
    left: 18,
    right: 18,
    bottom: 20,
    padding: 16,
    borderRadius: 18,
    backgroundColor: theme.colors.white,
  },
  bannerEyebrow: {
    fontSize: 10,
    fontWeight: '700',
    color: theme.colors.rose,
  },
  bannerTitle: {
    marginTop: 6,
    fontSize: 16,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  bannerBody: {
    marginTop: 6,
    fontSize: 12,
    color: theme.colors.mutedDark,
  },
  bannerButton: {
    marginTop: 12,
    backgroundColor: theme.colors.sun,
    borderRadius: 16,
    paddingHorizontal: 14,
    paddingVertical: 8,
    alignSelf: 'flex-start',
  },
  bannerButtonText: {
    fontSize: 12,
    fontWeight: '700',
    color: theme.colors.white,
  },
  primaryButton: {
    marginTop: 20,
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
});

