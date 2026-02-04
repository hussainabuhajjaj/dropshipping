import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, TextInput, View } from '@/src/utils/responsiveStyleSheet';
import { RefreshControl } from 'react-native';
import { theme } from '@/src/theme';
import { fetchWallet } from '@/src/api/wallet';
import type { GiftCard, Wallet } from '@/src/types/rewards';
import { useToast } from '@/src/overlays/ToastProvider';
import { usePullToRefresh } from '@/src/hooks/usePullToRefresh';

export default function WalletScreen() {
  const [redeemed, setRedeemed] = useState(false);
  const { show } = useToast();
  const [wallet, setWallet] = useState<Wallet | null>(null);
  const [loading, setLoading] = useState(true);
  const requestId = useRef(0);

  const loadWallet = useCallback(async () => {
    const id = ++requestId.current;
    setLoading(true);
    try {
      const data = await fetchWallet();
      if (id !== requestId.current) return;
      setWallet(data);
    } catch (err: any) {
      if (id !== requestId.current) return;
      show({ type: 'error', message: err?.message ?? 'Unable to load wallet.' });
      setWallet(null);
    } finally {
      if (id === requestId.current) setLoading(false);
    }
  }, [show]);

  useEffect(() => {
    loadWallet();
    return () => {
      requestId.current += 1;
    };
  }, [loadWallet]);

  const { refreshing, onRefresh } = usePullToRefresh(loadWallet);

  return (
    <ScrollView
      style={styles.container}
      contentContainerStyle={styles.content}
      showsVerticalScrollIndicator={false}
      refreshControl={
        <RefreshControl
          refreshing={refreshing}
          onRefresh={onRefresh}
          tintColor={theme.colors.primary}
          colors={[theme.colors.primary]}
        />
      }
    >
      <View style={styles.headerRow}>
        <Pressable style={styles.iconButton} onPress={() => router.back()}>
          <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
        </Pressable>
        <Text style={styles.title}>Wallet</Text>
        <Pressable style={styles.iconButton} onPress={() => router.push('/(tabs)/home')}>
          <Feather name="x" size={16} color={theme.colors.inkDark} />
        </Pressable>
      </View>
      <Text style={styles.subtitle}>Gift cards and saved promos.</Text>

      <View style={styles.redeemCard}>
        <Text style={styles.sectionTitle}>Redeem gift card</Text>
        <TextInput style={styles.input} placeholder="Gift card code" placeholderTextColor="#b6b6b6" />
        <Pressable style={styles.primaryButton} onPress={() => setRedeemed(true)}>
          <Text style={styles.primaryText}>Redeem</Text>
        </Pressable>
        {redeemed ? <Text style={styles.redeemNote}>Added to your wallet balance.</Text> : null}
      </View>

      <View style={styles.list}>
        {(wallet?.giftCards ?? []).map((card: GiftCard) => (
          <Pressable
            key={card.id}
            style={styles.card}
            onPress={() => router.push({ pathname: '/account/wallet-card-details', params: { id: card.id } })}
            android_ripple={{ color: theme.colors.sand }}
          >
            <Text style={styles.cardTitle}>{card.code}</Text>
            <Text style={styles.cardBody}>
              Balance: ${typeof card.balance === 'number' ? card.balance.toFixed(2) : '0.00'}
            </Text>
          </Pressable>
        ))}
        {!loading && (!wallet || (wallet.giftCards ?? []).length === 0) ? (
          <View style={styles.emptyCard}>
            <Text style={styles.emptyTitle}>No gift cards yet</Text>
            <Text style={styles.emptyBody}>Add a gift card to use it at checkout.</Text>
          </View>
        ) : null}
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
  redeemCard: {
    backgroundColor: theme.colors.sand,
    borderRadius: 20,
    padding: 18,
    marginBottom: 18,
  },
  sectionTitle: {
    fontSize: 14,
    fontWeight: '700',
    color: theme.colors.inkDark,
    marginBottom: 12,
  },
  input: {
    borderWidth: 1,
    borderColor: '#e6e8ef',
    borderRadius: 18,
    paddingHorizontal: 14,
    paddingVertical: 12,
    fontSize: 13,
    color: theme.colors.inkDark,
    backgroundColor: theme.colors.white,
    marginBottom: 12,
  },
  primaryButton: {
    backgroundColor: theme.colors.sun,
    paddingVertical: 12,
    borderRadius: 20,
    alignItems: 'center',
  },
  redeemNote: {
    marginTop: 8,
    fontSize: 12,
    color: theme.colors.inkDark,
  },
  primaryText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '700',
  },
  list: {
    gap: 12,
  },
  emptyCard: {
    padding: 16,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: theme.colors.sand,
    backgroundColor: theme.colors.white,
  },
  emptyTitle: {
    fontSize: 13,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  emptyBody: {
    marginTop: 6,
    fontSize: 12,
    color: theme.colors.mutedDark,
  },
  card: {
    backgroundColor: theme.colors.white,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: theme.colors.sand,
    padding: 14,
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
