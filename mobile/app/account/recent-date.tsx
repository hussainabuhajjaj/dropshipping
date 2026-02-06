import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
import { SafeAreaView } from 'react-native-safe-area-context';
const dates = ['Today', 'Yesterday', 'Last 7 days', 'Last 30 days'];

export default function RecentlyViewedDateScreen() {
  return (
    <SafeAreaView style={styles.container}>
      <ScrollView style={styles.scroll} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.headerRow}>
          <Pressable style={styles.iconButton} onPress={() => router.back()}>
            <Feather name="arrow-left" size={16} color={theme.colors.inkDark} />
          </Pressable>
          <Text style={styles.title}>Recently viewed</Text>
          <View style={styles.spacer} />
        </View>

        <Text style={styles.subtitle}>Choose a date range</Text>
        <View style={styles.list}>
          {dates.map((date) => (
            <Pressable
              key={date}
              style={styles.dateRow}
              onPress={() => router.push('/account/recent-date-chosen')}
            >
              <Text style={styles.dateText}>{date}</Text>
              <Feather name="chevron-right" size={16} color={theme.colors.inkDark} />
            </Pressable>
          ))}
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
    paddingTop: 10,
    paddingBottom: 24,
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 14,
  },
  title: {
    fontSize: 20,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  iconButton: {
    width: 32,
    height: 32,
    borderRadius: 16,
    backgroundColor: theme.colors.gray100,
    alignItems: 'center',
    justifyContent: 'center',
  },
  spacer: {
    width: 32,
    height: 32,
  },
  subtitle: {
    fontSize: 14,
    color: theme.colors.inkDark,
  },
  list: {
    marginTop: 16,
    gap: 12,
  },
  dateRow: {
    paddingVertical: 14,
    paddingHorizontal: 16,
    borderRadius: 16,
    backgroundColor: theme.colors.sand,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  dateText: {
    fontSize: 14,
    color: theme.colors.inkDark,
    fontWeight: '600',
  },
});
