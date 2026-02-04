import { Feather } from '@expo/vector-icons';
import { router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, TextInput, View } from '@/src/utils/responsiveStyleSheet';
import type { TrackingEvent } from '@/src/types/orders';
import { trackOrder } from '@/src/api/orders';
import { useOrders } from '@/lib/ordersStore';
import { theme } from '@/src/theme';
import { Skeleton } from '@/src/components/ui/Skeleton';
export default function TrackOrderScreen() {
  const params = useLocalSearchParams();
  const initialNumber = typeof params.number === 'string' ? params.number : '';
  const { getOrderByNumber } = useOrders();
  const [number, setNumber] = useState(initialNumber);
  const [email, setEmail] = useState('');
  const [submitted, setSubmitted] = useState(false);
  const [loading, setLoading] = useState(false);
  const [events, setEvents] = useState<TrackingEvent[] | null>(null);
  const [error, setError] = useState('');

  const handleTrack = async () => {
    setSubmitted(true);
    setLoading(true);
    setError('');
    const trimmedNumber = number.trim();
    const localOrder = trimmedNumber ? getOrderByNumber(trimmedNumber) : undefined;

    if (!trimmedNumber) {
      setEvents(null);
      setError('Enter your order number.');
      setLoading(false);
      return;
    }

    if (localOrder) {
      setEvents(localOrder.tracking ?? []);
      setLoading(false);
      return;
    }

    if (!email.trim()) {
      setEvents(null);
      setError('Enter the email used for the order.');
      setLoading(false);
      return;
    }

    try {
      const result = await trackOrder(trimmedNumber, email.trim());
      setEvents(result.tracking);
    } catch (err: any) {
      setEvents(null);
      setError(err?.message ?? 'Order not found. Check the number and email, then try again.');
    }
    setLoading(false);
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
      <View style={styles.headerRow}>
        <Pressable style={styles.iconButton} onPress={() => router.back()}>
          <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
        </Pressable>
        <Text style={styles.title}>Track order</Text>
        <Pressable style={styles.iconButton} onPress={() => router.push('/(tabs)/home')}>
          <Feather name="x" size={16} color={theme.colors.inkDark} />
        </Pressable>
      </View>
      <Text style={styles.subtitle}>Enter your order number and email.</Text>
      <View style={styles.card}>
        <TextInput
          style={styles.input}
          placeholder="Order number"
          placeholderTextColor="#b6b6b6"
          value={number}
          onChangeText={setNumber}
        />
        <TextInput
          style={styles.input}
          placeholder="Email"
          placeholderTextColor="#b6b6b6"
          value={email}
          onChangeText={setEmail}
        />
        <Pressable style={styles.primaryButton} onPress={handleTrack}>
          <Text style={styles.primaryText}>Track</Text>
        </Pressable>
      </View>

      {submitted ? (
        loading ? (
          <View style={styles.loader}>
            <Skeleton width="45%" height={12} />
            {[0, 1, 2].map((index) => (
              <View key={`sk-${index}`} style={styles.timelineRow}>
                <Skeleton width={10} height={10} radius={5} />
                <View style={styles.timelineBody}>
                  <Skeleton width="50%" height={12} />
                  <Skeleton width="80%" height={10} style={styles.skeletonGap} />
                  <Skeleton width="35%" height={10} style={styles.skeletonGap} />
                </View>
              </View>
            ))}
          </View>
        ) : events ? (
          <View style={styles.timeline}>
            <Text style={styles.sectionTitle}>Tracking timeline</Text>
            {events.length === 0 ? (
              <View style={styles.emptyTrackingCard}>
                <Text style={styles.emptyTrackingTitle}>Updates on the way</Text>
                <Text style={styles.emptyTrackingBody}>
                  We will show shipping scans as soon as the carrier updates the route.
                </Text>
              </View>
            ) : (
              events.map((event) => (
                <View key={event.id} style={styles.timelineRow}>
                  <View style={styles.timelineDot} />
                  <View style={styles.timelineBody}>
                    <Text style={styles.timelineStatus}>{event.status}</Text>
                    <Text style={styles.timelineDesc}>{event.description}</Text>
                    <Text style={styles.timelineTime}>{event.occurredAt}</Text>
                  </View>
                </View>
              ))
            )}
          </View>
        ) : (
          <View style={styles.errorCard}>
            <Text style={styles.errorTitle}>Order not found</Text>
            <Text style={styles.errorBody}>{error}</Text>
          </View>
        )
      ) : null}
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
    fontSize: 18,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  subtitle: {
    fontSize: 13,
    color: theme.colors.mutedDark,
    marginBottom: 18,
  },
  card: {
    backgroundColor: theme.colors.white,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: theme.colors.sand,
    padding: 16,
    gap: 12,
  },
  sectionTitle: {
    fontSize: 14,
    fontWeight: '700',
    color: theme.colors.inkDark,
    marginTop: 18,
    marginBottom: 12,
  },
  timeline: {
    marginTop: 18,
  },
  loader: {
    marginTop: 18,
    gap: 12,
  },
  skeletonGap: {
    marginTop: 6,
  },
  timelineRow: {
    flexDirection: 'row',
    gap: 12,
    marginBottom: 12,
  },
  emptyTrackingCard: {
    backgroundColor: theme.colors.white,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: theme.colors.sand,
    padding: 14,
  },
  emptyTrackingTitle: {
    fontSize: 13,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  emptyTrackingBody: {
    fontSize: 12,
    color: theme.colors.mutedDark,
    marginTop: 4,
  },
  timelineDot: {
    width: 10,
    height: 10,
    borderRadius: 5,
    backgroundColor: theme.colors.sun,
    marginTop: 6,
  },
  timelineBody: {
    flex: 1,
  },
  timelineStatus: {
    fontSize: 13,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  timelineDesc: {
    fontSize: 12,
    color: theme.colors.mutedDark,
    marginTop: 4,
  },
  timelineTime: {
    fontSize: 11,
    color: theme.colors.sun,
    marginTop: 4,
    fontWeight: '600',
  },
  errorCard: {
    marginTop: 18,
    backgroundColor: theme.colors.white,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: theme.colors.sand,
    padding: 14,
  },
  errorTitle: {
    fontSize: 14,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  errorBody: {
    fontSize: 12,
    color: theme.colors.mutedDark,
    marginTop: 4,
  },
  input: {
    borderWidth: 1,
    borderColor: '#e6e8ef',
    borderRadius: 18,
    paddingHorizontal: 14,
    paddingVertical: 12,
    fontSize: 13,
    color: theme.colors.inkDark,
    backgroundColor: theme.colors.sand,
  },
  primaryButton: {
    backgroundColor: theme.colors.sun,
    paddingVertical: 12,
    borderRadius: 20,
    alignItems: 'center',
  },
  primaryText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '700',
  },
});
