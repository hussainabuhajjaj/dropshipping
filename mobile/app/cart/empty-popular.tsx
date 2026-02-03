import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Pressable, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
export default function CartEmptyPopularScreen() {
  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.card}>
        <View style={styles.iconWrap}>
          <Feather name="shopping-bag" size={26} color={theme.colors.inkDark} />
        </View>
        <Text style={styles.title}>Your cart is empty</Text>
        <Text style={styles.body}>Check out popular items and start saving today.</Text>
        <Pressable style={styles.primaryButton} onPress={() => router.push('/(tabs)/home')}>
          <Text style={styles.primaryText}>See popular picks</Text>
        </Pressable>
        <Pressable onPress={() => router.push('/(tabs)/search')}>
          <Text style={styles.linkText}>Search products</Text>
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
    backgroundColor: theme.colors.sand,
    alignItems: 'center',
    justifyContent: 'center',
  },
  title: {
    marginTop: 16,
    fontSize: 16,
    fontWeight: '700',
    color: theme.colors.inkDark,
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
