import { Feather } from '@expo/vector-icons';
import { router, useLocalSearchParams } from 'expo-router';
import { useEffect, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
import { fetchVouchers } from '@/src/api/rewards';
import type { Voucher } from '@/src/types/rewards';
import { useToast } from '@/src/overlays/ToastProvider';
export default function VoucherExpireScreen() {
  const params = useLocalSearchParams();
  const id = typeof params.id === 'string' ? params.id : '';
  const { show } = useToast();
  const [voucher, setVoucher] = useState<Voucher | null>(null);

  useEffect(() => {
    let active = true;
    fetchVouchers()
      .then((items) => {
        if (!active) return;
        const found = items.find((item) => item.id === id) ?? items[0] ?? null;
        setVoucher(found);
      })
      .catch((err: any) => {
        if (!active) return;
        show({ type: 'error', message: err?.message ?? 'Unable to load voucher.' });
        setVoucher(null);
      });

    return () => {
      active = false;
    };
  }, [id, show]);

  return (
    <View style={styles.container}>
      <View style={styles.headerRow}>
        <Pressable style={styles.iconButton} onPress={() => router.back()}>
          <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
        </Pressable>
        <Text style={styles.title}>Voucher</Text>
        <Pressable style={styles.iconButton} onPress={() => router.push('/(tabs)/home')}>
          <Feather name="x" size={16} color={theme.colors.inkDark} />
        </Pressable>
      </View>

      <View style={styles.card}>
        <View style={styles.badge}>
          <Text style={styles.badgeText}>{voucher?.value ?? 'Reward'}</Text>
        </View>
        <Text style={styles.cardTitle}>{voucher?.title ?? 'Voucher'}</Text>
        <Text style={styles.cardBody}>{voucher?.description ?? 'Use it before it expires.'}</Text>
        <View style={styles.codeRow}>
          <Text style={styles.codeText}>{voucher?.code ?? 'REWARD'}</Text>
          <Pressable style={styles.copyButton}>
            <Feather name="copy" size={14} color={theme.colors.inkDark} />
            <Text style={styles.copyText}>Copy</Text>
          </Pressable>
        </View>
      </View>

      <Pressable style={styles.primaryButton} onPress={() => router.push('/checkout')}>
        <Text style={styles.primaryText}>Use voucher</Text>
      </Pressable>
      <Pressable style={styles.secondaryButton} onPress={() => router.push('/rewards/voucher-reminder')}>
        <Text style={styles.secondaryText}>Set reminder</Text>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.white,
    paddingHorizontal: 20,
    paddingTop: 12,
    paddingBottom: 32,
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 20,
  },
  title: {
    fontSize: 18,
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
  card: {
    borderRadius: 22,
    backgroundColor: '#f4f6ff',
    padding: 20,
  },
  badge: {
    alignSelf: 'flex-start',
    backgroundColor: theme.colors.sun,
    borderRadius: 14,
    paddingHorizontal: 12,
    paddingVertical: 6,
  },
  badgeText: {
    color: theme.colors.white,
    fontSize: 12,
    fontWeight: '700',
  },
  cardTitle: {
    marginTop: 14,
    fontSize: 16,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  cardBody: {
    marginTop: 6,
    fontSize: 12,
    color: theme.colors.mutedDark,
  },
  codeRow: {
    marginTop: 18,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: 10,
    paddingHorizontal: 14,
    borderRadius: 16,
    backgroundColor: theme.colors.white,
  },
  codeText: {
    fontSize: 14,
    fontWeight: '700',
    letterSpacing: 1,
    color: theme.colors.inkDark,
  },
  copyButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  copyText: {
    fontSize: 12,
    fontWeight: '600',
    color: theme.colors.sun,
  },
  primaryButton: {
    marginTop: 24,
    backgroundColor: theme.colors.sun,
    borderRadius: 24,
    paddingVertical: 14,
    alignItems: 'center',
  },
  primaryText: {
    color: theme.colors.white,
    fontSize: 14,
    fontWeight: '700',
  },
  secondaryButton: {
    marginTop: 12,
    borderRadius: 24,
    paddingVertical: 14,
    alignItems: 'center',
    backgroundColor: theme.colors.sand,
  },
  secondaryText: {
    color: theme.colors.inkDark,
    fontSize: 13,
    fontWeight: '700',
  },
});
