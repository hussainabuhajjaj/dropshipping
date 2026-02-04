import { Feather } from '@expo/vector-icons';
import { useLocalSearchParams, router } from 'expo-router';
import { Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
const copy: Record<string, string> = {
  terms: 'By using the app you agree to our terms of service, acceptable use policies, and privacy practices. Full policy text will be provided by the storefront before launch.',
  privacy: 'We respect your data and only collect what is needed to fulfill your orders. A full privacy policy will be provided by the storefront before launch.',
  shipping: 'Shipping timelines are shown at checkout with customs estimates.',
  refund: 'Refunds are reviewed within 48 hours after a request is submitted.',
};

export default function LegalScreen() {
  const params = useLocalSearchParams();
  const slug = typeof params.slug === 'string' ? params.slug : 'legal';
  const title = slug.replace('-', ' ');
  const body = copy[slug] ?? 'Legal documents from the storefront will appear here.';

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
      <View style={styles.headerRow}>
        <Pressable style={styles.iconButton} onPress={() => router.back()}>
          <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
        </Pressable>
        <Text style={styles.title}>{title}</Text>
        <Pressable style={styles.iconButton} onPress={() => router.push('/(tabs)/home')}>
          <Feather name="x" size={16} color={theme.colors.inkDark} />
        </Pressable>
      </View>
      <Text style={styles.body}>{body}</Text>
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
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 12,
  },
  iconButton: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: theme.colors.sand,
    alignItems: 'center',
    justifyContent: 'center',
  },
  title: {
    fontSize: 18,
    fontWeight: '700',
    color: theme.colors.inkDark,
    textTransform: 'capitalize',
  },
  body: {
    fontSize: 13,
    color: theme.colors.mutedDark,
    marginTop: 10,
    lineHeight: 20,
  },
});
