import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
import { SafeAreaView } from 'react-native-safe-area-context';
export default function AboutScreen() {
  return (
    <SafeAreaView style={styles.container}>
      <ScrollView style={styles.scroll} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.headerRow}>
          <Pressable style={styles.iconButton} onPress={() => router.back()}>
            <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
          </Pressable>
          <Text style={styles.title}>About</Text>
          <Pressable style={styles.iconButton} onPress={() => router.push('/(tabs)/home')}>
            <Feather name="x" size={16} color={theme.colors.inkDark} />
          </Pressable>
        </View>

        <View style={styles.heroCard}>
          <Text style={styles.heroEyebrow}>Simbazu</Text>
          <Text style={styles.heroTitle}>Design-forward fashion for daily life.</Text>
          <Text style={styles.heroBody}>
            We partner with vetted suppliers to deliver trending pieces with clear shipping timelines and
            reliable support.
          </Text>
        </View>

        <View style={styles.sectionCard}>
          <Text style={styles.sectionTitle}>Our promise</Text>
          <Text style={styles.body}>
            Transparent updates, smart pricing, and a catalog that changes with your style. We focus on
            the pieces you will actually wear.
          </Text>
        </View>

        <View style={styles.sectionCard}>
          <Text style={styles.sectionTitle}>Support</Text>
          <Text style={styles.body}>Need help? Our team is available 24/7 in chat and email.</Text>
        </View>
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
  heroCard: {
    borderRadius: 22,
    backgroundColor: theme.colors.blueSoft,
    padding: 20,
  },
  heroEyebrow: {
    fontSize: 10,
    fontWeight: '700',
    color: theme.colors.sun,
    letterSpacing: 1,
  },
  heroTitle: {
    marginTop: 8,
    fontSize: 18,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  heroBody: {
    marginTop: 10,
    fontSize: 12,
    color: theme.colors.mutedDark,
    lineHeight: 18,
  },
  sectionCard: {
    marginTop: 16,
    backgroundColor: theme.colors.sand,
    borderRadius: 20,
    padding: 18,
    gap: 8,
  },
  sectionTitle: {
    fontSize: 14,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  body: {
    fontSize: 12,
    color: theme.colors.mutedDark,
    lineHeight: 18,
  },
});
