import { Feather } from '@expo/vector-icons';
import { router, type Href } from 'expo-router';
import { Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
import { SafeAreaView } from 'react-native-safe-area-context';

const channels: Array<{
  id: string;
  title: string;
  subtitle: string;
  icon: string;
  href: Href;
}> = [
  { id: 'chat', title: 'Live chat', subtitle: 'Fast responses in-app.', icon: 'message-circle', href: '/chat' },
  { id: 'whatsapp', title: 'WhatsApp', subtitle: '+225 01 23 45 67', icon: 'phone', href: '/contact' },
  { id: 'email', title: 'Email', subtitle: 'support@dispatch.store', icon: 'mail', href: '/contact' },
  { id: 'track', title: 'Track order', subtitle: 'Get delivery updates', icon: 'truck', href: '/orders/track' },
];

export default function SupportScreen() {
  return (
    <SafeAreaView style={styles.container}>
      <ScrollView style={styles.scroll} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.headerRow}>
          <Pressable style={styles.iconButton} onPress={() => router.back()}>
            <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
          </Pressable>
          <Text style={styles.title}>Support</Text>
          <Pressable style={styles.iconButton} onPress={() => router.push('/(tabs)/home')}>
            <Feather name="x" size={16} color={theme.colors.inkDark} />
          </Pressable>
        </View>
        <Text style={styles.subtitle}>We are here to help with every order.</Text>

        <View style={styles.list}>
          {channels.map((channel) => (
            <Pressable
              key={channel.id}
              style={styles.card}
              onPress={() => router.push(channel.href)}
            >
              <View style={styles.iconWrap}>
                <Feather name={channel.icon as any} size={16} color={theme.colors.inkDark} />
              </View>
              <View style={styles.copy}>
                <Text style={styles.cardTitle}>{channel.title}</Text>
                <Text style={styles.cardBody}>{channel.subtitle}</Text>
              </View>
              <Feather name="chevron-right" size={16} color="#a1a4ad" />
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
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: theme.colors.white,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: theme.colors.sand,
    padding: 14,
    gap: 12,
  },
  iconWrap: {
    width: 36,
    height: 36,
    borderRadius: 14,
    backgroundColor: theme.colors.blueSoft,
    alignItems: 'center',
    justifyContent: 'center',
  },
  copy: {
    flex: 1,
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
});
