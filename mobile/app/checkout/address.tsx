import { Feather } from '@expo/vector-icons';
import { router, useLocalSearchParams } from 'expo-router';
import { useEffect, useMemo, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, TextInput, View } from '@/src/utils/responsiveStyleSheet';
import { Skeleton } from '@/src/components/ui/Skeleton';
import { useAddresses } from '@/lib/addressesStore';
import { usePreferences } from '@/src/store/preferencesStore';
import { useToast } from '@/src/overlays/ToastProvider';
import { theme } from '@/src/theme';

type AddressForm = {
  name: string;
  phone: string;
  line1: string;
  line2: string;
  city: string;
  state: string;
  postalCode: string;
  country: string;
};
export default function EditShippingAddressScreen() {
  const params = useLocalSearchParams();
  const addressId = typeof params.id === 'string' ? params.id : '';
  const { items, loading, create, update } = useAddresses();
  const defaultAddress = useMemo(() => items.find((item) => item.isDefault), [items]);
  const resolvedId = addressId || defaultAddress?.id || '';
  const isEditing = Boolean(resolvedId);
  const { setCountry, setShippingAddress } = usePreferences();
  const { show } = useToast();
  const [form, setForm] = useState<AddressForm>({
    name: '',
    phone: '',
    line1: '',
    line2: '',
    city: '',
    state: '',
    postalCode: '',
    country: '',
  });
  const [error, setError] = useState<string | null>(null);

  const currentAddress = useMemo(() => items.find((item) => item.id === resolvedId), [items, resolvedId]);

  useEffect(() => {
    if (!isEditing || !currentAddress) return;
    setForm({
      name: currentAddress.name ?? '',
      phone: currentAddress.phone ?? '',
      line1: currentAddress.line1 ?? '',
      line2: currentAddress.line2 ?? '',
      city: currentAddress.city ?? '',
      state: currentAddress.state ?? '',
      postalCode: currentAddress.postalCode ?? '',
      country: currentAddress.country ?? '',
    });
  }, [currentAddress, isEditing]);

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
      <View style={styles.headerRow}>
        <Pressable style={styles.iconButton} onPress={() => router.back()}>
          <Feather name="arrow-left" size={16} color={theme.colors.inkDark} />
        </Pressable>
        <Text style={styles.title}>Shipping address</Text>
        <View style={styles.spacer} />
      </View>

      {loading && isEditing ? (
        <View style={styles.skeletonBlock}>
          {[0, 1, 2, 3].map((index) => (
            <Skeleton key={`addr-checkout-skel-${index}`} height={theme.moderateScale(44)} radius={theme.moderateScale(14)} />
          ))}
        </View>
      ) : (
        <View style={styles.form}>
          <TextInput
            style={styles.input}
            placeholder="Full name"
            placeholderTextColor="#c7c7c7"
            value={form.name}
            onChangeText={(name) => setForm((prev) => ({ ...prev, name }))}
          />
          <TextInput
            style={styles.input}
            placeholder="Phone"
            placeholderTextColor="#c7c7c7"
            value={form.phone}
            onChangeText={(phone) => setForm((prev) => ({ ...prev, phone }))}
          />
          <TextInput
            style={styles.input}
            placeholder="Street address"
            placeholderTextColor="#c7c7c7"
            value={form.line1}
            onChangeText={(line1) => setForm((prev) => ({ ...prev, line1 }))}
          />
          <TextInput
            style={styles.input}
            placeholder="Apartment, suite, etc"
            placeholderTextColor="#c7c7c7"
            value={form.line2}
            onChangeText={(line2) => setForm((prev) => ({ ...prev, line2 }))}
          />
          <TextInput
            style={styles.input}
            placeholder="City"
            placeholderTextColor="#c7c7c7"
            value={form.city}
            onChangeText={(city) => setForm((prev) => ({ ...prev, city }))}
          />
          <TextInput
            style={styles.input}
            placeholder="State"
            placeholderTextColor="#c7c7c7"
            value={form.state}
            onChangeText={(state) => setForm((prev) => ({ ...prev, state }))}
          />
          <TextInput
            style={styles.input}
            placeholder="Postal code"
            placeholderTextColor="#c7c7c7"
            value={form.postalCode}
            onChangeText={(postalCode) => setForm((prev) => ({ ...prev, postalCode }))}
          />
          <TextInput
            style={styles.input}
            placeholder="Country"
            placeholderTextColor="#c7c7c7"
            value={form.country}
            onChangeText={(country) => setForm((prev) => ({ ...prev, country }))}
          />
        </View>
      )}

      <Pressable
        style={styles.primaryButton}
        onPress={async () => {
          setError(null);
          const payload = {
            name: form.name || undefined,
            phone: form.phone || undefined,
            line1: form.line1,
            line2: form.line2 || undefined,
            city: form.city || undefined,
            state: form.state || undefined,
            postal_code: form.postalCode || undefined,
            country: form.country || undefined,
            type: 'shipping',
            is_default: isEditing ? Boolean(currentAddress?.isDefault) : true,
          };
          if (!payload.line1) {
            setError('Address line is required.');
            show({ type: 'error', message: 'Address line is required.' });
            return;
          }
          const result = isEditing
            ? await update(resolvedId, payload)
            : await create(payload);
          if (!result.ok || !result.address) {
            const message = result.message ?? 'Unable to save address.';
            setError(message);
            show({ type: 'error', message });
            return;
          }
          const saved = result.address;
          setShippingAddress({
            name: saved.name ?? '',
            phone: saved.phone ?? '',
            address: saved.line1 ?? '',
            city: saved.city ?? '',
            postcode: saved.postalCode ?? '',
            country: saved.country ?? '',
          });
          setCountry(saved.country ?? '');
          router.back();
        }}
      >
        <Text style={styles.primaryText}>Save</Text>
      </Pressable>
      {error ? <Text style={styles.errorText}>{error}</Text> : null}
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
    paddingTop: 10,
    paddingBottom: 24,
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
    width: 32,
    height: 32,
    borderRadius: 16,
    backgroundColor: theme.colors.gray100,
    alignItems: 'center',
    justifyContent: 'center',
  },
  spacer: {
    width: 32,
    height: 32,
  },
  form: {
    gap: 12,
  },
  input: {
    height: 50,
    borderRadius: 18,
    backgroundColor: theme.colors.sand,
    paddingHorizontal: 16,
    fontSize: 14,
    color: theme.colors.inkDark,
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
    color: theme.colors.gray200,
    fontWeight: '700',
  },
  errorText: {
    marginTop: 10,
    fontSize: 12,
    color: theme.colors.danger,
    textAlign: 'center',
  },
  skeletonBlock: {
    gap: theme.moderateScale(12),
  },
});
