import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { useEffect, useMemo, useState } from 'react';
import { Skeleton } from '@/src/components/ui/Skeleton';
import { useAddresses } from '@/lib/addressesStore';
import { usePreferences } from '@/src/store/preferencesStore';
import { useToast } from '@/src/overlays/ToastProvider';
import { theme } from '@/src/theme';

export default function ShippingAddressScreen() {
  const { setCountry, setShippingAddress } = usePreferences();
  const { items, loading, error, setDefault } = useAddresses();
  const { show } = useToast();
  const [selectedId, setSelectedId] = useState<string | null>(null);

  const formatted = useMemo(() => {
    return items.map((address) => ({
      id: address.id,
      title: address.name || 'Address',
      detail: [address.line1, address.city, address.state, address.postalCode]
        .filter((value) => value && String(value).trim().length > 0)
        .join(', '),
      country: address.country,
      isDefault: address.isDefault,
      raw: address,
    }));
  }, [items]);

  useEffect(() => {
    if (loading) return;
    if (formatted.length === 0) return;
    const fallback = formatted.find((item) => item.isDefault) ?? formatted[0];
    if (!fallback) return;
    if (selectedId && selectedId === fallback.id) return;
    setSelectedId((prev) => prev ?? fallback.id);
    if (fallback.raw) {
      setShippingAddress({
        name: fallback.raw.name ?? '',
        phone: fallback.raw.phone ?? '',
        address: fallback.raw.line1 ?? '',
        city: fallback.raw.city ?? '',
        postcode: fallback.raw.postalCode ?? '',
        country: fallback.raw.country ?? '',
      });
      setCountry(fallback.raw.country ?? '');
    }
  }, [formatted, loading, selectedId, setCountry, setShippingAddress]);

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
      <View style={styles.headerRow}>
        <Pressable style={styles.iconButton} onPress={() => router.back()}>
          <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
        </Pressable>
        <Text style={styles.title}>Shipping address</Text>
        <Pressable style={styles.iconButton} onPress={() => router.push('/(tabs)/home')}>
          <Feather name="x" size={16} color={theme.colors.inkDark} />
        </Pressable>
      </View>

      <View style={styles.list}>
        {loading ? (
          [0, 1, 2].map((index) => (
            <View key={`addr-skel-${index}`} style={styles.card}>
              <Skeleton width={theme.moderateScale(18)} height={theme.moderateScale(18)} radius={9} />
              <View style={styles.cardBody}>
                <Skeleton width="45%" height={12} />
                <Skeleton width="70%" height={10} style={styles.skeletonGap} />
              </View>
            </View>
          ))
        ) : formatted.length === 0 ? (
          <View style={styles.emptyCard}>
            <Text style={styles.emptyTitle}>No saved addresses</Text>
            <Text style={styles.emptyBody}>Add a shipping address to continue checkout.</Text>
          </View>
        ) : (
          formatted.map((address) => (
            <Pressable
              key={address.id}
              style={styles.card}
              onPress={() => router.push(`/shipping/edit?id=${address.id}`)}
            >
              <Pressable
                style={styles.radio}
                onPress={async (event) => {
                  event.stopPropagation();
                  setSelectedId(address.id);
                  if (address.raw) {
                    setShippingAddress({
                      name: address.raw.name ?? '',
                      phone: address.raw.phone ?? '',
                      address: address.raw.line1 ?? '',
                      city: address.raw.city ?? '',
                      postcode: address.raw.postalCode ?? '',
                      country: address.raw.country ?? '',
                    });
                    setCountry(address.raw.country ?? '');
                  }
                  if (!address.isDefault) {
                    const result = await setDefault(address.id);
                    if (!result.ok) {
                      show({ type: 'error', message: result.message ?? 'Unable to set default address.' });
                    }
                  }
                }}
              >
                {selectedId === address.id ? <View style={styles.radioDot} /> : null}
              </Pressable>
              <View style={styles.cardBody}>
                <Text style={styles.cardTitle}>{address.title}</Text>
                <Text style={styles.cardSub}>{address.detail}</Text>
                {address.country ? <Text style={styles.cardSub}>{address.country}</Text> : null}
              </View>
              <Feather name="chevron-right" size={16} color={theme.colors.inkDark} />
            </Pressable>
          ))
        )}
        {!loading && error ? <Text style={styles.errorText}>{error}</Text> : null}
      </View>

      <Pressable style={styles.primaryButton} onPress={() => router.push('/shipping/edit')}>
        <Text style={styles.primaryText}>Add new address</Text>
      </Pressable>
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
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    padding: 14,
    borderRadius: 18,
    backgroundColor: theme.colors.sand,
  },
  radio: {
    width: 18,
    height: 18,
    borderRadius: 9,
    borderWidth: 1,
    borderColor: '#c7c7c7',
    alignItems: 'center',
    justifyContent: 'center',
  },
  radioDot: {
    width: 10,
    height: 10,
    borderRadius: 5,
    backgroundColor: theme.colors.sun,
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
  primaryButton: {
    marginTop: 24,
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
