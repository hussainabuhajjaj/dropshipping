import { Feather } from '@expo/vector-icons';
import { router, Stack } from 'expo-router';
import { useEffect, useState } from 'react';
import { Image, Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { Skeleton } from '@/src/components/ui/Skeleton';
import type { Product } from '@/src/types/storefront';
import { fetchProducts, fetchProductsBySlugs } from '@/src/api/catalog';
import { useCart } from '@/lib/cartStore';
import { useWishlist } from '@/lib/wishlistStore';
import { useRecentlyViewed } from '@/lib/recentlyViewedStore';
import { theme } from '@/src/theme';
import { useToast } from '@/src/overlays/ToastProvider';
export default function WishlistScreen() {
  const { items: wishlisted, loading: loadingWish, error, remove } = useWishlist();
  const { slugs: recentSlugs } = useRecentlyViewed();
  const { addItem } = useCart();
  const { show } = useToast();
  const recentSize = 46;
  const recentRadius = 23;
  const itemImageWidth = 86;
  const itemImageHeight = 96;
  const itemImageRadius = 18;
  const popularImageHeight = 130;
  const [recentItems, setRecentItems] = useState<Product[]>([]);
  const [popular, setPopular] = useState<Product[]>([]);
  const [loadingRecent, setLoadingRecent] = useState(true);
  const [loadingPopular, setLoadingPopular] = useState(true);
  const [localError, setLocalError] = useState<string | null>(null);

  useEffect(() => {
    let active = true;
    setLoadingRecent(true);
    if (recentSlugs.length === 0) {
      setRecentItems([]);
      setLoadingRecent(false);
      return () => {
        active = false;
      };
    }
    fetchProductsBySlugs(recentSlugs.slice(0, 5))
      .then((items) => {
        if (!active) return;
        setRecentItems(items);
      })
      .catch((err: any) => {
        if (!active) return;
        show({ type: 'error', message: err?.message ?? 'Unable to load recent items.' });
        setRecentItems([]);
      })
      .finally(() => {
        if (active) setLoadingRecent(false);
      });

    return () => {
      active = false;
    };
  }, [recentSlugs, show]);

  useEffect(() => {
    let active = true;
    setLoadingPopular(true);
    fetchProducts({ per_page: 6 })
      .then(({ items }) => {
        if (!active) return;
        setPopular(items);
      })
      .catch((err: any) => {
        if (!active) return;
        show({ type: 'error', message: err?.message ?? 'Unable to load popular items.' });
        setPopular([]);
      })
      .finally(() => {
        if (active) setLoadingPopular(false);
      });

    return () => {
      active = false;
    };
  }, [show]);

  const showEmpty = !loadingWish && wishlisted.length === 0;
  const combinedError = localError ?? error;

  return (
    <>
      <Stack.Screen options={{ title: 'Wish List' }} />
      <ScrollView style={styles.container} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.headerRow}>
          <Text style={styles.title}>Wishlist</Text>
        </View>

      <View style={styles.sectionRow}>
        <Text style={styles.sectionTitle}>Recently viewed</Text>
        <Pressable style={styles.circleButton} onPress={() => router.push('/account/recent')}>
          <Feather name="arrow-right" size={14} color={theme.colors.inkDark} />
        </Pressable>
      </View>
      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={styles.recentRow}
      >
        {loadingRecent
          ? [0, 1, 2, 3, 4].map((index) => (
              <View key={`recent-skel-${index}`} style={styles.recentSkeleton}>
                <Skeleton width={recentSize} height={recentSize} radius={recentRadius} />
              </View>
            ))
          : recentItems.map((product) => (
              <Pressable
                key={product.id}
                onPress={() => router.push(`/products/${product.slug}`)}
              >
                {product.image ? (
                  <Image source={{ uri: product.image }} style={styles.recentAvatar} />
                ) : (
                  <View style={styles.recentAvatarFallback}>
                    <Text style={styles.recentAvatarText}>{product.name.slice(0, 1).toUpperCase()}</Text>
                  </View>
                )}
              </Pressable>
            ))}
      </ScrollView>

      {loadingWish ? (
        <View style={styles.list}>
          {[0, 1, 2].map((index) => (
            <View key={`wish-skel-${index}`} style={styles.listItem}>
              <View style={styles.itemImageWrap}>
                <Skeleton width={itemImageWidth} height={itemImageHeight} radius={itemImageRadius} />
              </View>
              <View style={styles.itemInfo}>
                <Skeleton width="80%" height={12} />
                <Skeleton width="50%" height={10} style={styles.skeletonGap} />
                <View style={styles.metaRow}>
                  <Skeleton width={36} height={18} radius={9} />
                  <Skeleton width={36} height={18} radius={9} />
                </View>
              </View>
              <Skeleton width={36} height={36} radius={18} />
            </View>
          ))}
        </View>
      ) : showEmpty ? (
        <View style={styles.emptyWrap}>
          <View style={styles.emptyCircle}>
            <Feather name="heart" size={24} color={theme.colors.inkDark} />
          </View>
          {combinedError ? <Text style={styles.emptyText}>{combinedError}</Text> : null}
        </View>
      ) : (
        <View style={styles.list}>
          {wishlisted.map((product, index) => {
            const compareAt = product.compareAt ?? undefined;
            return (
              <Pressable
                key={product.id}
                style={styles.listItem}
                onPress={() => router.push(`/products/${product.slug}`)}
              >
                <View style={styles.itemImageWrap}>
                  {product.image ? (
                    <Image source={{ uri: product.image }} style={styles.itemImage} />
                  ) : (
                    <View style={styles.itemImageFallback}>
                      <Text style={styles.itemImageText}>{product.name.slice(0, 1).toUpperCase()}</Text>
                    </View>
                  )}
                  <Pressable
                    style={styles.trashButton}
                    onPress={async (event) => {
                      event.stopPropagation();
                      const result = await remove(product.id);
                      if (!result.ok) {
                        const message = result.message ?? 'Unable to remove item from wishlist.';
                        setLocalError(message);
                        show({ type: 'error', message });
                      } else {
                        setLocalError(null);
                      }
                    }}
                  >
                    <Feather name="trash-2" size={12} color={theme.colors.inkDark} />
                  </Pressable>
                </View>
                <View style={styles.itemInfo}>
                  <Text style={styles.itemName} numberOfLines={2}>
                    {product.name}
                  </Text>
                  <View style={styles.priceRow}>
                    {compareAt ? <Text style={styles.comparePrice}>${compareAt.toFixed(2)}</Text> : null}
                    <Text style={styles.itemPrice}>${product.price.toFixed(2)}</Text>
                  </View>
                  <View style={styles.metaRow}>
                    <View style={styles.metaChip}>
                      <Text style={styles.metaText}>Pink</Text>
                    </View>
                    <View style={styles.metaChip}>
                      <Text style={styles.metaText}>M</Text>
                    </View>
                  </View>
                </View>
                <Pressable
                  style={styles.bagButton}
                  onPress={(event) => {
                    event.stopPropagation();
                    addItem(product);
                  }}
                >
                  <Feather name="shopping-bag" size={16} color={theme.colors.inkDark} />
                </Pressable>
              </Pressable>
            );
          })}
        </View>
      )}

      {showEmpty ? (
        <View style={styles.popularSection}>
          <View style={styles.popularHeader}>
            <Text style={styles.sectionTitle}>Most Popular</Text>
            <Pressable style={styles.seeAll} onPress={() => router.push('/products')}>
              <Text style={styles.seeAllText}>See All</Text>
              <View style={styles.seeAllIcon}>
                <Feather name="arrow-right" size={12} color={theme.colors.inkDark} />
              </View>
            </Pressable>
          </View>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.popularRow}>
            {loadingPopular
              ? [0, 1, 2, 3].map((index) => (
                  <View key={`popular-skel-${index}`} style={styles.popularCard}>
                    <Skeleton
                      width="100%"
                      height={popularImageHeight}
                      radius={16}
                    />
                    <View style={styles.popularMeta}>
                      <Skeleton width={28} height={10} />
                      <Skeleton width={32} height={10} />
                    </View>
                  </View>
                ))
              : popular.map((product) => (
                  <Pressable
                    key={product.id}
                    style={styles.popularCard}
                    onPress={() => router.push(`/products/${product.slug}`)}
                  >
                    {product.image ? (
                      <Image source={{ uri: product.image }} style={styles.popularImage} />
                    ) : (
                      <View style={styles.popularImageFallback} />
                    )}
                    <View style={styles.popularMeta}>
                      <View style={styles.popularStat}>
                        <Text style={styles.popularStatText}>{product.reviews}</Text>
                        <Feather name="heart" size={10} color={theme.colors.inkDark} />
                      </View>
                      <Text style={styles.popularBadge}>{product.badge ?? 'New'}</Text>
                    </View>
                  </Pressable>
                ))}
          </ScrollView>
        </View>
      ) : null}
      </ScrollView>
    </>
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
    paddingBottom: 28,
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 12,
  },
  title: {
    fontSize: 24,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  sectionRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 12,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  circleButton: {
    width: 28,
    height: 28,
    borderRadius: 14,
    backgroundColor: theme.colors.sun,
    alignItems: 'center',
    justifyContent: 'center',
  },
  recentRow: {
    gap: 10,
    paddingBottom: 4,
    marginBottom: 10,
  },
  recentSkeleton: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  recentAvatar: {
    width: 46,
    height: 46,
    borderRadius: 23,
    borderWidth: 2,
    borderColor: theme.colors.white,
    backgroundColor: theme.colors.sand,
    overflow: 'hidden',
  },
  recentAvatarFallback: {
    width: 46,
    height: 46,
    borderRadius: 23,
    borderWidth: 2,
    borderColor: theme.colors.white,
    backgroundColor: theme.colors.sand,
    alignItems: 'center',
    justifyContent: 'center',
  },
  recentAvatarText: {
    fontSize: 14,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  loader: {
    marginTop: 24,
  },
  list: {
    gap: 14,
  },
  listItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  itemImageWrap: {
    width: 86,
    height: 96,
    borderRadius: 18,
    backgroundColor: theme.colors.sand,
    overflow: 'hidden',
  },
  itemImage: {
    width: '100%',
    height: '100%',
  },
  itemImageFallback: {
    width: '100%',
    height: '100%',
    backgroundColor: theme.colors.sand,
    alignItems: 'center',
    justifyContent: 'center',
  },
  itemImageText: {
    fontSize: 18,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  trashButton: {
    position: 'absolute',
    left: 6,
    bottom: 6,
    width: 26,
    height: 26,
    borderRadius: 13,
    backgroundColor: theme.colors.orange,
    alignItems: 'center',
    justifyContent: 'center',
  },
  itemInfo: {
    flex: 1,
  },
  itemName: {
    fontSize: 12,
    color: theme.colors.inkDark,
    marginBottom: 6,
  },
  priceRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: 8,
  },
  comparePrice: {
    fontSize: 12,
    color: '#a0a0a0',
    textDecorationLine: 'line-through',
  },
  itemPrice: {
    fontSize: 14,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  metaRow: {
    flexDirection: 'row',
    gap: 8,
  },
  skeletonGap: {
    marginTop: 6,
  },
  metaChip: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 10,
    backgroundColor: theme.colors.sand,
  },
  metaText: {
    fontSize: 11,
    color: theme.colors.inkDark,
    fontWeight: '600',
  },
  bagButton: {
    width: 32,
    height: 32,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: theme.colors.sun,
    alignItems: 'center',
    justifyContent: 'center',
  },
  emptyWrap: {
    alignItems: 'center',
    marginTop: 30,
    marginBottom: 16,
  },
  emptyCircle: {
    width: 84,
    height: 84,
    borderRadius: 42,
    backgroundColor: theme.colors.white,
    borderWidth: 1,
    borderColor: theme.colors.sand,
    alignItems: 'center',
    justifyContent: 'center',
    shadowColor: theme.colors.black,
    shadowOpacity: 0.08,
    shadowRadius: 12,
    shadowOffset: { width: 0, height: 6 },
    elevation: 3,
  },
  emptyText: {
    marginTop: 10,
    fontSize: 12,
    color: theme.colors.mutedDark,
  },
  popularSection: {
    marginTop: 8,
  },
  popularHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 12,
  },
  seeAll: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  seeAllText: {
    fontSize: 12,
    color: theme.colors.inkDark,
  },
  seeAllIcon: {
    width: 22,
    height: 22,
    borderRadius: 11,
    backgroundColor: theme.colors.sun,
    alignItems: 'center',
    justifyContent: 'center',
  },
  popularRow: {
    gap: 12,
    paddingBottom: 8,
  },
  popularCard: {
    width: 110,
  },
  popularImage: {
    width: '100%',
    height: 130,
    borderRadius: 16,
    backgroundColor: theme.colors.sand,
  },
  popularImageFallback: {
    width: '100%',
    height: 130,
    borderRadius: 16,
    backgroundColor: theme.colors.sand,
  },
  popularMeta: {
    marginTop: 6,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  popularStat: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
  },
  popularStatText: {
    fontSize: 11,
    color: theme.colors.inkDark,
    fontWeight: '600',
  },
  popularBadge: {
    fontSize: 11,
    color: theme.colors.inkDark,
    fontWeight: '600',
  },
});
