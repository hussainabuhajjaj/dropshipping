import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
import { SafeAreaView } from 'react-native-safe-area-context';
export default function StoryProductOneScreen() {
  return (
    <SafeAreaView style={styles.container}>
      <ScrollView style={styles.scroll} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
      <View style={styles.progressRow}>
        {[0, 1, 2, 3].map((index) => (
          <View key={index} style={[styles.progressBar, index < 3 ? styles.progressActive : null]} />
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
        <View style={styles.productCard}>
          <View style={styles.productImage} />
          <View style={styles.productCopy}>
            <Text style={styles.productTitle}>Ribbed mini dress</Text>
            <Text style={styles.productPrice}>$22.00</Text>
            <Pressable style={styles.productButton} onPress={() => router.push('/products/sale')}>
              <Text style={styles.productButtonText}>Shop now</Text>
            </Pressable>
          </View>
        </View>
      </View>

      <Pressable style={styles.primaryButton} onPress={() => router.push('/stories/banner')}>
        <Text style={styles.primaryText}>Next</Text>
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
    height: 380,
    borderRadius: 24,
    backgroundColor: theme.colors.blueSoftPale,
    overflow: 'hidden',
  },
  storyImage: {
    flex: 1,
    backgroundColor: '#cdd6fb',
  },
  productCard: {
    position: 'absolute',
    left: 16,
    right: 16,
    bottom: 18,
    flexDirection: 'row',
    gap: 12,
    padding: 12,
    borderRadius: 18,
    backgroundColor: theme.colors.white,
    alignItems: 'center',
  },
  productImage: {
    width: 64,
    height: 64,
    borderRadius: 14,
    backgroundColor: theme.colors.sand,
  },
  productCopy: {
    flex: 1,
  },
  productTitle: {
    fontSize: 13,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  productPrice: {
    marginTop: 4,
    fontSize: 12,
    color: theme.colors.mutedDark,
  },
  productButton: {
    marginTop: 8,
    backgroundColor: theme.colors.sun,
    borderRadius: 14,
    paddingHorizontal: 10,
    paddingVertical: 6,
    alignSelf: 'flex-start',
  },
  productButtonText: {
    fontSize: 11,
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
