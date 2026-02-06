import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useEffect, useRef, useState } from 'react';
import { Image } from 'react-native';
import { Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
import { useAuth } from '@/lib/authStore';
import { meRequest } from '@/src/api/auth';
import { SafeAreaView } from 'react-native-safe-area-context';

type StatItem = { id: string; label: string; value: string };

export default function FullProfileScreen() {
  const { user, updateUser, status } = useAuth();
  const hasLoadedMe = useRef(false);
  const [stats] = useState<StatItem[]>([
    { id: 'orders', label: 'Orders', value: '—' },
    { id: 'points', label: 'Points', value: '—' },
    { id: 'reviews', label: 'Reviews', value: '—' },
  ]);

  useEffect(() => {
    if (status !== 'authed') {
      hasLoadedMe.current = false;
      return;
    }
    if (hasLoadedMe.current) return;
    hasLoadedMe.current = true;
    const load = async () => {
      try {
        const me = await meRequest();
        const fullName = `${me.first_name ?? ''} ${me.last_name ?? ''}`.trim();
        updateUser({
          name: (me.name ?? fullName) || 'Customer',
          email: me.email ?? undefined,
          avatar: me.avatar ?? null,
          phone: me.phone ?? null,
        });
      } catch {
        // ignore profile fetch errors
      }
    };

    load();
  }, [status, updateUser]);

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView style={styles.scroll} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.headerRow}>
          <Pressable style={styles.iconButton} onPress={() => router.back()}>
            <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
          </Pressable>
          <Text style={styles.title}>Profile</Text>
          <Pressable style={styles.iconButton} onPress={() => router.push('/settings/profile')}>
            <Feather name="edit-2" size={16} color={theme.colors.inkDark} />
          </Pressable>
        </View>

        <View style={styles.profileCard}>
          <View style={styles.avatarWrap}>
            {user?.avatar ? (
              <Image source={{ uri: user.avatar }} style={styles.avatarImage} />
            ) : (
              <View style={styles.avatarFallback} />
            )}
          </View>
          <Text style={styles.name}>{user?.name ?? 'Customer'}</Text>
          <Text style={styles.email}>{user?.email ?? '—'}</Text>
          <View style={styles.statRow}>
            {stats.map((stat) => (
              <View key={stat.id} style={styles.statCard}>
                <Text style={styles.statValue}>{stat.value}</Text>
                <Text style={styles.statLabel}>{stat.label}</Text>
              </View>
            ))}
          </View>
        </View>

        <View style={styles.actionRow}>
          <Pressable style={styles.actionCard} onPress={() => router.push('/rewards')}>
            <Feather name="gift" size={16} color={theme.colors.inkDark} />
            <Text style={styles.actionText}>Rewards</Text>
          </Pressable>
          <Pressable style={styles.actionCard} onPress={() => router.push('/settings')}>
            <Feather name="settings" size={16} color={theme.colors.inkDark} />
            <Text style={styles.actionText}>Settings</Text>
          </Pressable>
        </View>

        <Pressable style={styles.primaryButton} onPress={() => router.push('/orders/history')}>
          <Text style={styles.primaryText}>View order history</Text>
        </Pressable>
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
  profileCard: {
    borderRadius: 24,
    backgroundColor: theme.colors.sand,
    padding: 20,
    alignItems: 'center',
  },
  avatarWrap: {
    width: 86,
    height: 86,
    borderRadius: 43,
    overflow: 'hidden',
    backgroundColor: theme.colors.white,
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarImage: {
    width: '100%',
    height: '100%'
  },
  avatarFallback: {
    width: 70,
    height: 70,
    borderRadius: 35,
    backgroundColor: '#e1e5f2',
  },
  name: {
    marginTop: 12,
    fontSize: 18,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  email: {
    marginTop: 4,
    fontSize: 12,
    color: theme.colors.mutedDark,
  },
  statRow: {
    marginTop: 16,
    flexDirection: 'row',
    gap: 10,
  },
  statCard: {
    flex: 1,
    padding: 12,
    borderRadius: 16,
    backgroundColor: theme.colors.white,
    alignItems: 'center',
  },
  statValue: {
    fontSize: 14,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  statLabel: {
    marginTop: 4,
    fontSize: 11,
    color: theme.colors.mutedDark,
  },
  actionRow: {
    marginTop: 18,
    flexDirection: 'row',
    gap: 12,
  },
  actionCard: {
    flex: 1,
    padding: 14,
    borderRadius: 18,
    backgroundColor: theme.colors.blueSoft,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    justifyContent: 'center',
  },
  actionText: {
    fontSize: 13,
    fontWeight: '700',
    color: theme.colors.sun,
  },
  primaryButton: {
    marginTop: 22,
    backgroundColor: theme.colors.sun,
    borderRadius: 24,
    paddingVertical: 14,
    alignItems: 'center',
  },
  primaryText: {
    fontSize: 14,
    fontWeight: '700',
    color: theme.colors.white,
  },
});
