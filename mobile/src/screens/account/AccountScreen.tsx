import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { FlatList, Image, Linking, Pressable, ScrollView, StyleSheet, View, useWindowDimensions } from 'react-native';
import { Text } from '@/src/components/i18n/Text';
import { CategoryCard } from '@/src/components/ui/CategoryCard';
import { Chip } from '@/src/components/ui/Chip';
import { CircleIconButton } from '@/src/components/ui/CircleIconButton';
import { ProductCard } from '@/src/components/ui/ProductCard';
import { PopularCard } from '@/src/components/ui/PopularCard';
import { SeeAllAction } from '@/src/components/ui/SeeAllAction';
import { Skeleton } from '@/src/components/ui/Skeleton';
import { theme } from '@/src/theme';
import { useAuth } from '@/lib/authStore';
import { meRequest } from '@/src/api/auth';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { fetchHome, fetchProductsBySlugs } from '@/src/api/catalog';
import { useToast } from '@/src/overlays/ToastProvider';
import type { Category, HomePayload, Product } from '@/src/types/storefront';
import { useRecentlyViewed } from '@/lib/recentlyViewedStore';
import { RefreshControl } from 'react-native';
import { usePullToRefresh } from '@/src/hooks/usePullToRefresh';
import { formatCurrency } from '@/src/lib/formatCurrency';
import { usePreferences } from '@/src/store/preferencesStore';
import { fetchAnnouncements } from '@/src/api/announcements';
import type { AnnouncementItem } from '@/src/types/announcements';

const orderTabs = ['To Pay', 'To Receive', 'To Review'];
const stories = Array.from({ length: 5 }, (_, index) => `story-${index}`);

const mostPopular = [
  { id: 'popular-1', label: 'New', count: '1780' },
  { id: 'popular-2', label: 'Sale', count: '1780' },
  { id: 'popular-3', label: 'Hot', count: '1780' },
];

type ProductRowItem = Product | { id: string; skeleton: true };
type CategoryGridItem = Category | { id: string; skeleton: true };
type TopProduct = { id: string; label: string; slug?: string | null };

const topProductsFallback: TopProduct[] = [
  { id: 'top-1', label: 'Bags', slug: null },
  { id: 'top-2', label: 'Watch', slug: null },
  { id: 'top-3', label: 'Hoodies', slug: null },
  { id: 'top-4', label: 'Shoes', slug: null },
  { id: 'top-5', label: 'Accessories', slug: null },
];

