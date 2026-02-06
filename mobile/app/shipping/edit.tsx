import { Feather } from '@expo/vector-icons';
import { router, useLocalSearchParams } from 'expo-router';
import { useEffect, useMemo, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, TextInput, View } from '@/src/utils/responsiveStyleSheet';
import { Skeleton } from '@/src/components/ui/Skeleton';
import { useAddresses } from '@/lib/addressesStore';
import { usePreferences } from '@/src/store/preferencesStore';
import { useToast } from '@/src/overlays/ToastProvider';
import { theme } from '@/src/theme';
import { KeyboardAvoidingView, Platform } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

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
export default function ShippingEditScreen() {
  const params = useLocalSearchParams();
  const addressId = typeof params.id === 'string' ? params.id : '';
  const isEditing = Boolean(addressId);
  const { items, loading, create, update } = useAddresses();
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

  const currentAddress = useMemo(() => items.find((item) => item.id === addressId), [items, addressId]);

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
    <SafeAreaView style={styles.container}>
      <KeyboardAvoidingView
        style={styles.keyboard}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        keyboardVerticalOffset={Platform.OS === 'ios' ? theme.moderateScale(20) : 0}
      >
        <ScrollView
          style={styles.scroll}
          contentContainerStyle={styles.content}
          showsVerticalScrollIndicator={false}
          keyboardShouldPersistTaps="handled"
          keyboardDismissMode="interactive"
          automaticallyAdjustKeyboardInsets
        >
      <View style={styles.headerRow}>
        <Pressable style={styles.iconButton} onPress={() => router.back()}>
          <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
        </Pressable>
        <Text style={styles.title}>{isEditing ? 'Edit address' : 'Add address'}</Text>
        <Pressable style={styles.iconButton} onPress={() => router.push('/(tabs)/home')}>
          <Feather name="x" size={16} color={theme.colors.inkDark} />
        </Pressable>
      </View>

      {loading && isEditing ? (
        <View style={styles.skeletonBlock}>
          {[0, 1, 2, 3].map((index) => (
            <Skeleton key={`addr-edit-skel-${index}`} height={theme.moderateScale(44)} radius={theme.moderateScale(14)} />
          ))}
        </View>
      ) : (
        <>
      <View style={styles.field}>
        <Text style={styles.label}>Full name</Text>
        <TextInput
          style={styles.input}
          placeholder="Romina E."
          placeholderTextColor="#b6b6b6"
          value={form.name}
          onChangeText={(name) => setForm((prev) => ({ ...prev, name }))}
        />
      </View>
      <View style={styles.field}>
        <Text style={styles.label}>Phone</Text>
        <TextInput
          style={styles.input}
          placeholder="+225 00 00 00 00"
          placeholderTextColor="#b6b6b6"
          value={form.phone}
          onChangeText={(phone) => setForm((prev) => ({ ...prev, phone }))}
        />
      </View>
      <View style={styles.field}>
        <Text style={styles.label}>Address</Text>
        <TextInput
          style={styles.input}
          placeholder="102 Market St"
          placeholderTextColor="#b6b6b6"
          value={form.line1}
          onChangeText={(line1) => setForm((prev) => ({ ...prev, line1 }))}
        />
      </View>
      <View style={styles.field}>
        <Text style={styles.label}>Apartment, suite, etc</Text>
        <TextInput
          style={styles.input}
          placeholder="Apt 12"
          placeholderTextColor="#b6b6b6"
          value={form.line2}
          onChangeText={(line2) => setForm((prev) => ({ ...prev, line2 }))}
        />
      </View>
      <View style={styles.field}>
        <Text style={styles.label}>City</Text>
        <TextInput
          style={styles.input}
          placeholder="San Francisco"
          placeholderTextColor="#b6b6b6"
          value={form.city}
          onChangeText={(city) => setForm((prev) => ({ ...prev, city }))}
        />
      </View>
      <View style={styles.fieldRow}>
        <View style={styles.fieldHalf}>
          <Text style={styles.label}>State</Text>
          <TextInput
            style={styles.input}
            placeholder="CA"
            placeholderTextColor="#b6b6b6"
            value={form.state}
            onChangeText={(state) => setForm((prev) => ({ ...prev, state }))}
          />
        </View>
        <View style={styles.fieldHalf}>
          <Text style={styles.label}>ZIP</Text>
          <TextInput
            style={styles.input}
            placeholder="94105"
            placeholderTextColor="#b6b6b6"
            value={form.postalCode}
            onChangeText={(postalCode) => setForm((prev) => ({ ...prev, postalCode }))}
          />
        </View>
      </View>
      <View style={styles.field}>
        <Text style={styles.label}>Country</Text>
        <TextInput
          style={styles.input}
          placeholder="CI"
          placeholderTextColor="#b6b6b6"
          value={form.country}
          onChangeText={(country) => setForm((prev) => ({ ...prev, country }))}
        />
      </View>

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
            ? await update(addressId, payload)
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
          router.replace('/shipping');
        }}
      >
      <Text style={styles.primaryText}>Save address</Text>
      </Pressable>
      {error ? <Text style={styles.errorText}>{error}</Text> : null}
      </>
      )}
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.white,
  },
  keyboard: {
    flex: 1,
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
  field: {
    marginBottom: 14,
  },
  fieldRow: {
    flexDirection: 'row',
    gap: 12,
  },
  fieldHalf: {
    flex: 1,
  },
  label: {
    fontSize: 12,
    fontWeight: '600',
    color: theme.colors.mutedDark,
    marginBottom: 8,
  },
  input: {
    borderRadius: 16,
    backgroundColor: theme.colors.sand,
    paddingHorizontal: 14,
    paddingVertical: 12,
    fontSize: 13,
    color: theme.colors.inkDark,
  },
  primaryButton: {
    marginTop: 20,
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
