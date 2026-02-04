import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useMemo, useState, useEffect } from 'react';
import {
  FlatList,
  Image,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from '@/src/utils/responsiveStyleSheet';
import { Linking } from 'react-native';
import { useCart } from '@/lib/cartStore';
import { useOrders } from '@/lib/ordersStore';
import { usePaymentMethods } from '@/lib/paymentMethodsStore';
import { theme } from '@/src/theme';
import { usePreferences } from '@/src/store/preferencesStore';
import { StatusDialog } from '@/src/overlays/StatusDialog';
import { useToast } from '@/src/overlays/ToastProvider';
import { previewCheckout, confirmCheckout, CheckoutPreview } from '@/src/api/checkout';
import { initKorapay } from '@/src/api/payments';
import { useAuth } from '@/lib/authStore';

const countryToCode = (value: string) => {
  if (!value) return 'US';
  if (value.length === 2) return value.toUpperCase();
  const normalized = value.toLowerCase();
  const map: Record<string, string> = {
    'united states': 'US',
    'united states of america': 'US',
    usa: 'US',
    'united kingdom': 'GB',
    uk: 'GB',
    canada: 'CA',
    nigeria: 'NG',
    ghana: 'GH',
    kenya: 'KE',
    'cote d’ivoire': 'CI',
    "cote d'ivoire": 'CI',
    'ivory coast': 'CI',
  };
  return map[normalized] ?? 'US';
};
export default function PaymentScreen() {
  const { selectedCount, items, selectedIds, refreshCart, applyCoupon, removeCoupon, summary } = useCart();
  const { addOrderFromCart } = useOrders();
  const { selectedCard } = usePaymentMethods();
  const { state } = usePreferences();
  const { user } = useAuth();
  const { show } = useToast();
  const selectedItems = useMemo(
    () => items.filter((item) => selectedIds.includes(item.productId)),
    [items, selectedIds]
  );
  const cartSignature = useMemo(
    () => items.map((item) => `${item.productId}:${item.quantity}`).join('|'),
    [items]
  );
  const [voucherCode, setVoucherCode] = useState('');
  const [voucherApplied, setVoucherApplied] = useState(false);
  const [preview, setPreview] = useState<CheckoutPreview | null>(null);
  const [loadingPreview, setLoadingPreview] = useState(false);
  const [activeDialog, setActiveDialog] = useState<
    | null
    | { type: 'processing' }
    | { type: 'success'; number: string }
    | { type: 'failed'; title: string; message: string }
    | { type: 'voucher' }
  >(null);
  const canCheckout = selectedCount > 0 && selectedCount === items.length;
  const totals = preview ?? {
    subtotal: summary.subtotal,
    shipping: summary.shipping,
    discount: summary.discount,
    tax: summary.tax,
    total: summary.total,
    currency: summary.currency,
  };

  useEffect(() => {
    let active = true;
    if (items.length === 0) {
      setPreview(null);
      return;
    }
    setLoadingPreview(true);
    previewCheckout({
      email: user?.email,
      country: countryToCode(state.shippingAddress.country),
    })
      .then((result) => {
        if (!active) return;
        setPreview(result);
      })
      .catch((err: any) => {
        if (!active) return;
        show({ type: 'error', message: err?.message ?? 'Unable to load checkout preview.' });
      })
      .finally(() => {
        if (!active) return;
        setLoadingPreview(false);
      });

    return () => {
      active = false;
    };
  }, [cartSignature, summary.discount, state.shippingAddress.country, user?.email, show]);

  useEffect(() => {
    if (voucherCode.trim().length === 0 && voucherApplied) {
      removeCoupon();
      setVoucherApplied(false);
    }
  }, [voucherApplied, voucherCode, removeCoupon]);

  useEffect(() => {
    if (summary.coupon && !voucherApplied) {
      setVoucherApplied(true);
    }
    if (!summary.coupon && voucherApplied) {
      setVoucherApplied(false);
    }
  }, [summary.coupon, voucherApplied]);



  return (
    <View style={styles.container}>
      <ScrollView style={styles.scroll} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.headerRow}>
          <Text style={styles.title}>Payment</Text>
          <Pressable style={styles.iconButton} onPress={() => router.push('/shipping')}>
            <Feather name="map-pin" size={16} color={theme.colors.inkDark} />
          </Pressable>
        </View>

        <Pressable style={styles.card} onPress={() => router.push('/checkout/address')}>
          <View>
            <Text style={styles.cardTitle}>Shipping address</Text>
            <Text style={styles.cardBody}>
              {state.shippingAddress.address}, {state.shippingAddress.city}
            </Text>
          </View>
          <Feather name="chevron-right" size={16} color={theme.colors.inkDark} />
        </Pressable>

        <Pressable style={styles.card} onPress={() => router.push('/payment/methods')}>
          <View>
            <Text style={styles.cardTitle}>Payment method</Text>
            <Text style={styles.cardBody}>
              {selectedCard
                ? `${selectedCard.brand === 'visa' ? 'Visa' : 'Mastercard'} •••• ${selectedCard.last4}`
                : 'Choose a payment method'}
            </Text>
          </View>
          <Feather name="chevron-right" size={16} color={theme.colors.inkDark} />
        </Pressable>

        <View style={styles.card}>
          <View style={styles.voucherRow}>
            <TextInput
              style={styles.voucherInput}
              placeholder="Add voucher"
              placeholderTextColor="#c7c7c7"
              value={voucherCode}
              onChangeText={(value) => {
                setVoucherCode(value);
                setVoucherApplied(false);
              }}
            />
            <Pressable
              style={styles.voucherButton}
              onPress={async () => {
                const next = voucherCode.trim();
                if (!next) {
                  show({ type: 'error', message: 'Enter a voucher code to apply.' });
                  return;
                }
                const ok = await applyCoupon(next);
                if (ok) {
                  setVoucherApplied(true);
                  setActiveDialog({ type: 'voucher' });
                } else {
                  show({ type: 'error', message: 'Unable to apply voucher.' });
                }
              }}
            >
              <Text style={styles.voucherButtonText}>Apply</Text>
            </Pressable>
          </View>
          {voucherApplied ? (
            <Text style={styles.voucherApplied}>Voucher applied.</Text>
          ) : null}
        </View>

        <View style={styles.orderCard}>
          <Text style={styles.summaryTitle}>Order items</Text>
          {selectedItems.length === 0 ? (
            <Text style={styles.orderEmpty}>No items selected. Go back to cart to choose items.</Text>
          ) : (
            <FlatList
              horizontal
              data={selectedItems}
              keyExtractor={(item) => item.id}
              showsHorizontalScrollIndicator={false}
              contentContainerStyle={styles.orderList}
              renderItem={({ item }) => (
                <View style={styles.orderCardItem}>
                  {item.image ? (
                    <Image source={{ uri: item.image }} style={styles.orderImage} />
                  ) : (
                    <View style={styles.orderImageFallback}>
                      <Text style={styles.orderImageFallbackText}>
                        {item.name.slice(0, 1).toUpperCase()}
                      </Text>
                    </View>
                  )}
                  <Text style={styles.orderName} numberOfLines={2}>
                    {item.name}
                  </Text>
                <View style={styles.orderMetaRow}>
                  <Text style={styles.orderMeta}>Qty {item.quantity}</Text>
                  <Text style={styles.orderPrice}>${(item.price * item.quantity).toFixed(2)}</Text>
                </View>
              </View>
            )}
          />
          )}
        </View>

        <View style={styles.summaryCard}>
          <Text style={styles.summaryTitle}>Order summary</Text>
          <View style={styles.summaryRow}>
            <Text style={styles.summaryLabel}>Subtotal</Text>
            <Text style={styles.summaryValue}>${totals.subtotal.toFixed(2)}</Text>
          </View>
          <View style={styles.summaryRow}>
            <Text style={styles.summaryLabel}>Shipping</Text>
            <Text style={styles.summaryValue}>${totals.shipping.toFixed(2)}</Text>
          </View>
          {totals.discount > 0 ? (
            <View style={styles.summaryRow}>
              <Text style={styles.summaryLabel}>Discount</Text>
              <Text style={styles.summaryValue}>-${totals.discount.toFixed(2)}</Text>
            </View>
          ) : null}
          {totals.tax > 0 ? (
            <View style={styles.summaryRow}>
              <Text style={styles.summaryLabel}>Tax</Text>
              <Text style={styles.summaryValue}>${totals.tax.toFixed(2)}</Text>
            </View>
          ) : null}
          <View style={styles.summaryRow}>
            <Text style={styles.summaryTotalLabel}>Total</Text>
            <Text style={styles.summaryTotal}>${totals.total.toFixed(2)}</Text>
          </View>
        </View>
      </ScrollView>

      <View style={styles.bottomBar}>
        <View>
          <Text style={styles.totalLabel}>Total</Text>
          <Text style={styles.totalValue}>${totals.total.toFixed(2)}</Text>
        </View>
        <Pressable
          style={[styles.payButton, !canCheckout ? styles.payButtonDisabled : null]}
          onPress={() => {
            if (!canCheckout) {
              show({
                type: 'error',
                message:
                  items.length > 0
                    ? 'Select all items in your cart to continue checkout.'
                    : 'Your cart is empty.',
              });
              return;
            }
            if (!selectedCard) {
              setActiveDialog({
                type: 'failed',
                title: 'Choose payment method',
                message: 'Select a card to proceed with payment.',
              });
              return;
            }
            if (!user?.email) {
              show({ type: 'error', message: 'Add an email address to continue.' });
              return;
            }
            if (!state.shippingAddress.phone && !user?.phone) {
              show({ type: 'error', message: 'Add a phone number to continue.' });
              return;
            }
            if (!state.shippingAddress.address || !state.shippingAddress.city) {
              show({ type: 'error', message: 'Add a shipping address to continue.' });
              return;
            }
            const minimum = preview?.minimum_cart_requirement as { passes?: boolean; message?: string } | undefined;
            if (minimum && minimum.passes === false) {
              show({ type: 'error', message: minimum.message ?? 'Minimum cart requirement not met.' });
              return;
            }
            setActiveDialog({ type: 'processing' });
          }}
          disabled={!canCheckout || loadingPreview}
        >
          <Text style={styles.payText}>{loadingPreview ? 'Loading…' : 'Pay now'}</Text>
        </Pressable>
      </View>

      <StatusDialog
        visible={activeDialog?.type === 'processing'}
        variant="loading"
        title="Payment in progress"
        message="We are processing your payment, please wait a moment."
        primaryLabel="Continue"
        onPrimary={async () => {
          if (!user?.email) {
            show({ type: 'error', message: 'Add an email address to continue.' });
            setActiveDialog(null);
            return;
          }
          const fullName = state.shippingAddress.name || user?.name || 'Customer';
          const [firstName, ...rest] = fullName.split(' ');
          const lastName = rest.join(' ').trim();
          try {
            const confirm = await confirmCheckout({
              email: user.email,
              phone: state.shippingAddress.phone || user?.phone || '',
              first_name: firstName,
              last_name: lastName || undefined,
              line1: state.shippingAddress.address,
              city: state.shippingAddress.city,
              postal_code: state.shippingAddress.postcode || undefined,
              country: countryToCode(state.shippingAddress.country),
              payment_method: 'korapay',
            });
            if (!confirm.order_number) {
              throw new Error('Checkout failed. Missing order number.');
            }
            const init = await initKorapay({
              order_number: confirm.order_number,
              customer: { email: user.email, name: fullName },
              amount: totals.total,
              currency: totals.currency,
            }).catch(() => null);
            if (init?.checkout_url) {
              Linking.openURL(init.checkout_url).catch(() => {});
            }
            addOrderFromCart({
              items: selectedItems,
              shipping: totals.shipping,
              discount: totals.discount,
              number: confirm.order_number,
            });
            await refreshCart();
            setActiveDialog({ type: 'success', number: confirm.order_number });
          } catch (err: any) {
            setActiveDialog({
              type: 'failed',
              title: 'Payment failed',
              message: err?.message ?? 'Unable to place the order.',
            });
          }
        }}
        secondaryLabel="Cancel payment"
        onSecondary={() => setActiveDialog(null)}
        onClose={() => setActiveDialog(null)}
      />

      <StatusDialog
        visible={activeDialog?.type === 'voucher'}
        variant="success"
        title="Voucher added"
        message="Your discount has been applied to the order."
        primaryLabel="OK"
        onPrimary={() => setActiveDialog(null)}
        onClose={() => setActiveDialog(null)}
      />

      <StatusDialog
        visible={activeDialog?.type === 'failed'}
        variant="error"
        title={activeDialog?.type === 'failed' ? activeDialog.title : ''}
        message={activeDialog?.type === 'failed' ? activeDialog.message : ''}
        primaryLabel="Choose method"
        onPrimary={() => {
          setActiveDialog(null);
          router.push('/payment/methods');
        }}
        secondaryLabel="Close"
        onSecondary={() => setActiveDialog(null)}
        onClose={() => setActiveDialog(null)}
      />

      <StatusDialog
        visible={activeDialog?.type === 'success'}
        variant="success"
        title="Payment confirmed"
        message="Your payment was confirmed. We’ll start processing your order."
        primaryLabel="View order"
        onPrimary={() => {
          if (activeDialog?.type !== 'success') return;
          const number = activeDialog.number;
          setActiveDialog(null);
          router.replace({ pathname: '/orders/[number]', params: { number } });
        }}
        secondaryLabel="Back to home"
        onSecondary={() => {
          setActiveDialog(null);
          router.replace('/(tabs)/home');
        }}
        onClose={() => setActiveDialog(null)}
      />
    </View>
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
    paddingTop: 10,
    paddingBottom: 120,
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 16,
  },
  title: {
    fontSize: 24,
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
  card: {
    marginBottom: 12,
    padding: 16,
    borderRadius: 18,
    backgroundColor: theme.colors.sand,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  cardTitle: {
    fontSize: 14,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  cardBody: {
    marginTop: 6,
    fontSize: 12,
    color: theme.colors.inkDark,
  },
  voucherRow: {
    marginTop: 10,
    flexDirection: 'row',
    gap: 10,
  },
  voucherInput: {
    flex: 1,
    height: 44,
    borderRadius: 14,
    backgroundColor: theme.colors.white,
    paddingHorizontal: 12,
    fontSize: 12,
    color: theme.colors.inkDark,
  },
  voucherButton: {
    paddingHorizontal: 16,
    borderRadius: 14,
    backgroundColor: theme.colors.sun,
    justifyContent: 'center',
  },
  voucherButtonText: {
    fontSize: 12,
    fontWeight: '700',
    color: theme.colors.gray200,
  },
  voucherApplied: {
    marginTop: 8,
    fontSize: 11,
    color: theme.colors.primary,
    fontWeight: '600',
  },
  summaryCard: {
    marginTop: 10,
    borderRadius: 18,
    backgroundColor: theme.colors.gray100,
    padding: 16,
  },
  orderCard: {
    marginTop: 10,
    borderRadius: 18,
    backgroundColor: theme.colors.sand,
    padding: 16,
  },
  orderEmpty: {
    marginTop: 8,
    fontSize: 12,
    color: theme.colors.mutedDark,
  },
  orderList: {
    marginTop: 10,
    gap: 12,
  },
  orderCardItem: {
    width: 140,
    borderRadius: 16,
    backgroundColor: theme.colors.white,
    padding: 12,
    borderWidth: 1,
    borderColor: theme.colors.borderSoft,
  },
  orderImage: {
    width: '100%',
    height: 90,
    borderRadius: 14,
    backgroundColor: theme.colors.gray200,
  },
  orderImageFallback: {
    width: '100%',
    height: 90,
    borderRadius: 14,
    backgroundColor: theme.colors.gray200,
    alignItems: 'center',
    justifyContent: 'center',
  },
  orderImageFallbackText: {
    fontSize: 18,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  orderName: {
    marginTop: 8,
    fontSize: 12,
    fontWeight: '600',
    color: theme.colors.inkDark,
  },
  orderMetaRow: {
    marginTop: 6,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  orderMeta: {
    fontSize: 11,
    color: theme.colors.mutedDark,
  },
  orderPrice: {
    fontSize: 12,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  summaryTitle: {
    fontSize: 14,
    fontWeight: '700',
    color: theme.colors.inkDark,
    marginBottom: 12,
  },
  summaryRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  summaryLabel: {
    fontSize: 12,
    color: theme.colors.inkDark,
  },
  summaryValue: {
    fontSize: 12,
    color: theme.colors.inkDark,
    fontWeight: '600',
  },
  summaryTotalLabel: {
    fontSize: 14,
    color: theme.colors.black,
    fontWeight: '700',
  },
  summaryTotal: {
    fontSize: 14,
    color: theme.colors.black,
    fontWeight: '700',
  },
  bottomBar: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
    height: 84,
    borderTopWidth: 1,
    borderTopColor: theme.colors.gray300,
    backgroundColor: theme.colors.white,
    paddingHorizontal: 20,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  totalLabel: {
    fontSize: 12,
    color: theme.colors.inkDark,
  },
  totalValue: {
    fontSize: 16,
    color: theme.colors.black,
    fontWeight: '700',
    marginTop: 4,
  },
  payButton: {
    paddingHorizontal: 24,
    paddingVertical: 12,
    borderRadius: 24,
    backgroundColor: theme.colors.sun,
  },
  payButtonDisabled: {
    opacity: 0.5,
  },
  payText: {
    color: theme.colors.gray200,
    fontSize: 14,
    fontWeight: '700',
  },
});
