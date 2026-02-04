import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { RefreshControl } from 'react-native';
import { theme } from '@/src/theme';
import { fetchRewardSummary, fetchVouchers } from '@/src/api/rewards';
import type { RewardSummary, Voucher } from '@/src/types/rewards';
import { useToast } from '@/src/overlays/ToastProvider';
import { usePullToRefresh } from '@/src/hooks/usePullToRefresh';

export default function RewardsScreen() {
  const { show } = useToast();
  const [summary, setSummary] = useState<RewardSummary | null>(null);
  const [vouchers, setVouchers] = useState<Voucher[]>([]);
  const [loading, setLoading] = useState(true);
  const requestId = useRef(0);

  const loadRewards = useCallback(async () => {
    const id = ++requestId.current;
    setLoading(true);
    try {
      const [summary, vouchers] = await Promise.all([fetchRewardSummary(), fetchVouchers()]);
      if (id !== requestId.current) return;
      setSummary(summary);
      setVouchers(vouchers);
    } catch (err: any) {
      if (id !== requestId.current) return;
      show({ type: 'error', message: err?.message ?? 'Unable to load rewards.' });
      setSummary(null);
      setVouchers([]);
    } finally {
      if (id === requestId.current) setLoading(false);
    }
  }, [show]);

  useEffect(() => {
    loadRewards();
    return () => {
      requestId.current += 1;
    };
  }, [loadRewards]);

  const { refreshing, onRefresh } = usePullToRefresh(loadRewards);

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
        <Text style={styles.title}>Rewards</Text>
        <Pressable style={styles.iconButton} onPress={() => router.push('/(tabs)/home')}>
          <Feather name="x" size={16} color={theme.colors.inkDark} />
        </Pressable>
      </View>

      <View style={styles.pointsCard}>
        <Text style={styles.pointsLabel}>Your points</Text>
        <Text style={styles.pointsValue}>{summary?.pointsBalance ?? 0}</Text>
        <Text style={styles.pointsBody}>
          {summary?.pointsToNextTier
            ? `Only ${summary.pointsToNextTier} points to ${summary.nextTier ?? 'next tier'}.`
            : 'Keep shopping to unlock rewards.'}
        </Text>
        <Pressable style={styles.pointsButton} onPress={() => router.push('/rewards/progress')}>
          <Text style={styles.pointsButtonText}>View progress</Text>
        </Pressable>
      </View>

      <View style={styles.sectionHeader}>
        <Text style={styles.sectionTitle}>Active vouchers</Text>
        <Pressable onPress={() => router.push('/rewards/voucher-reminder')}>
          <Text style={styles.sectionAction}>Reminders</Text>
        </Pressable>
      </View>

      <View style={styles.list}>
        {!loading && vouchers.length === 0 ? (
          <View style={styles.emptyCard}>
            <Text style={styles.emptyTitle}>No vouchers yet</Text>
            <Text style={styles.emptyBody}>We’ll drop new rewards as you shop.</Text>
          </View>
        ) : null}
        {vouchers.map((voucher) => (
          <Pressable
            key={voucher.id}
            style={styles.card}
            onPress={() => router.push(`/rewards/voucher-expire?id=${encodeURIComponent(voucher.id)}`)}
          >
            <View style={styles.badge}>
              <Text style={styles.badgeText}>{voucher.value ?? 'Reward'}</Text>
            </View>
            <View style={styles.cardBody}>
              <Text style={styles.cardTitle}>{voucher.title ?? 'Voucher'}</Text>
              <Text style={styles.cardSub}>
                {voucher.endsAt ? `Expires ${new Date(voucher.endsAt).toLocaleDateString()}` : 'No expiry'}
              </Text>
            </View>
            <Feather name="chevron-right" size={16} color={theme.colors.inkDark} />
          </Pressable>
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
    marginBottom: 16,
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
  pointsCard: {
    borderRadius: 20,
    backgroundColor: theme.colors.blueSoft,
    padding: 18,
  },
  pointsLabel: {
    fontSize: 12,
    color: theme.colors.mutedDark,
  },
  pointsValue: {
    marginTop: 6,
    fontSize: 24,
    fontWeight: '800',
    color: theme.colors.inkDark,
  },
  pointsBody: {
    marginTop: 8,
    fontSize: 12,
    color: theme.colors.mutedDark,
  },
  pointsButton: {
    marginTop: 14,
    alignSelf: 'flex-start',
    backgroundColor: theme.colors.sun,
    borderRadius: 16,
    paddingHorizontal: 14,
    paddingVertical: 8,
  },
  pointsButtonText: {
    fontSize: 12,
    fontWeight: '700',
    color: theme.colors.white,
  },
  sectionHeader: {
    marginTop: 24,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 12,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  sectionAction: {
    fontSize: 12,
    fontWeight: '700',
    color: theme.colors.sun,
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
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    padding: 14,
    borderRadius: 18,
    backgroundColor: theme.colors.sand,
  },
  badge: {
    backgroundColor: theme.colors.sun,
    borderRadius: 12,
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  badgeText: {
    fontSize: 10,
    fontWeight: '700',
    color: theme.colors.white,
  },
  cardBody: {
    flex: 1,
  },
  cardTitle: {
    fontSize: 13,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  cardSub: {
    marginTop: 4,
    fontSize: 11,
    color: theme.colors.mutedDark,
  },
});
