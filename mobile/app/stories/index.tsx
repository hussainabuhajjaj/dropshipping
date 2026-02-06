import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
import { SafeAreaView } from 'react-native-safe-area-context';
const avatars = ['A', 'B', 'C', 'D', 'E'];

export default function StoryDotsScreen() {
  return (
    <SafeAreaView style={styles.container}>
      <ScrollView style={styles.scroll} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.progressRow}>
          {[0, 1, 2, 3].map((index) => (
            <View key={index} style={[styles.progressBar, index === 0 ? styles.progressActive : null]} />
          ))}
        </View>

        <View style={styles.headerRow}>
          <Pressable style={styles.iconButton} onPress={() => router.back()}>
            <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
          </Pressable>
          <Text style={styles.title}>Stories</Text>
          <Pressable style={styles.iconButton} onPress={() => router.push('/(tabs)/home')}>
            <Feather name="x" size={16} color={theme.colors.inkDark} />
          </Pressable>
        </View>

        <View style={styles.storyCard}>
          <View style={styles.storyImage} />
          <View style={styles.storyOverlay}>
            <Text style={styles.storyTitle}>Weekend essentials</Text>
            <Text style={styles.storySubtitle}>Tap to explore</Text>
          </View>
        </View>

        <View style={styles.avatarRow}>
          {avatars.map((avatar) => (
            <View key={avatar} style={styles.avatar}>
              <Text style={styles.avatarText}>{avatar}</Text>
            </View>
          ))}
        </View>

        <View style={styles.dotRow}>
          <View style={[styles.dot, styles.dotActive]} />
          <View style={styles.dot} />
          <View style={styles.dot} />
        </View>

        <Pressable style={styles.primaryButton} onPress={() => router.push('/stories/dots-tap')}>
          <Text style={styles.primaryText}>Next story</Text>
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
    height: 360,
    borderRadius: 24,
    backgroundColor: theme.colors.blueSoftPale,
    overflow: 'hidden',
  },
  storyImage: {
    flex: 1,
    backgroundColor: '#d5dbf4',
  },
  storyOverlay: {
    position: 'absolute',
    left: 20,
    bottom: 20,
  },
  storyTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  storySubtitle: {
    marginTop: 6,
    fontSize: 12,
    color: theme.colors.mutedDark,
  },
  avatarRow: {
    marginTop: 18,
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  avatar: {
    width: 46,
    height: 46,
    borderRadius: 23,
    backgroundColor: theme.colors.sand,
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarText: {
    fontSize: 14,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  dotRow: {
    marginTop: 16,
    flexDirection: 'row',
    justifyContent: 'center',
    gap: 8,
  },
  dot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: theme.colors.blueSoftAlt,
  },
  dotActive: {
    backgroundColor: theme.colors.sun,
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
