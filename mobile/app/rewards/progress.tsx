import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useEffect, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
import { fetchRewardSummary } from '@/src/api/rewards';
import type { RewardSummary } from '@/src/types/rewards';
import { useToast } from '@/src/overlays/ToastProvider';
export default function RewardsProgressScreen() {
  const { show } = useToast();
  const [summary, setSummary] = useState<RewardSummary | null>(null);

  useEffect(() => {
    let active = true;
    fetchRewardSummary()
      .then((data) => {
        if (!active) return;
        setSummary(data);
      })
      .catch((err: any) => {
        if (!active) return;
        show({ type: 'error', message: err?.message ?? 'Unable to load rewards.' });
        setSummary(null);
      });

    return () => {
      active = false;
    };
  }, [show]);

  const progress = Math.min(100, Math.max(0, summary?.progressPercent ?? 0));

  return (
    <View style={styles.container}>
      <View style={styles.headerRow}>
        <Pressable style={styles.iconButton} onPress={() => router.back()}>
          <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
        </Pressable>
        <Text style={styles.title}>Rewards progress</Text>
        <Pressable style={styles.iconButton} onPress={() => router.push('/(tabs)/home')}>
          <Feather name="x" size={16} color={theme.colors.inkDark} />
        </Pressable>
      </View>

      <View style={styles.progressCard}>
        <Text style={styles.progressTitle}>{summary?.tier ?? 'Starter'} tier</Text>
        <Text style={styles.progressValue}>{summary?.pointsBalance ?? 0} pts</Text>
        <View style={styles.progressBar}>
          <View style={[styles.progressFill, { width: `${progress}%` }]} />
        </View>
        <Text style={styles.progressBody}>
          {summary?.pointsToNextTier
            ? `${summary.pointsToNextTier} points left to reach ${summary.nextTier ?? 'next tier'}.`
            : 'Keep shopping to unlock the next tier.'}
        </Text>
      </View>

      <View style={styles.goalCard}>
        <Text style={styles.goalTitle}>Earn more</Text>
        <Text style={styles.goalBody}>Shop 3 more items this week to unlock a bonus voucher.</Text>
        <Pressable style={styles.primaryButton} onPress={() => router.push('/rewards/voucher-expire')}>
          <Text style={styles.primaryText}>View rewards</Text>
        </Pressable>
      </View>
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
  progressCard: {
    borderRadius: 20,
    backgroundColor: theme.colors.blueSoft,
    padding: 18,
  },
  progressTitle: {
    fontSize: 14,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  progressValue: {
    marginTop: 6,
    fontSize: 22,
    fontWeight: '800',
    color: theme.colors.inkDark,
  },
  progressBar: {
    marginTop: 14,
    height: 8,
    borderRadius: 4,
    backgroundColor: '#dfe6ff',
    overflow: 'hidden',
  },
  progressFill: {
    height: '100%',
    backgroundColor: theme.colors.sun,
  },
  progressBody: {
    marginTop: 10,
    fontSize: 12,
    color: theme.colors.mutedDark,
  },
  goalCard: {
    marginTop: 20,
    borderRadius: 20,
    backgroundColor: theme.colors.sand,
    padding: 18,
  },
  goalTitle: {
    fontSize: 14,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  goalBody: {
    marginTop: 8,
    fontSize: 12,
    color: theme.colors.mutedDark,
  },
  primaryButton: {
    marginTop: 16,
    backgroundColor: theme.colors.sun,
    borderRadius: 18,
    paddingVertical: 12,
    alignItems: 'center',
  },
  primaryText: {
    fontSize: 13,
    fontWeight: '700',
    color: theme.colors.white,
  },
});
