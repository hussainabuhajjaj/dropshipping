import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { Pressable, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
export default function ImageRecognizingScreen() {
  return (
    <View style={styles.container}>
      <View style={styles.headerRow}>
        <Pressable style={styles.iconButton} onPress={() => router.back()}>
          <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
        </Pressable>
        <Text style={styles.title}>Recognizing</Text>
        <Pressable style={styles.iconButton} onPress={() => router.push('/(tabs)/home')}>
          <Feather name="x" size={16} color={theme.colors.inkDark} />
        </Pressable>
      </View>

      <View style={styles.previewCard}>
        <View style={styles.previewImage} />
        <View style={styles.scanLine} />
      </View>

      <Text style={styles.statusTitle}>We are scanning your image</Text>
      <Text style={styles.statusBody}>Hang tight while we match similar products.</Text>

      <Pressable style={styles.primaryButton} onPress={() => router.push('/image-search/recognized')}>
        <Text style={styles.primaryText}>View results</Text>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.white,
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
  previewCard: {
    height: 320,
    borderRadius: 24,
    backgroundColor: theme.colors.blueSoftPale,
    overflow: 'hidden',
    justifyContent: 'center',
  },
  previewImage: {
    flex: 1,
    backgroundColor: theme.colors.blueSoftMuted,
  },
  scanLine: {
    position: 'absolute',
    left: 24,
    right: 24,
    height: 2,
    backgroundColor: theme.colors.sun,
  },
  statusTitle: {
    marginTop: 24,
    fontSize: 16,
    fontWeight: '700',
    color: theme.colors.inkDark,
    textAlign: 'center',
  },
  statusBody: {
    marginTop: 8,
    fontSize: 12,
    color: theme.colors.mutedDark,
    textAlign: 'center',
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
});

