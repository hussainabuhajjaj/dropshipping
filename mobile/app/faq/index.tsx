import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
const faqs = [
  {
    id: 'faq-1',
    question: 'When will my order arrive?',
    answer: 'Delivery usually takes 7-18 business days depending on customs and carrier updates.',
  },
  {
    id: 'faq-2',
    question: 'How do I track my package?',
    answer: 'Use the tracking screen or check your order page for real-time events.',
  },
  {
    id: 'faq-3',
    question: 'Can I cancel my order?',
    answer: 'Orders can be canceled before dispatch. Contact support quickly to help.',
  },
  {
    id: 'faq-4',
    question: 'Are duties included?',
    answer: 'We show expected duties before checkout so there are no surprises.',
  },
];

export default function FAQScreen() {
  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
      <View style={styles.headerRow}>
        <Pressable style={styles.iconButton} onPress={() => router.back()}>
          <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
        </Pressable>
        <Text style={styles.title}>FAQ</Text>
        <Pressable style={styles.iconButton} onPress={() => router.push('/(tabs)/home')}>
          <Feather name="x" size={16} color={theme.colors.inkDark} />
        </Pressable>
      </View>
      <Text style={styles.subtitle}>Quick answers to common questions.</Text>

      <View style={styles.list}>
        {faqs.map((item) => (
          <View key={item.id} style={styles.card}>
            <Text style={styles.question}>{item.question}</Text>
            <Text style={styles.answer}>{item.answer}</Text>
          </View>
        ))}
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
    fontSize: 20,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  subtitle: {
    fontSize: 13,
    color: theme.colors.mutedDark,
    marginBottom: 18,
  },
  list: {
    gap: 12,
  },
  card: {
    backgroundColor: theme.colors.white,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: theme.colors.sand,
    padding: 14,
  },
  question: {
    fontSize: 13,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  answer: {
    fontSize: 12,
    color: theme.colors.mutedDark,
    marginTop: 6,
  },
});

