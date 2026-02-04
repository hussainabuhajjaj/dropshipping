import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useState } from 'react';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { DeleteAccountDialog } from '@/src/overlays/DeleteAccountDialog';
import { StatusDialog } from '@/src/overlays/StatusDialog';
import { theme } from '@/src/theme';
import { usePreferences } from '@/src/store/preferencesStore';
import { requestAccountDeletion } from '@/src/api/account';
import { logoutRequest } from '@/src/api/auth';
import { useAuth } from '@/lib/authStore';
import { useCart } from '@/lib/cartStore';
import { useWishlist } from '@/lib/wishlistStore';
import { useOrders } from '@/lib/ordersStore';
import { usePaymentMethods } from '@/lib/paymentMethodsStore';
import { useAddresses } from '@/lib/addressesStore';

type RowItem = {
  label: string;
  value?: string;
  onPress?: () => void;
  danger?: boolean;
};

const personalItems: RowItem[] = [
  { label: 'Profile', onPress: () => router.push('/settings/profile') },
  { label: 'Shipping Address', onPress: () => router.push('/shipping') },
  { label: 'Payment methods', onPress: () => router.push('/settings/payments') },
];

const shopItems: RowItem[] = [
  { label: 'Country', value: 'Vietnam', onPress: () => router.push('/preferences/country') },
  { label: 'Currency', value: '$ USD', onPress: () => router.push('/preferences/currency') },
  { label: 'Sizes', value: 'UK', onPress: () => router.push('/preferences/sizes') },
  { label: 'Terms & Privacy', onPress: () => router.push('/legal/terms') },
];

const accountItems: RowItem[] = [
  { label: 'Language', value: 'English', onPress: () => router.push('/preferences/language') },
  { label: 'About Simbazu', onPress: () => router.push('/about') },
];

