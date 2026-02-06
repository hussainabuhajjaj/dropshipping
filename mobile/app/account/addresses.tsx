import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useMemo } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { Skeleton } from '@/src/components/ui/Skeleton';
import { useAddresses } from '@/lib/addressesStore';
import { theme } from '@/src/theme';
import { SafeAreaView } from 'react-native-safe-area-context';

export default function AddressesScreen() {
  const { items, loading, error } = useAddresses();
  const list = useMemo(() => {
    return items.map((address) => ({
      id: address.id,
      name: address.name || 'Address',
      line: [address.line1, address.city].filter(Boolean).join(', '),
      city: address.state || address.postalCode || '',
      country: address.country || '',
      isDefault: address.isDefault,
    }));
  }, [items]);

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView style={styles.scroll} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.headerRow}>
          <Pressable style={styles.iconButton} onPress={() => router.back()}>
            <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
          </Pressable>
          <Text style={styles.title}>Addresses</Text>
          <Pressable style={styles.addButton} onPress={() => router.push('/shipping/edit')}>
            <Text style={styles.addButtonText}>Add</Text>
          </Pressable>
        </View>
        <Text style={styles.subtitle}>Manage delivery locations.</Text>

        <View style={styles.list}>
          {loading ? (
            [0, 1, 2].map((index) => (
              <View key={`addr-skel-${index}`} style={styles.card}>
                <Skeleton width="40%" height={12} />
                <Skeleton width="70%" height={10} style={styles.skeletonGap} />
                <Skeleton width="50%" height={10} style={styles.skeletonGap} />
              </View>
            ))
          ) : list.length === 0 ? (
            <View style={styles.emptyCard}>
              <Text style={styles.emptyTitle}>No saved addresses</Text>
              <Text style={styles.emptyBody}>Add a delivery location to get started.</Text>
            </View>
          ) : (
            list.map((address) => (
              <Pressable
                key={address.id}
                style={styles.card}
                onPress={() => router.push(`/shipping/edit?id=${address.id}`)}
              >
                <View style={styles.cardHeader}>
                  <Text style={styles.cardTitle}>{address.name}</Text>
                  {address.isDefault ? <Text style={styles.defaultBadge}>Default</Text> : null}
                </View>
                <Text style={styles.cardBody}>{address.line}</Text>
                {address.city ? <Text style={styles.cardBody}>{address.city}</Text> : null}
                {address.country ? <Text style={styles.cardBody}>{address.country}</Text> : null}
              </Pressable>
            ))
          )}
          {!loading && error ? <Text style={styles.errorText}>{error}</Text> : null}
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
    justifyContent: 'space-between',
    alignItems: 'center',
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
  addButton: {
    backgroundColor: theme.colors.blueSoft,
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 16,
  },
  addButtonText: {
    fontSize: 12,
    fontWeight: '700',
    color: theme.colors.sun,
  },
  list: {
    gap: 12,
  },
  skeletonGap: {
    marginTop: 6,
  },
  emptyCard: {
    padding: 16,
    borderRadius: 18,
    backgroundColor: theme.colors.sand,
    alignItems: 'center',
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
    textAlign: 'center',
  },
  errorText: {
    marginTop: 8,
    fontSize: 12,
    color: theme.colors.danger,
  },
  card: {
    backgroundColor: theme.colors.white,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: theme.colors.sand,
    padding: 14,
  },
  cardHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  cardTitle: {
    fontSize: 14,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  defaultBadge: {
    fontSize: 11,
    fontWeight: '700',
    color: theme.colors.primary,
  },
  cardBody: {
    fontSize: 12,
    color: theme.colors.mutedDark,
    marginTop: 4,
  },
});
