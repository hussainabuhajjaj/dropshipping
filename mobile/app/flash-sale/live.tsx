import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { Pressable, ScrollView, StyleSheet, Text, TextInput, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
import { KeyboardAvoidingView, Platform } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
const messages = [
  { id: 'm1', text: 'Love the new arrivals!', user: 'Lina' },
  { id: 'm2', text: 'Can you show the size guide?', user: 'Amira' },
  { id: 'm3', text: 'That jacket is fire.', user: 'Jae' },
];

export default function FlashSaleLiveScreen() {
  const insets = useSafeAreaInsets();
  return (
    <KeyboardAvoidingView
      style={styles.container}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      keyboardVerticalOffset={Platform.OS === 'ios' ? theme.moderateScale(20) : 0}
    >
      <ScrollView
        style={styles.scroll}
        contentContainerStyle={[
          styles.content,
          {
            paddingTop: theme.moderateScale(12) + insets.top,
            paddingBottom: theme.moderateScale(120) + insets.bottom,
          },
        ]}
        showsVerticalScrollIndicator={false}
        keyboardShouldPersistTaps="handled"
        keyboardDismissMode="interactive"
        automaticallyAdjustKeyboardInsets
      >
        <View style={styles.headerRow}>
          <Pressable style={styles.iconButton} onPress={() => router.back()}>
            <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
          </Pressable>
          <View style={styles.livePill}>
            <Text style={styles.livePillText}>LIVE</Text>
          </View>
          <Pressable style={styles.iconButton} onPress={() => router.push('/(tabs)/home')}>
            <Feather name="x" size={16} color={theme.colors.inkDark} />
          </Pressable>
        </View>

        <View style={styles.videoCard}>
          <View style={styles.viewerBadge}>
            <Feather name="eye" size={12} color={theme.colors.inkDark} />
            <Text style={styles.viewerText}>4.2k</Text>
          </View>
          <View style={styles.videoOverlay}>
            <Text style={styles.videoTitle}>New drop with Mina</Text>
            <Text style={styles.videoSubtitle}>Styling live now</Text>
          </View>
        </View>

        <View style={styles.hostRow}>
          <View style={styles.hostAvatar} />
          <View>
            <Text style={styles.hostName}>Mina James</Text>
            <Text style={styles.hostRole}>Stylist</Text>
          </View>
          <Pressable style={styles.followButton}>
            <Text style={styles.followText}>Follow</Text>
          </Pressable>
        </View>

        <View style={styles.chatCard}>
          <Text style={styles.chatTitle}>Live chat</Text>
          {messages.map((item) => (
            <View key={item.id} style={styles.chatRow}>
              <Text style={styles.chatUser}>{item.user}</Text>
              <Text style={styles.chatText}>{item.text}</Text>
            </View>
          ))}
        </View>
      </ScrollView>

      <View style={[styles.bottomCard, { bottom: theme.moderateScale(62) + insets.bottom }]}>
        <View style={styles.productThumb} />
        <View style={styles.productCopy}>
          <Text style={styles.productTitle}>Cropped leather jacket</Text>
          <Text style={styles.productPrice}>$42.00</Text>
        </View>
        <Pressable style={styles.buyButton} onPress={() => router.push('/products/sale')}>
          <Text style={styles.buyText}>Buy</Text>
        </Pressable>
      </View>

      <View style={[styles.inputRow, { paddingBottom: theme.moderateScale(12) + insets.bottom }]}>
        <TextInput style={styles.input} placeholder="Say something" placeholderTextColor="#b6b6b6" />
        <Pressable style={styles.sendButton}>
          <Feather name="send" size={14} color={theme.colors.inkDark} />
        </Pressable>
      </View>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#0b0b0b',
  },
  scroll: {
    flex: 1,
  },
  content: {
    paddingHorizontal: 16,
    paddingTop: 12,
    paddingBottom: 120,
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 16,
  },
  iconButton: {
    width: 34,
    height: 34,
    borderRadius: 17,
    backgroundColor: 'rgba(255,255,255,0.2)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  livePill: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 12,
    backgroundColor: theme.colors.rose,
  },
  livePillText: {
    fontSize: 12,
    fontWeight: '700',
    color: theme.colors.white,
  },
  videoCard: {
    height: 300,
    borderRadius: 24,
    backgroundColor: '#1c1c1c',
    overflow: 'hidden',
    justifyContent: 'space-between',
  },
  viewerBadge: {
    margin: 16,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    alignSelf: 'flex-start',
    backgroundColor: 'rgba(0,0,0,0.6)',
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 12,
  },
  viewerText: {
    fontSize: 12,
    color: theme.colors.white,
  },
  videoOverlay: {
    padding: 18,
    backgroundColor: 'rgba(0,0,0,0.35)',
  },
  videoTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: theme.colors.white,
  },
  videoSubtitle: {
    marginTop: 6,
    fontSize: 12,
    color: '#e1e1e1',
  },
  hostRow: {
    marginTop: 18,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  hostAvatar: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: '#323232',
  },
  hostName: {
    fontSize: 14,
    fontWeight: '700',
    color: theme.colors.white,
  },
  hostRole: {
    fontSize: 12,
    color: '#cfcfcf',
  },
  followButton: {
    marginLeft: 'auto',
    backgroundColor: theme.colors.white,
    borderRadius: 16,
    paddingHorizontal: 14,
    paddingVertical: 6,
  },
  followText: {
    fontSize: 12,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  chatCard: {
    marginTop: 18,
    padding: 14,
    borderRadius: 18,
    backgroundColor: '#1d1d1d',
  },
  chatTitle: {
    fontSize: 13,
    fontWeight: '700',
    color: theme.colors.white,
    marginBottom: 8,
  },
  chatRow: {
    flexDirection: 'row',
    gap: 8,
    marginBottom: 6,
  },
  chatUser: {
    fontSize: 12,
    fontWeight: '700',
    color: '#ff9db3',
  },
  chatText: {
    flex: 1,
    fontSize: 12,
    color: theme.colors.sand,
  },
  bottomCard: {
    position: 'absolute',
    left: 16,
    right: 16,
    bottom: 62,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    padding: 12,
    borderRadius: 18,
    backgroundColor: theme.colors.white,
  },
  productThumb: {
    width: 50,
    height: 50,
    borderRadius: 12,
    backgroundColor: '#e7e7e7',
  },
  productCopy: {
    flex: 1,
  },
  productTitle: {
    fontSize: 12,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  productPrice: {
    marginTop: 4,
    fontSize: 12,
    color: theme.colors.mutedDark,
  },
  buyButton: {
    backgroundColor: theme.colors.sun,
    borderRadius: 16,
    paddingHorizontal: 14,
    paddingVertical: 8,
  },
  buyText: {
    fontSize: 12,
    fontWeight: '700',
    color: theme.colors.white,
  },
  inputRow: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingHorizontal: 16,
    paddingVertical: 12,
    backgroundColor: '#0b0b0b',
    borderTopWidth: 1,
    borderTopColor: '#1b1b1b',
  },
  input: {
    flex: 1,
    backgroundColor: '#1c1c1c',
    borderRadius: 20,
    paddingHorizontal: 14,
    paddingVertical: 10,
    fontSize: 12,
    color: theme.colors.white,
  },
  sendButton: {
    width: 34,
    height: 34,
    borderRadius: 17,
    backgroundColor: theme.colors.sun,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
