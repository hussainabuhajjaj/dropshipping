import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
export default function StoryProductTwoScreen() {
  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
      <View style={styles.progressRow}>
        {[0, 1, 2, 3].map((index) => (
          <View key={index} style={[styles.progressBar, styles.progressActive]} />
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
          <Text style={styles.productLabel}>Complete the look</Text>
          <View style={styles.productRow}>
            <View style={styles.productImage} />
            <View style={styles.productCopy}>
              <Text style={styles.productTitle}>Wide leg denim</Text>
              <Text style={styles.productPrice}>$29.00</Text>
            </View>
          </View>
          <Pressable style={styles.productButton} onPress={() => router.push('/products/sale')}>
            <Text style={styles.productButtonText}>Shop set</Text>
          </Pressable>
        </View>
      </View>

      <Pressable style={styles.primaryButton} onPress={() => router.push('/(tabs)/home')}>
        <Text style={styles.primaryText}>Finish</Text>
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
    backgroundColor: theme.colors.sun,
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
    left: 18,
    right: 18,
    bottom: 18,
    padding: 16,
    borderRadius: 18,
    backgroundColor: theme.colors.white,
  },
  productLabel: {
    fontSize: 12,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  productRow: {
    marginTop: 10,
    flexDirection: 'row',
    gap: 12,
    alignItems: 'center',
  },
  productImage: {
    width: 58,
    height: 58,
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
    marginTop: 12,
    backgroundColor: theme.colors.sun,
    borderRadius: 16,
    paddingHorizontal: 14,
    paddingVertical: 8,
    alignSelf: 'flex-start',
  },
  productButtonText: {
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