export default function AccountScreen() {
  const { width } = useWindowDimensions();
  const { show } = useToast();
  const { state } = usePreferences();
  const { status, user, updateUser } = useAuth();
  const hasLoadedMe = useRef(false);
  const homeRequestId = useRef(0);
  const recentRequestId = useRef(0);
  const announcementRequestId = useRef(0);
  const { slugs: recentSlugs } = useRecentlyViewed();
  const [home, setHome] = useState<HomePayload | null>(null);
  const [loadingHome, setLoadingHome] = useState(true);
  const [recentItems, setRecentItems] = useState<Product[]>([]);
  const [loadingRecent, setLoadingRecent] = useState(true);
  const [announcement, setAnnouncement] = useState<AnnouncementItem | null>(null);
  const [loadingAnnouncement, setLoadingAnnouncement] = useState(true);
  const gridGap = theme.moderateScale(12);
  const horizontalPadding = theme.moderateScale(20);
  const gridItemWidth = (width - horizontalPadding * 2 - gridGap) / 2;
  const flashItemWidth = (width - horizontalPadding * 2 - gridGap * 2) / 3;
  const recentSize = theme.moderateScale(48);
  const recentRadius = theme.moderateScale(24);

  const newItems = useMemo(() => {
    const source = home?.recommended?.length ? home.recommended : home?.trending ?? [];
    return source.slice(0, 4);
  }, [home]);

  const flashItems = useMemo(() => {
    return (home?.flashDeals ?? []).slice(0, 6);
  }, [home]);

  const justForYou = useMemo(() => {
    const source = home?.trending?.length ? home.trending : home?.recommended ?? [];
    return source.slice(0, 4);
  }, [home]);

  const categories = useMemo(() => {
    return home?.categories ?? [];
  }, [home]);

  const topProducts = useMemo(() => {
    if (categories.length === 0) return topProductsFallback;
    return categories.slice(0, 5).map((category, index) => ({
      id: `${category.id ?? index}`,
      label: category.name,
      slug: category.slug ?? null,
    }));
  }, [categories]);

  const loadMe = useCallback(
    async (force?: boolean) => {
      if (status !== 'authed') {
        hasLoadedMe.current = false;
        return;
      }
      if (!force && hasLoadedMe.current) return;
      hasLoadedMe.current = true;
      try {
        const me = await meRequest();
        const fullName = `${me.first_name ?? ''} ${me.last_name ?? ''}`.trim();
        updateUser({
          name: (me.name ?? fullName) || 'Customer',
          email: me.email ?? undefined,
          avatar: me.avatar ?? null,
          phone: me.phone ?? null,
        });
      } catch {
        // ignore profile fetch errors
      }
    },
    [status, updateUser]
  );

  useEffect(() => {
    loadMe();
  }, [loadMe]);

  const loadHome = useCallback(async () => {
    const id = ++homeRequestId.current;
    setLoadingHome(true);
    try {
      const payload = await fetchHome();
      if (id !== homeRequestId.current) return;
      setHome(payload);
    } catch (err: any) {
      if (id !== homeRequestId.current) return;
      const message = err?.message ?? 'Unable to load account feed.';
      show({ type: 'error', message });
      setHome(null);
    } finally {
      if (id === homeRequestId.current) setLoadingHome(false);
    }
  }, [show]);

  const loadRecent = useCallback(async () => {
    const id = ++recentRequestId.current;
    setLoadingRecent(true);
    if (recentSlugs.length === 0) {
      setRecentItems([]);
      setLoadingRecent(false);
      return;
    }
    try {
      const items = await fetchProductsBySlugs(recentSlugs.slice(0, 5));
      if (id !== recentRequestId.current) return;
      setRecentItems(items);
    } catch (err: any) {
      if (id !== recentRequestId.current) return;
      show({ type: 'error', message: err?.message ?? 'Unable to load recently viewed items.' });
      setRecentItems([]);
    } finally {
      if (id === recentRequestId.current) setLoadingRecent(false);
    }
  }, [recentSlugs, show]);

  useEffect(() => {
    loadHome();
    return () => {
      homeRequestId.current += 1;
    };
  }, [loadHome]);

  useEffect(() => {
    loadRecent();
    return () => {
      recentRequestId.current += 1;
    };
  }, [loadRecent]);

  const loadAnnouncement = useCallback(async () => {
    const id = ++announcementRequestId.current;
    setLoadingAnnouncement(true);
    try {
      const { items } = await fetchAnnouncements({ per_page: 1 });
      if (id !== announcementRequestId.current) return;
      setAnnouncement(items[0] ?? null);
    } catch {
      if (id !== announcementRequestId.current) return;
      setAnnouncement(null);
    } finally {
      if (id === announcementRequestId.current) setLoadingAnnouncement(false);
    }
  }, []);

  useEffect(() => {
    loadAnnouncement();
    return () => {
      announcementRequestId.current += 1;
    };
  }, [loadAnnouncement]);

  const { refreshing, onRefresh } = usePullToRefresh(async () => {
    await Promise.all([loadHome(), loadRecent(), loadMe(true), loadAnnouncement()]);
  });

  const openHref = (href?: string | null) => {
    if (!href) {
      router.push('/account/notifications');
      return;
    }
    if (href.startsWith('http://') || href.startsWith('https://')) {
      Linking.openURL(href).catch(() => {});
      return;
    }
    router.push(href as any);
  };

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView
        contentContainerStyle={styles.content}
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={onRefresh}
            tintColor={theme.colors.primary}
            colors={[theme.colors.primary]}
          />
        }
      >
        <View style={styles.headerRow}>
          <Pressable onPress={() => router.push('/account/full-profile')}>
            <View style={styles.avatar}>
              {user?.avatar ? (
                <Image source={{ uri: user.avatar }} style={styles.avatarImage} />
              ) : null}
            </View>
          </Pressable>
          <Pressable style={styles.activityPill} onPress={() => router.push('/orders')}>
            <Text style={styles.activityText}>My Activity</Text>
          </Pressable>
          <View style={styles.iconRow}>
            <CircleIconButton
              icon="mail"
              size={theme.moderateScale(28)}
              variant="outlined"
              onPress={() => router.push('/support')}
            />
            <CircleIconButton
              icon="bell"
              size={theme.moderateScale(28)}
              variant="outlined"
              onPress={() => router.push('/account/notifications')}
            />
            <CircleIconButton
              icon="settings"
              size={theme.moderateScale(28)}
              variant="outlined"
              onPress={() => router.push('/settings')}
            />
            <CircleIconButton
              icon="user-plus"
              size={theme.moderateScale(28)}
              variant="outlined"
              onPress={() => router.push('/account/wishlist')}
              accessibilityLabel="Wishlist"
            />
          </View>
        </View>

        {status === 'guest' ? (
          <View style={styles.authCard}>
            <Text style={styles.authTitle}>Sign in to continue</Text>
            <Text style={styles.authBody}>Access your orders, wishlist, and saved settings.</Text>
            <View style={styles.authRow}>
              <Pressable style={styles.authButton} onPress={() => router.push('/auth/login')}>
                <Text style={styles.authButtonText}>Login</Text>
              </Pressable>
              <Pressable style={styles.authButtonOutline} onPress={() => router.push('/auth/register')}>
                <Text style={styles.authButtonOutlineText}>Create account</Text>
              </Pressable>
            </View>
          </View>
        ) : null}

        {status === 'authed' ? (
          <Text style={styles.greeting}>Hello, {user?.name ?? 'Customer'}!</Text>
        ) : null}

        {loadingAnnouncement ? (
          <View style={styles.announcement}>
            <View style={{ flex: 1 }}>
              <Skeleton width={theme.moderateScale(130)} height={theme.moderateScale(14)} radius={theme.moderateScale(7)} />
              <View style={{ height: theme.moderateScale(8) }} />
              <Skeleton width={theme.moderateScale(240)} height={theme.moderateScale(12)} radius={theme.moderateScale(6)} />
            </View>
          </View>
        ) : announcement ? (
          <View style={styles.announcement}>
            <View>
              <Text style={styles.announcementTitle}>{announcement.title || 'Announcement'}</Text>
              <Text style={styles.announcementBody}>{announcement.body}</Text>
            </View>
            <CircleIconButton
              icon="arrow-right"
              size={theme.moderateScale(26)}
              variant="filled"
              onPress={() => openHref(announcement.actionHref)}
            />
          </View>
        ) : null}

        <Text style={styles.sectionTitle}>Recently viewed</Text>
        <FlatList<ProductRowItem>
          horizontal
          data={
            loadingRecent
              ? Array.from({ length: 5 }, (_, index) => ({ id: `sk-${index}`, skeleton: true as const }))
              : recentItems
          }
          keyExtractor={(item) => item.id}
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={styles.recentRow}
          renderItem={({ item }) => {
            if ('skeleton' in item) {
              return (
                <View style={styles.recentAvatar}>
                  <Skeleton width={recentSize} height={recentSize} radius={recentRadius} />
                </View>
              );
            }
            return (
              <Pressable
                style={styles.recentAvatar}
                onPress={() => router.push('/account/recent')}
              >
                {item.image ? (
                  <Image source={{ uri: item.image }} style={styles.recentAvatarImage} />
                ) : (
                  <View style={styles.recentAvatarFallback}>
                    <Text style={styles.recentAvatarText}>{item.name.slice(0, 1).toUpperCase()}</Text>
                  </View>
                )}
              </Pressable>
            );
          }}
        />

        <Text style={styles.sectionTitle}>My Orders</Text>
        <View style={styles.orderRow}>
          {orderTabs.map((item) => (
            <View key={item} style={styles.orderChip}>
              <Chip
                label={item}
                active={item === 'To Receive'}
                onPress={() => {
                  if (item === 'To Receive') {
                    router.push('/orders/to-receive');
                    return;
                  }
                  if (item === 'To Review') {
                    router.push('/orders/review-option');
                    return;
                  }
                  router.push('/payment/methods');
                }}
              />
              {item === 'To Receive' ? <View style={styles.orderDot} /> : null}
            </View>
          ))}
        </View>

        <View style={styles.sectionHeader}>
          <Text style={styles.sectionTitle}>Stories</Text>
        </View>
        <FlatList
          horizontal
          data={stories}
          keyExtractor={(item) => item}
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={styles.storyRow}
          renderItem={() => (
            <Pressable style={styles.storyCard} onPress={() => router.push('/stories')}>
              <View style={styles.storyLive}>
                <Text style={styles.storyLiveText}>Live</Text>
              </View>
            </Pressable>
          )}
        />

        <View style={styles.sectionHeader}>
          <Text style={styles.sectionTitle}>New Items</Text>
          <SeeAllAction onPress={() => router.push('/products')} />
        </View>
        <FlatList<ProductRowItem>
          horizontal
          data={
            loadingHome
              ? Array.from({ length: 3 }, (_, index) => ({ id: `sk-${index}`, skeleton: true as const }))
              : newItems
          }
          keyExtractor={(item) => item.id}
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={styles.horizontalRow}
          renderItem={({ item }) => {
            if ('skeleton' in item) {
              return (
                <ProductCard
                  loading
                  width={theme.moderateScale(140)}
                  imageHeight={theme.moderateScale(130)}
                />
              );
            }
            return (
              <ProductCard
                title={item.name}
                price={formatCurrency(item.price, item.currency, state.currency)}
                width={theme.moderateScale(140)}
                imageHeight={theme.moderateScale(130)}
                image={item.image ? { uri: item.image } : undefined}
                onPress={() => router.push(`/products/${item.slug}`)}
              />
            );
          }}
        />

        <View style={styles.sectionHeader}>
          <Text style={styles.sectionTitle}>Most Popular</Text>
          <SeeAllAction onPress={() => router.push('/products')} />
        </View>
        <FlatList
          horizontal
          data={mostPopular}
          keyExtractor={(item) => item.id}
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={styles.popularRow}
          renderItem={({ item }) => (
            <PopularCard
              label={item.label}
              count={item.count}
              loading={loadingHome}
              onPress={() => router.push('/products')}
            />
          )}
        />

        <View style={styles.sectionHeader}>
          <Text style={styles.sectionTitle}>Categories</Text>
          <SeeAllAction onPress={() => router.push('/(tabs)/categories')} />
        </View>
        <FlatList<CategoryGridItem>
          data={
            loadingHome
              ? Array.from({ length: 4 }, (_, index) => ({ id: `sk-${index}`, skeleton: true as const }))
              : categories
          }
          keyExtractor={(item) => item.id}
          numColumns={2}
          scrollEnabled={false}
          columnWrapperStyle={styles.column}
          renderItem={({ item }) => (
            <CategoryCard
              loading={'skeleton' in item ? true : loadingHome}
              label={'skeleton' in item ? '' : item.name}
              count={'skeleton' in item ? 0 : item.product_count ?? item.count}
              previews={'skeleton' in item ? [] : item.subcategory_previews}
              width={gridItemWidth}
              onPress={
                'skeleton' in item || loadingHome
                  ? undefined
                  : () => {
                      const category = item.slug ?? item.name;
                      if (!category) return;
                      router.push({
                        pathname: '/products',
                        params: {
                          category,
                          title: item.name || undefined,
                        },
                      });
                    }
              }
            />
          )}
        />

        <View style={styles.sectionHeader}>
          <Text style={styles.sectionTitle}>Flash Sale</Text>
          <View style={styles.timer}>
            <Feather name="clock" size={theme.moderateScale(12)} color={theme.colors.primary} />
            <Text style={styles.timerText}>00</Text>
            <Text style={styles.timerText}>36</Text>
            <Text style={styles.timerText}>58</Text>
          </View>
        </View>
        <FlatList<ProductRowItem>
          data={
            loadingHome
              ? Array.from({ length: 6 }, (_, index) => ({ id: `sk-${index}`, skeleton: true as const }))
              : flashItems
          }
          keyExtractor={(item) => item.id}
          numColumns={3}
          scrollEnabled={false}
          columnWrapperStyle={styles.flashColumn}
          renderItem={({ item }) => {
            if ('skeleton' in item) {
              return (
                <ProductCard
                  loading
                  width={flashItemWidth}
                  imageHeight={theme.moderateScale(90)}
                />
              );
            }
            const hasCompare = item.compareAt && item.compareAt > item.price;
            const discount = hasCompare
              ? Math.round(100 - (item.price / (item.compareAt as number)) * 100)
              : null;
            return (
              <ProductCard
                title={item.name}
                price={formatCurrency(item.price, item.currency, state.currency)}
                oldPrice={
                  hasCompare
                    ? formatCurrency(item.compareAt as number, item.currency, state.currency)
                    : undefined
                }
                badge={discount ? `-${discount}%` : item.badge ?? undefined}
                width={flashItemWidth}
                imageHeight={theme.moderateScale(90)}
                image={item.image ? { uri: item.image } : undefined}
                onPress={() => router.push(`/products/${item.slug}`)}
              />
            );
          }}
        />

        <Text style={[styles.sectionTitle, styles.sectionSpacing]}>Top Products</Text>
        <FlatList<TopProduct>
          horizontal
          data={topProducts}
          keyExtractor={(item) => item.id}
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={styles.topRow}
          renderItem={({ item }) => (
            <Pressable
              style={styles.topProduct}
              onPress={() => {
                const category = item.slug || item.label;
                if (!category) return;
                router.push({
                  pathname: '/products',
                  params: {
                    category,
                    title: item.label || undefined,
                  },
                });
              }}
            >
              <View style={styles.topProductIcon} />
              <Text style={styles.topProductLabel}>{item.label}</Text>
            </Pressable>
          )}
        />

        <View style={styles.sectionHeader}>
          <View style={styles.justRow}>
            <Text style={styles.sectionTitle}>Just For You</Text>
            <Feather name="star" size={theme.moderateScale(14)} color={theme.colors.primary} />
          </View>
        </View>
        <FlatList<ProductRowItem>
          data={
            loadingHome
              ? Array.from({ length: 4 }, (_, index) => ({ id: `sk-${index}`, skeleton: true as const }))
              : justForYou
          }
          keyExtractor={(item) => item.id}
          numColumns={2}
          scrollEnabled={false}
          columnWrapperStyle={styles.column}
          renderItem={({ item }) => {
            if ('skeleton' in item) {
              return (
                <ProductCard
                  loading
                  width={gridItemWidth}
                  imageHeight={theme.moderateScale(160)}
                />
              );
            }
            return (
              <ProductCard
                title={item.name}
                price={formatCurrency(item.price, item.currency, state.currency)}
                width={gridItemWidth}
                imageHeight={theme.moderateScale(160)}
                image={item.image ? { uri: item.image } : undefined}
                onPress={() => router.push(`/products/${item.slug}`)}
              />
            );
          }}
        />
      </ScrollView>
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
    paddingBottom: theme.moderateScale(28),
    paddingTop: theme.moderateScale(8),
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.moderateScale(10),
    marginBottom: theme.moderateScale(10),
  },
  iconRow: {
    flexDirection: 'row-reverse',
    gap: theme.moderateScale(8),
    flex: 1,
    justifyContent: 'flex-end',
  },
  avatar: {
    width: theme.moderateScale(32),
    height: theme.moderateScale(32),
    borderRadius: theme.moderateScale(16),
    backgroundColor: theme.colors.primarySoft,
    overflow: 'hidden',
  },
  avatarImage: {
    width: '100%',
    height: '100%',
  },
  activityPill: {
    paddingHorizontal: theme.moderateScale(14),
    paddingVertical: theme.moderateScale(6),
    borderRadius: theme.moderateScale(16),
    backgroundColor: theme.colors.primary,
  },
  activityText: {
    fontSize: theme.moderateScale(12),
    color: theme.colors.white,
    fontWeight: '600',
  },
  toggle: {
    width: theme.moderateScale(34),
    height: theme.moderateScale(18),
    borderRadius: theme.moderateScale(9),
    backgroundColor: theme.colors.primarySoftAlt,
    marginLeft: 'auto',
  },
  greeting: {
    fontSize: theme.moderateScale(22),
    fontWeight: '700',
    color: theme.colors.ink,
    marginBottom: theme.moderateScale(12),
  },
  authCard: {
    marginTop: theme.moderateScale(6),
    padding: theme.moderateScale(14),
    borderRadius: theme.moderateScale(16),
    backgroundColor: theme.colors.primarySoftLight,
    marginBottom: theme.moderateScale(14),
  },
  authTitle: {
    fontSize: theme.moderateScale(14),
    fontWeight: '700',
    color: theme.colors.ink,
  },
  authBody: {
    marginTop: theme.moderateScale(6),
    fontSize: theme.moderateScale(12),
    color: theme.colors.muted,
  },
  authRow: {
    marginTop: theme.moderateScale(12),
    flexDirection: 'row',
    gap: theme.moderateScale(10),
  },
  authButton: {
    flex: 1,
    backgroundColor: theme.colors.primary,
    borderRadius: theme.moderateScale(18),
    paddingVertical: theme.moderateScale(10),
    alignItems: 'center',
  },
  authButtonText: {
    fontSize: theme.moderateScale(12),
    fontWeight: '700',
    color: theme.colors.white,
  },
  authButtonOutline: {
    flex: 1,
    borderWidth: 1,
    borderColor: theme.colors.primary,
    borderRadius: theme.moderateScale(18),
    paddingVertical: theme.moderateScale(10),
    alignItems: 'center',
    backgroundColor: theme.colors.white,
  },
  authButtonOutlineText: {
    fontSize: theme.moderateScale(12),
    fontWeight: '700',
    color: theme.colors.primary,
  },
  announcement: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.moderateScale(12),
    padding: theme.moderateScale(14),
    borderRadius: theme.moderateScale(16),
    backgroundColor: theme.colors.primarySoftLight,
    marginBottom: theme.moderateScale(16),
  },
  announcementTitle: {
    fontSize: theme.moderateScale(12),
    fontWeight: '700',
    color: theme.colors.ink,
  },
  announcementBody: {
    marginTop: theme.moderateScale(4),
    fontSize: theme.moderateScale(11),
    color: theme.colors.muted,
    maxWidth: theme.moderateScale(220),
  },
  sectionTitle: {
    fontSize: theme.moderateScale(16),
    fontWeight: '700',
    color: theme.colors.ink,
    marginTop: theme.moderateScale(16),
  },
  sectionSpacing: {
    marginTop: theme.moderateScale(20),
  },
  recentRow: {
    paddingTop: theme.moderateScale(10),
    gap: theme.moderateScale(10),
  },
  recentAvatar: {
    width: theme.moderateScale(48),
    height: theme.moderateScale(48),
    borderRadius: theme.moderateScale(24),
    backgroundColor: theme.colors.primarySoft,
    overflow: 'hidden',
  },
  recentAvatarImage: {
    width: '100%',
    height: '100%',
    borderRadius: theme.moderateScale(24),
  },
  recentAvatarFallback: {
    width: '100%',
    height: '100%',
    borderRadius: theme.moderateScale(24),
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: theme.colors.primarySoft,
  },
  recentAvatarText: {
    fontSize: theme.moderateScale(14),
    fontWeight: '700',
    color: theme.colors.ink,
  },
  orderRow: {
    flexDirection: 'row',
    gap: theme.moderateScale(10),
    marginTop: theme.moderateScale(10),
  },
  orderChip: {
    position: 'relative',
  },
  orderDot: {
    position: 'absolute',
    right: theme.moderateScale(-2),
    top: theme.moderateScale(-2),
    width: theme.moderateScale(8),
    height: theme.moderateScale(8),
    borderRadius: theme.moderateScale(4),
    backgroundColor: '#21c26f',
  },
  sectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginTop: theme.moderateScale(16),
  },
  storyRow: {
    paddingTop: theme.moderateScale(10),
    gap: theme.moderateScale(12),
  },
  storyCard: {
    width: theme.moderateScale(80),
    height: theme.moderateScale(110),
    borderRadius: theme.moderateScale(16),
    backgroundColor: theme.colors.primarySoftAlt,
    justifyContent: 'flex-start',
  },
  storyLive: {
    backgroundColor: '#21c26f',
    borderRadius: theme.moderateScale(10),
    paddingHorizontal: theme.moderateScale(6),
    paddingVertical: theme.moderateScale(2),
    alignSelf: 'flex-start',
    margin: theme.moderateScale(6),
  },
  storyLiveText: {
    fontSize: theme.moderateScale(10),
    color: theme.colors.white,
    fontWeight: '700',
  },
  horizontalRow: {
    paddingTop: theme.moderateScale(10),
    gap: theme.moderateScale(12),
  },
  popularRow: {
    paddingTop: theme.moderateScale(10),
    gap: theme.moderateScale(12),
  },
  column: {
    justifyContent: 'space-between',
    marginBottom: theme.moderateScale(12),
  },
  timer: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.moderateScale(6),
  },
  timerText: {
    fontSize: theme.moderateScale(12),
    color: theme.colors.primary,
    fontWeight: '600',
  },
  flashColumn: {
    justifyContent: 'space-between',
    marginBottom: theme.moderateScale(12),
  },
  topRow: {
    paddingTop: theme.moderateScale(10),
    gap: theme.moderateScale(12),
  },
  topProduct: {
    width: theme.moderateScale(70),
    alignItems: 'center',
  },
  topProductIcon: {
    width: theme.moderateScale(56),
    height: theme.moderateScale(56),
    borderRadius: theme.moderateScale(28),
    backgroundColor: theme.colors.primarySoft,
    marginBottom: theme.moderateScale(6),
  },
  topProductLabel: {
    fontSize: theme.moderateScale(11),
    color: theme.colors.ink,
  },
  justRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.moderateScale(6),
  },
});
