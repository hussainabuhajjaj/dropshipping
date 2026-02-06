import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Linking, RefreshControl } from 'react-native';
import { Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
import { fetchNotifications, markNotificationsRead } from '@/src/api/notifications';
import type { NotificationItem } from '@/src/types/notifications';
import { useToast } from '@/src/overlays/ToastProvider';
import { usePullToRefresh } from '@/src/hooks/usePullToRefresh';
import { SafeAreaView } from 'react-native-safe-area-context';

export default function NotificationsScreen() {
  const { show } = useToast();
  const [items, setItems] = useState<NotificationItem[]>([]);
  const [loading, setLoading] = useState(true);
  const requestId = useRef(0);

  const loadNotifications = useCallback(async () => {
    const id = ++requestId.current;
    setLoading(true);
    try {
      const { items } = await fetchNotifications({ per_page: 30 });
      if (id !== requestId.current) return;
      setItems(items);
      const unreadIds = items.filter((item) => !item.readAt).map((item) => item.id);
      if (unreadIds.length > 0) {
        markNotificationsRead({ ids: unreadIds }).catch(() => {});
      }
    } catch (err: any) {
      if (id !== requestId.current) return;
      show({ type: 'error', message: err?.message ?? 'Unable to load notifications.' });
      setItems([]);
    } finally {
      if (id === requestId.current) setLoading(false);
    }
  }, [show]);

  useEffect(() => {
    loadNotifications();
    return () => {
      requestId.current += 1;
    };
  }, [loadNotifications]);

  const openHref = (href?: string | null) => {
    if (!href) return;
    if (href.startsWith('http://') || href.startsWith('https://')) {
      Linking.openURL(href).catch(() => {});
      return;
    }
    router.push(href);
  };

  const cards = useMemo(() => (loading ? [] : items), [items, loading]);

  const { refreshing, onRefresh } = usePullToRefresh(loadNotifications);

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView
        style={styles.scroll}
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
          <Text style={styles.title}>Notifications</Text>
          <Pressable style={styles.iconButton} onPress={() => router.push('/(tabs)/home')}>
            <Feather name="x" size={16} color={theme.colors.inkDark} />
          </Pressable>
        </View>
        <Text style={styles.subtitle}>Stay on top of every update.</Text>

        <View style={styles.list}>
          {cards.length === 0 && !loading ? (
            <View style={styles.emptyCard}>
              <Text style={styles.emptyTitle}>No notifications yet</Text>
              <Text style={styles.emptyBody}>We’ll let you know when something happens.</Text>
            </View>
          ) : null}
          {cards.map((item) => (
            <Pressable key={item.id} style={styles.card} onPress={() => openHref(item.actionUrl)}>
              <Text style={styles.cardTitle}>{item.title ?? 'Update'}</Text>
              {item.body ? <Text style={styles.cardBody}>{item.body}</Text> : null}
              <Text style={styles.cardTime}>
                {item.createdAt ? new Date(item.createdAt).toLocaleDateString() : ''}
              </Text>
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
    marginTop: 6,
  },
  cardTime: {
    fontSize: 11,
    color: theme.colors.sun,
    marginTop: 8,
    fontWeight: '600',
  },
  emptyCard: {
    padding: 16,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: theme.colors.sand,
    backgroundColor: theme.colors.white,
  },
  emptyTitle: {
    fontSize: 14,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  emptyBody: {
    marginTop: 6,
    fontSize: 12,
    color: theme.colors.mutedDark,
  },
});
