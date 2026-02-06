import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useState } from 'react';
import Constants from 'expo-constants';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Pressable, StyleSheet, View } from 'react-native';
import { Text } from '@/src/components/i18n/Text';
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
import { useTranslations } from '@/src/i18n/TranslationsProvider';

type RowItem = {
  label: string;
  value?: string;
  onPress?: () => void;
  danger?: boolean;
};

export default function SettingsScreen() {
  const [showDeleteDialog, setShowDeleteDialog] = useState(false);
  const { state } = usePreferences();
  const { status, logout } = useAuth();
  const { t } = useTranslations();
  const appVersion = Constants.expoConfig?.version ?? '1.0.0';
  const cart = useCart();
  const wishlist = useWishlist();
  const orders = useOrders();
  const paymentMethods = usePaymentMethods();
  const addresses = useAddresses();

  const personalItems: RowItem[] = [
    { label: t('Profile', 'Profile'), onPress: () => router.push('/settings/profile') },
    { label: t('Shipping Address', 'Shipping Address'), onPress: () => router.push('/shipping') },
    { label: t('Payment methods', 'Payment methods'), onPress: () => router.push('/settings/payments') },
  ];

  const shopItems: RowItem[] = [
    { label: t('Country', 'Country'), value: 'Vietnam', onPress: () => router.push('/preferences/country') },
    { label: t('Currency', 'Currency'), value: '$ USD', onPress: () => router.push('/preferences/currency') },
    { label: t('Sizes', 'Sizes'), value: 'UK', onPress: () => router.push('/preferences/sizes') },
    { label: t('Terms & Privacy', 'Terms & Privacy'), onPress: () => router.push('/legal/terms') },
  ];

  const accountItems: RowItem[] = [
    { label: t('Language', 'Language'), value: state.language, onPress: () => router.push('/preferences/language') },
    { label: t('About Simbazu', 'About Simbazu'), onPress: () => router.push('/about') },
  ];

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
        <Text style={styles.title}>{t('Settings', 'Settings')}</Text>

        <Text style={styles.sectionTitle}>{t('Personal', 'Personal')}</Text>
        <View style={styles.sectionList}>
          {personalItems.map((item) => (
            <Pressable key={item.label} style={styles.row} onPress={item.onPress}>
              <Text style={styles.rowLabel}>{item.label}</Text>
              <Feather name="chevron-right" size={theme.moderateScale(16)} color={theme.colors.mutedLight} />
            </Pressable>
          ))}
        </View>

        <Text style={styles.sectionTitle}>{t('Shop', 'Shop')}</Text>
        <View style={styles.sectionList}>
          {shopItems.map((item) => (
            <Pressable key={item.label} style={styles.row} onPress={item.onPress}>
              <Text style={styles.rowLabel}>{item.label}</Text>
              <View style={styles.rowRight}>
                {item.label === t('Country', 'Country') ? (
                  <Text style={styles.rowValue}>{state.country}</Text>
                ) : item.label === t('Currency', 'Currency') ? (
                  <Text style={styles.rowValue}>{state.currency}</Text>
                ) : item.label === t('Sizes', 'Sizes') ? (
                  <Text style={styles.rowValue}>{state.size}</Text>
                ) : item.value ? (
                  <Text style={styles.rowValue}>{item.value}</Text>
                ) : null}
                <Feather name="chevron-right" size={theme.moderateScale(16)} color={theme.colors.mutedLight} />
              </View>
            </Pressable>
          ))}
        </View>

        <Text style={styles.sectionTitle}>{t('Account', 'Account')}</Text>
        <View style={styles.sectionList}>
          {accountItems.map((item) => (
            <Pressable key={item.label} style={styles.row} onPress={item.onPress}>
              <Text style={styles.rowLabel}>{item.label}</Text>
              <View style={styles.rowRight}>
                {item.value ? <Text style={styles.rowValue}>{item.value}</Text> : null}
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
            <Text style={[styles.rowLabel, styles.logoutText]}>{t('Log out', 'Log out')}</Text>
          </Pressable>
        </View>

        <Pressable onPress={() => setShowDeleteDialog(true)} style={styles.deleteRow}>
          <Text style={styles.deleteText}>{t('Delete My Account', 'Delete My Account')}</Text>
        </Pressable>

        <View style={styles.footer}>
          <Text style={styles.footerTitle}>Simbazu</Text>
          <Text style={styles.footerBody}>
            {t('Version :version', 'Version :version', { version: appVersion })}
          </Text>
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
              message: t(
                'You are not signed in. Sign in to delete your account.',
                'You are not signed in. Sign in to delete your account.'
              ),
            });
            return;
          }

          setBusy(true);
          try {
            await requestAccountDeletion();
            clearLocalState();
            await logout();
            setDeleteResult({
              ok: true,
              message: t(
                'Your account deletion request was submitted.',
                'Your account deletion request was submitted.'
              ),
            });
            router.replace('/(tabs)/home');
          } catch (e) {
            setDeleteResult({
              ok: false,
              message: t(
                'Unable to delete your account right now. Please try again later.',
                'Unable to delete your account right now. Please try again later.'
              ),
            });
          } finally {
            setBusy(false);
          }
        }}
      />

      <StatusDialog
        visible={busy}
        variant="loading"
        title={t('Deleting account', 'Deleting account')}
        message={t('Please wait while we submit your request.', 'Please wait while we submit your request.')}
        primaryLabel={t('Hide', 'Hide')}
        onPrimary={() => setBusy(false)}
        onClose={() => setBusy(false)}
      />

      <StatusDialog
        visible={deleteResult?.ok === true}
        variant="success"
        title={t('Request submitted', 'Request submitted')}
        message={deleteResult?.message ?? ''}
        primaryLabel={t('OK', 'OK')}
        onPrimary={() => setDeleteResult(null)}
        onClose={() => setDeleteResult(null)}
      />

      <StatusDialog
        visible={deleteResult?.ok === false}
        variant="error"
        title={t("Couldn't delete account", "Couldn't delete account")}
        message={deleteResult?.message ?? ''}
        primaryLabel={t('OK', 'OK')}
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
