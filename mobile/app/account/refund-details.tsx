import { Feather } from '@expo/vector-icons';
import { useLocalSearchParams, router } from 'expo-router';
import { Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
import { SafeAreaView } from 'react-native-safe-area-context';

export default function RefundDetailsScreen() {
  const { id } = useLocalSearchParams();

  // Placeholder: In real app, fetch refund details by id
  return (
    <SafeAreaView style={styles.container}>
      <ScrollView style={styles.scroll} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.headerRow}>
          <Pressable style={styles.iconButton} onPress={() => router.back()}>
            <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
          </Pressable>
          <Text style={styles.title}>Refund Details</Text>
          <View style={styles.iconButton} />
        </View>
        <Text style={styles.subtitle}>Refund ID: {id}</Text>
        <View style={styles.card}>
          <Text style={styles.cardTitle}>Order #...</Text>
          <Text style={styles.cardBody}>Amount: $...</Text>
          <Text style={styles.cardStatus}>Status: ...</Text>
          <Text style={styles.cardBody}>Reason: ...</Text>
          <Text style={styles.cardBody}>Requested: ...</Text>
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
  card: {
    backgroundColor: theme.colors.white,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: theme.colors.sand,
    padding: 14,
    marginTop: 16,
  },
  cardTitle: {
    fontSize: 14,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  cardBody: {
    fontSize: 12,
    color: theme.colors.mutedDark,
    marginTop: 4,
  },
  cardStatus: {
    fontSize: 12,
    color: theme.colors.sun,
    marginTop: 6,
    fontWeight: '600',
  },
});