export default function SettingsScreen() {
  const [showDeleteDialog, setShowDeleteDialog] = useState(false);
  const { state } = usePreferences();
  const { status, logout } = useAuth();
  const cart = useCart();
  const wishlist = useWishlist();
  const orders = useOrders();
  const paymentMethods = usePaymentMethods();
  const addresses = useAddresses();

  const [busy, setBusy] = useState(false);
  const [deleteResult, setDeleteResult] = useState<null | { ok: boolean; message: string }>(null);

  const clearLocalState = () => {
    cart.clear();
    wishlist.clear();
    orders.clearLocal();
    paymentMethods.reset();
    addresses.clear();
  };

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.content}>
        <Text style={styles.title}>Settings</Text>

        <Text style={styles.sectionTitle}>Personal</Text>
        <View style={styles.sectionList}>
          {personalItems.map((item) => (
            <Pressable key={item.label} style={styles.row} onPress={item.onPress}>
              <Text style={styles.rowLabel}>{item.label}</Text>
              <Feather name="chevron-right" size={theme.moderateScale(16)} color={theme.colors.mutedLight} />
            </Pressable>
          ))}
        </View>

        <Text style={styles.sectionTitle}>Shop</Text>
        <View style={styles.sectionList}>
          {shopItems.map((item) => (
            <Pressable key={item.label} style={styles.row} onPress={item.onPress}>
              <Text style={styles.rowLabel}>{item.label}</Text>
              <View style={styles.rowRight}>
                {item.label === 'Country' ? (
                  <Text style={styles.rowValue}>{state.country}</Text>
                ) : item.label === 'Currency' ? (
                  <Text style={styles.rowValue}>{state.currency}</Text>
                ) : item.label === 'Sizes' ? (
                  <Text style={styles.rowValue}>{state.size}</Text>
                ) : item.value ? (
                  <Text style={styles.rowValue}>{item.value}</Text>
                ) : null}
                <Feather name="chevron-right" size={theme.moderateScale(16)} color={theme.colors.mutedLight} />
              </View>
            </Pressable>
          ))}
        </View>

        <Text style={styles.sectionTitle}>Account</Text>
        <View style={styles.sectionList}>
          {accountItems.map((item) => (
            <Pressable key={item.label} style={styles.row} onPress={item.onPress}>
              <Text style={styles.rowLabel}>{item.label}</Text>
              <View style={styles.rowRight}>
                {item.label === 'Language' ? (
                  <Text style={styles.rowValue}>{state.language}</Text>
                ) : item.value ? (
                  <Text style={styles.rowValue}>{item.value}</Text>
                ) : null}
                <Feather name="chevron-right" size={theme.moderateScale(16)} color={theme.colors.mutedLight} />
              </View>
            </Pressable>
          ))}
          <Pressable
            style={styles.row}
            onPress={async () => {
              try {
                if (status !== 'guest') {
                  await logoutRequest();
                }
              } catch {
                // ignore logout errors and still clear local state
              } finally {
                clearLocalState();
                await logout();
                router.replace('/(tabs)/home');
              }
            }}
          >
            <Text style={[styles.rowLabel, styles.logoutText]}>Log out</Text>
          </Pressable>
        </View>

        <Pressable onPress={() => setShowDeleteDialog(true)} style={styles.deleteRow}>
          <Text style={styles.deleteText}>Delete My Account</Text>
        </Pressable>

        <View style={styles.footer}>
          <Text style={styles.footerTitle}>Simbazu</Text>
          <Text style={styles.footerBody}>Version 1.0 April, 2020</Text>
        </View>
      </View>

      <DeleteAccountDialog
        visible={showDeleteDialog}
        onCancel={() => setShowDeleteDialog(false)}
        onSignOut={() => {
          setShowDeleteDialog(false);
          clearLocalState();
          logout();
          router.replace('/(tabs)/home');
        }}
        onConfirm={async () => {
          setShowDeleteDialog(false);
          if (status === 'guest') {
            clearLocalState();
            await logout();
            setDeleteResult({
              ok: false,
              message: 'You are not signed in. Sign in to delete your account.',
            });
            return;
          }

          setBusy(true);
          try {
            await requestAccountDeletion();
            clearLocalState();
            await logout();
            setDeleteResult({ ok: true, message: 'Your account deletion request was submitted.' });
            router.replace('/(tabs)/home');
          } catch (e) {
            setDeleteResult({
              ok: false,
              message: 'Unable to delete your account right now. Please try again later.',
            });
          } finally {
            setBusy(false);
          }
        }}
      />

      <StatusDialog
        visible={busy}
        variant="loading"
        title="Deleting account"
        message="Please wait while we submit your request."
        primaryLabel="Hide"
        onPrimary={() => setBusy(false)}
        onClose={() => setBusy(false)}
      />

      <StatusDialog
        visible={deleteResult?.ok === true}
        variant="success"
        title="Request submitted"
        message={deleteResult?.message ?? ''}
        primaryLabel="OK"
        onPrimary={() => setDeleteResult(null)}
        onClose={() => setDeleteResult(null)}
      />

      <StatusDialog
        visible={deleteResult?.ok === false}
        variant="error"
        title="Couldn't delete account"
        message={deleteResult?.message ?? ''}
        primaryLabel="OK"
        onPrimary={() => setDeleteResult(null)}
        onClose={() => setDeleteResult(null)}
      />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.white,
  },
  content: {
    paddingHorizontal: theme.moderateScale(20),
    paddingTop: theme.moderateScale(10),
    paddingBottom: theme.moderateScale(24),
    flex: 1,
  },
  title: {
    fontSize: theme.moderateScale(22),
    fontWeight: '700',
    color: theme.colors.ink,
    marginBottom: theme.moderateScale(18),
  },
  sectionTitle: {
    fontSize: theme.moderateScale(14),
    fontWeight: '700',
    color: theme.colors.ink,
    marginBottom: theme.moderateScale(8),
  },
  sectionList: {
    marginBottom: theme.moderateScale(18),
  },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: theme.moderateScale(14),
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.borderSoft,
  },
  rowLabel: {
    fontSize: theme.moderateScale(13),
    color: theme.colors.ink,
  },
  rowRight: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.moderateScale(10),
  },
  rowValue: {
    fontSize: theme.moderateScale(12),
    color: theme.colors.muted,
  },
  deleteRow: {
    marginTop: theme.moderateScale(8),
  },
  deleteText: {
    fontSize: theme.moderateScale(12),
    color: theme.colors.pink,
  },
  logoutText: {
    color: theme.colors.rose,
  },
  footer: {
    marginTop: theme.moderateScale(18),
  },
  footerTitle: {
    fontSize: theme.moderateScale(14),
    fontWeight: '700',
    color: theme.colors.ink,
  },
  footerBody: {
    marginTop: theme.moderateScale(4),
    fontSize: theme.moderateScale(11),
    color: theme.colors.muted,
  },
});
