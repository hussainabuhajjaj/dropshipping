import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Pressable, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
export default function ToReceiveFailedScreen() {
  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.card}>
        <View style={styles.iconWrap}>
          <Feather name="alert-circle" size={28} color={theme.colors.inkDark} />
        </View>
        <Text style={styles.title}>Delivery attempt was not successful</Text>
        <Text style={styles.body}>We will try again or contact you for a new schedule.</Text>
        <Pressable style={styles.primaryButton} onPress={() => router.push('/orders/delivery-attempt')}>
          <Text style={styles.primaryText}>View details</Text>
        </Pressable>
        <Pressable onPress={() => router.back()}>
          <Text style={styles.linkText}>Back to tracking</Text>
        </Pressable>
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.white,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 24,
  },
  card: {
    width: '100%',
    borderRadius: 24,
    backgroundColor: theme.colors.sand,
    paddingVertical: 32,
    paddingHorizontal: 24,
    alignItems: 'center',
  },
  iconWrap: {
    width: 54,
    height: 54,
    borderRadius: 27,
    backgroundColor: theme.colors.dangerSoft,
    alignItems: 'center',
    justifyContent: 'center',
  },
  title: {
    marginTop: 16,
    fontSize: 16,
    fontWeight: '700',
    color: theme.colors.inkDark,
    textAlign: 'center',
  },
  body: {
    marginTop: 8,
    fontSize: 13,
    color: theme.colors.inkDark,
    textAlign: 'center',
  },
  primaryButton: {
    marginTop: 18,
    backgroundColor: theme.colors.sun,
    paddingVertical: 12,
    paddingHorizontal: 24,
    borderRadius: 24,
  },
  primaryText: {
    fontSize: 14,
    color: theme.colors.gray200,
    fontWeight: '700',
  },
  linkText: {
    marginTop: 12,
    fontSize: 13,
    color: theme.colors.inkDark,
  },
});
