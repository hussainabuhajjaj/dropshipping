import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { Pressable, ScrollView, StyleSheet, Text, TextInput, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
export default function ProfileToReceiveScreen() {
  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
      <View style={styles.headerRow}>
        <Pressable style={styles.iconButton} onPress={() => router.back()}>
          <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
        </Pressable>
        <Text style={styles.title}>Delivery details</Text>
        <Pressable style={styles.iconButton} onPress={() => router.push('/(tabs)/home')}>
          <Feather name="x" size={16} color={theme.colors.inkDark} />
        </Pressable>
      </View>

      <Text style={styles.subtitle}>Confirm how we should contact you for delivery updates.</Text>

      <View style={styles.form}>
        <TextInput style={styles.input} placeholder="Full name" placeholderTextColor="#b6b6b6" />
        <TextInput style={styles.input} placeholder="Phone number" placeholderTextColor="#b6b6b6" />
        <TextInput style={styles.input} placeholder="Address" placeholderTextColor="#b6b6b6" />
      </View>

      <Pressable style={styles.primaryButton} onPress={() => router.replace('/orders/to-receive')}>
        <Text style={styles.primaryText}>Save</Text>
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
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 12,
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
  subtitle: {
    fontSize: 13,
    color: theme.colors.mutedDark,
  },
  form: {
    marginTop: 16,
    gap: 12,
  },
  input: {
    height: 50,
    borderRadius: 18,
    backgroundColor: theme.colors.sand,
    paddingHorizontal: 16,
    fontSize: 14,
    color: theme.colors.inkDark,
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
    color: theme.colors.gray200,
    fontWeight: '700',
  },
});

