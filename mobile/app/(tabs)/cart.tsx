import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useEffect, useMemo, useState } from 'react';
import { FlatList, Image, Pressable, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { type CartItem, useCart } from '@/lib/cartStore';
import { fetchProducts } from '@/src/api/catalog';
import { formatCurrency } from '@/src/lib/formatCurrency';
import { theme } from '@/src/theme';
import { RemoveCartItemDialog } from '@/src/overlays/RemoveCartItemDialog';
import { useRecentlyViewed } from '@/lib/recentlyViewedStore';
import { ProductTile } from '@/src/components/products/ProductTile';
import { Skeleton } from '@/src/components/ui/Skeleton';
import { useToast } from '@/src/overlays/ToastProvider';
import type { Product } from '@/src/types/storefront';

type CartRow = CartItem | { id: string; skeleton: true };
export default function CartScreen() {
  const {
    items,
    subtotal,
    updateQty,
    removeItem,
    selectedIds,
    toggleSelection,
    selectedCount,
    addItem,
    loading,
    error,
    refreshCart,
    summary,
  } = useCart();
  const { slugs } = useRecentlyViewed();
  const { show } = useToast();
  const insets = useSafeAreaInsets();
  const shipping = summary.shipping;
  const tax = summary.tax;
  const discount = summary.discount;
  const isAllSelected = items.length > 0 && selectedCount === items.length;
  const selectedSubtotal = useMemo(
    () =>
      items
        .filter((item) => selectedIds.includes(item.productId))
        .reduce((sum, item) => sum + item.price * item.quantity, 0),
    [items, selectedIds]
  );
  const selectedTotal = isAllSelected
    ? summary.total
    : Math.max(selectedSubtotal, 0);
  const showRecentOnEmpty = slugs.length > 0;
  const canCheckout = selectedCount > 0 && selectedCount === items.length && !loading;
  const [recentProducts, setRecentProducts] = useState<Product[]>([]);
  const [pendingRemove, setPendingRemove] = useState<{ id: string; name: string } | null>(null);
  const skeletonRows = useMemo(() => Array.from({ length: 3 }, (_, index) => index), []);
  const listData: CartRow[] = useMemo(() => {
    if (loading && items.length === 0) {
      return skeletonRows.map((row) => ({ id: `cart-skeleton-${row}`, skeleton: true as const }));
    }
    return items;
  }, [items, loading, skeletonRows]);

  useEffect(() => {
    refreshCart();
  }, []);

  useEffect(() => {
    if (error) {
      show({ type: 'error', message: error });
    }
  }, [error, show]);

  useEffect(() => {
    let active = true;
    fetchProducts()
      .then(({ items: data }) => {
        if (!active) return;
        if (slugs.length === 0) {
          setRecentProducts(data.slice(0, 8));
          return;
        }
        const bySlug = new Map(data.map((product) => [product.slug, product]));
        const mapped = slugs
          .map((slug) => bySlug.get(slug))
          .filter((product): product is Product => Boolean(product));
        setRecentProducts(mapped.length > 0 ? mapped : data.slice(0, 8));
      })
      .catch(() => {});

    return () => {
      active = false;
    };
  }, [slugs]);
  const confirmRemove = (name: string, productId: string) => {
    setPendingRemove({ id: productId, name });
  };
  const recentSection = (
    <View style={styles.recentSection}>
      <Text style={styles.recentTitle}>Recently viewed</Text>
      <FlatList
        horizontal
        data={recentProducts}
        keyExtractor={(product) => product.id}
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={styles.recentList}
        renderItem={({ item }) => (
            <View style={styles.recentItem}>
            <ProductTile
              product={item}
              mode="carousel"
              onPress={() => router.push({ pathname: '/modal', params: { slug: item.slug } })}
              onAdd={() => addItem(item)}
            />
          </View>
        )}
      />
    </View>
  );

  return (
    <View style={styles.container}>
      <FlatList
        style={styles.scroll}
        data={listData}
        keyExtractor={(item) => item.id}
        contentContainerStyle={[
          styles.content,
          styles.list,
          {
            paddingTop: theme.moderateScale(10) + insets.top,
            paddingBottom: theme.moderateScale(120) + insets.bottom,
          },
        ]}
        showsVerticalScrollIndicator={false}
        ListEmptyComponent={
          !loading ? (
            <View style={styles.emptyCard}>
              <Feather name="shopping-bag" size={36} color={theme.colors.inkDark} />
              <Text style={styles.emptyTitle}>Your cart is empty</Text>
              <Text style={styles.emptyBody}>Find something you love and add it here.</Text>
              <Pressable style={styles.primaryButton} onPress={() => router.push('/products')}>
                <Text style={styles.primaryText}>Go shopping</Text>
              </Pressable>
            </View>
          ) : null
        }
        ListFooterComponent={
          <View style={styles.footer}>
            {items.length > 0 ? (
              <View style={styles.summaryCard}>
                <View style={styles.summaryRow}>
                  <Text style={styles.summaryLabel}>Subtotal</Text>
                  <Text style={styles.summaryValue}>
                    {formatCurrency(isAllSelected ? subtotal : selectedSubtotal, summary.currency)}
                  </Text>
                </View>
                <View style={styles.summaryRow}>
                  <Text style={styles.summaryLabel}>Shipping</Text>
                  <Text style={styles.summaryValue}>
                    {formatCurrency(isAllSelected ? shipping : 0, summary.currency)}
                  </Text>
                </View>
                {discount > 0 && isAllSelected ? (
                  <View style={styles.summaryRow}>
                    <Text style={styles.summaryLabel}>Discount</Text>
                    <Text style={styles.summaryValue}>{formatCurrency(-discount, summary.currency)}</Text>
                  </View>
                ) : null}
                {tax > 0 && isAllSelected ? (
                  <View style={styles.summaryRow}>
                    <Text style={styles.summaryLabel}>Tax</Text>
                    <Text style={styles.summaryValue}>{formatCurrency(tax, summary.currency)}</Text>
                  </View>
                ) : null}
                <View style={styles.summaryRow}>
                  <Text style={styles.summaryTotalLabel}>Total</Text>
                  <Text style={styles.summaryTotal}>{formatCurrency(selectedTotal, summary.currency)}</Text>
                </View>
              </View>
            ) : null}

            {items.length > 0 || showRecentOnEmpty ? recentSection : null}
          </View>
        }
        renderItem={({ item }) => {
          if ('skeleton' in item) {
            return (
              <View style={styles.itemCard}>
                <Skeleton height={theme.moderateScale(18)} width={theme.moderateScale(18)} radius={9} />
                <Skeleton height={theme.moderateScale(90)} width={theme.moderateScale(90)} radius={14} />
                <View style={styles.itemInfo}>
                  <Skeleton height={theme.moderateScale(12)} radius={theme.moderateScale(6)} width="80%" />
                  <Skeleton height={theme.moderateScale(12)} radius={theme.moderateScale(6)} width="40%" />
                  <View style={styles.qtyRow}>
                    <Skeleton height={theme.moderateScale(28)} width={theme.moderateScale(28)} radius={14} />
                    <Skeleton
                      height={theme.moderateScale(12)}
                      radius={theme.moderateScale(6)}
                      width={theme.moderateScale(24)}
                    />
                    <Skeleton height={theme.moderateScale(28)} width={theme.moderateScale(28)} radius={14} />
                  </View>
                </View>
              </View>
            );
          }

          return (
            <View style={styles.itemCard}>
              <Pressable
                style={styles.checkWrap}
                onPress={() => toggleSelection(item.productId)}
                accessibilityRole="checkbox"
                accessibilityState={{ checked: selectedIds.includes(item.productId) }}
              >
                <Feather
                  name={selectedIds.includes(item.productId) ? 'check-circle' : 'circle'}
                  size={18}
                  color={selectedIds.includes(item.productId) ? theme.colors.primary : theme.colors.mutedLight}
                />
              </Pressable>
              <Pressable
                style={styles.itemImageWrap}
                onPress={() => {
                  if (item.slug) {
                    router.push(`/products/${item.slug}`);
                  }
                }}
              >
                {item.image ? (
                  <Image source={{ uri: item.image }} style={styles.itemImage} />
                ) : (
                  <View style={styles.itemImageFallback}>
                    <Text style={styles.itemImageFallbackText}>{item.name.slice(0, 1).toUpperCase()}</Text>
                  </View>
                )}
              </Pressable>
              <View style={styles.itemInfo}>
                <Pressable
                  onPress={() => {
                    if (item.slug) {
                      router.push(`/products/${item.slug}`);
                    }
                  }}
                >
                  <Text style={styles.itemName} numberOfLines={2}>
                    {item.name}
                  </Text>
                </Pressable>
                <Text style={styles.itemPrice}>{formatCurrency(item.price, item.currency, summary.currency)}</Text>
                <View style={styles.qtyRow}>
                  <Pressable
                    style={styles.qtyButton}
                    onPress={() => updateQty(item.productId, Math.max(1, item.quantity - 1))}
                    disabled={loading}
                  >
                    <Feather name="minus" size={14} color={theme.colors.inkDark} />
                  </Pressable>
                  <Text style={styles.qtyValue}>{item.quantity}</Text>
                  <Pressable
                    style={styles.qtyButton}
                    onPress={() => updateQty(item.productId, item.quantity + 1)}
                    disabled={loading}
                  >
                    <Feather name="plus" size={14} color={theme.colors.inkDark} />
                  </Pressable>
                  <Pressable
                    style={styles.removeButton}
                    onPress={() => confirmRemove(item.name, item.productId)}
                    disabled={loading}
                  >
                    <Feather name="trash-2" size={14} color={theme.colors.inkDark} />
                  </Pressable>
                </View>
              </View>
            </View>
          );
        }}
      />

      {items.length ? (
        <View
          style={[
            styles.bottomBar,
            {
              height: theme.moderateScale(84) + insets.bottom,
              paddingBottom: insets.bottom,
            },
          ]}
        >
          <View>
            <Text style={styles.totalLabel}>Total</Text>
            <Text style={styles.totalValue}>{formatCurrency(selectedTotal, summary.currency)}</Text>
          </View>
          <Pressable
            style={[styles.checkoutButton, !canCheckout ? styles.checkoutButtonDisabled : null]}
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
              router.push('/checkout');
            }}
            disabled={!canCheckout}
          >
            <Text style={styles.checkoutText}>Checkout</Text>
          </Pressable>
        </View>
      ) : null}
      <RemoveCartItemDialog
        visible={Boolean(pendingRemove)}
        itemName={pendingRemove?.name}
        onCancel={() => setPendingRemove(null)}
        onConfirm={() => {
          if (pendingRemove) {
            removeItem(pendingRemove.id);
          }
          setPendingRemove(null);
        }}
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
  title: {
    fontSize: 24,
    fontWeight: '700',
    color: theme.colors.inkDark,
    marginBottom: 12,
  },
  emptyCard: {
    alignItems: 'center',
    paddingVertical: 40,
    paddingHorizontal: 20,
    borderRadius: 20,
    backgroundColor: theme.colors.sand,
    marginTop: 30,
  },
  emptyTitle: {
    marginTop: 16,
    fontSize: 16,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  emptyBody: {
    marginTop: 8,
    fontSize: 13,
    color: theme.colors.inkDark,
    textAlign: 'center',
  },
  primaryButton: {
    marginTop: 18,
    backgroundColor: theme.colors.sun,
    borderRadius: 24,
    paddingHorizontal: 24,
    paddingVertical: 12,
  },
  primaryText: {
    color: theme.colors.gray200,
    fontSize: 14,
    fontWeight: '700',
  },
  list: {
    gap: 16,
  },
  footer: {
    gap: 16,
  },
  itemCard: {
    flexDirection: 'row',
    gap: 12,
    borderRadius: 16,
    padding: 12,
    backgroundColor: theme.colors.white,
    borderWidth: 1,
    borderColor: theme.colors.gray250,
  },
  checkWrap: {
    paddingTop: 4,
  },
  itemImageWrap: {
    borderRadius: 14,
    overflow: 'hidden',
  },
  itemImage: {
    width: 90,
    height: 90,
    borderRadius: 14,
    backgroundColor: theme.colors.gray200,
  },
  itemImageFallback: {
    width: 90,
    height: 90,
    borderRadius: 14,
    backgroundColor: theme.colors.gray200,
    alignItems: 'center',
    justifyContent: 'center',
  },
  itemImageFallbackText: {
    fontSize: 20,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  itemInfo: {
    flex: 1,
  },
  itemName: {
    fontSize: 13,
    color: theme.colors.black,
  },
  itemPrice: {
    marginTop: 6,
    fontSize: 14,
    fontWeight: '600',
    color: theme.colors.inkDark,
  },
  qtyRow: {
    marginTop: 10,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  qtyButton: {
    width: 28,
    height: 28,
    borderRadius: 14,
    backgroundColor: theme.colors.gray100,
    alignItems: 'center',
    justifyContent: 'center',
  },
  qtyValue: {
    fontSize: 13,
    color: theme.colors.inkDark,
    fontWeight: '600',
  },
  removeButton: {
    marginLeft: 'auto',
    width: 28,
    height: 28,
    borderRadius: 14,
    backgroundColor: theme.colors.dangerSoft,
    alignItems: 'center',
    justifyContent: 'center',
  },
  summaryCard: {
    borderRadius: 16,
    padding: 16,
    backgroundColor: theme.colors.gray100,
  },
  summaryRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  summaryLabel: {
    fontSize: 13,
    color: theme.colors.inkDark,
  },
  summaryValue: {
    fontSize: 13,
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
  recentSection: {
    marginTop: 8,
  },
  recentTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: theme.colors.inkDark,
    marginBottom: 12,
  },
  recentList: {
    gap: 12,
    paddingBottom: 8,
  },
  recentItem: {
    width: theme.moderateScale(170),
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
  checkoutButton: {
    paddingHorizontal: 24,
    paddingVertical: 12,
    borderRadius: 24,
    backgroundColor: theme.colors.orange,
  },
  checkoutButtonDisabled: {
    opacity: 0.5,
  },
  checkoutText: {
    color: theme.colors.gray200,
    fontSize: 14,
    fontWeight: '700',
  },
});
