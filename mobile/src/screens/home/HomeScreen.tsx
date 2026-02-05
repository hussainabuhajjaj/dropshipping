import { Feather } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { SafeAreaView } from 'react-native-safe-area-context';
import { FlatList, Image, Linking, Pressable, RefreshControl, ScrollView, StyleSheet, View, useWindowDimensions } from 'react-native';
import { Text } from '@/src/components/i18n/Text';
import { CategoryCard } from '@/src/components/ui/CategoryCard';
import { PopularCard } from '@/src/components/ui/PopularCard';
import { ProductCard } from '@/src/components/ui/ProductCard';
import { SearchBar } from '@/src/components/ui/SearchBar';
import { SeeAllAction } from '@/src/components/ui/SeeAllAction';
import { Skeleton } from '@/src/components/ui/Skeleton';
import ModalDialog from '@/src/components/ui/ModalDialog';
import { RoundedInput } from '@/src/components/auth/RoundedInput';
import { theme } from '@/src/theme';
import { fetchCategories, fetchHome } from '@/src/api/catalog';
import type { Banner, BannerGroups, Category, NewsletterPopup, Product, PromoSlide, StorefrontSettings } from '@/src/types/storefront';
import { useToast } from '@/src/overlays/ToastProvider';
import { subscribeNewsletter } from '@/src/api/newsletter';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { usePullToRefresh } from '@/src/hooks/usePullToRefresh';
import { formatCurrency } from '@/src/lib/formatCurrency';
import { usePreferences } from '@/src/store/preferencesStore';

export default function HomeScreen() {
  const { width } = useWindowDimensions();
  const { show } = useToast();
  const { state } = usePreferences();
  const [loading, setLoading] = useState(true);
  const [heroSlide, setHeroSlide] = useState<PromoSlide | null>(null);
  const [heroSlides, setHeroSlides] = useState<PromoSlide[]>([]);
  const [heroIndex, setHeroIndex] = useState(0);
  const [categories, setCategories] = useState<Category[]>([]);
  const [flashDeals, setFlashDeals] = useState<Product[]>([]);
  const [trending, setTrending] = useState<Product[]>([]);
  const [recommended, setRecommended] = useState<Product[]>([]);
  const [banners, setBanners] = useState<BannerGroups | null>(null);
  const [newsletterPopup, setNewsletterPopup] = useState<NewsletterPopup | null>(null);
  const [popupVisible, setPopupVisible] = useState(false);
  const [popupKind, setPopupKind] = useState<'banner' | 'newsletter' | null>(null);
  const [popupEmail, setPopupEmail] = useState('');
  const [popupSubmitting, setPopupSubmitting] = useState(false);
  const [popupMessage, setPopupMessage] = useState<string | null>(null);
  const [storefront, setStorefront] = useState<StorefrontSettings | null>(null);
  const requestId = useRef(0);
  const popupStorageKey = 'newsletterPopupDismissedAt';
  const gridGap = theme.moderateScale(12);
  const horizontalPadding = theme.moderateScale(20);
  const gridItemWidth = (width - horizontalPadding * 2 - gridGap) / 2;
  const flashItemWidth = (width - horizontalPadding * 2 - gridGap * 2) / 3;
  const skeletonCategories = useMemo<Category[]>(
    () =>
      Array.from({ length: 6 }, (_, index) => ({
        id: `skeleton-cat-${index}`,
        name: '',
        slug: '',
        count: 0,
        image: null,
        accent: null,
      })),
    []
  );
  const skeletonProducts = useMemo<Product[]>(
    () =>
      Array.from({ length: 6 }, (_, index) => ({
        id: `skeleton-prod-${index}`,
        slug: '',
        name: '',
        price: 0,
        compareAt: null,
        rating: 0,
        reviews: 0,
        image: null,
      })),
    []
  );
  const stripBanner = banners?.strip?.[0] ?? null;
  const carouselBanners = banners?.carousel ?? [];
  const fullBanners = banners?.full ?? [];
  const popupBanner = banners?.popup?.[0] ?? null;
  const safeCarouselBanners = carouselBanners.filter(Boolean) as Banner[];
  const safeFullBanners = fullBanners.filter(Boolean) as Banner[];

  const loadHome = useCallback(async () => {
    const id = ++requestId.current;
    setLoading(true);
    try {
      const data = await fetchHome();
      if (id !== requestId.current) return;
      const heroItems = Array.isArray(data.hero) ? data.hero : [];
      const normalizedHero = heroItems.map((slide) => ({
        ...slide,
        title: String(slide.title ?? slide.kicker ?? 'Big Sale'),
        subtitle: String(slide.subtitle ?? 'Up to 50%'),
      }));
      setHeroSlides(normalizedHero);
      setHeroSlide(normalizedHero[0] ?? null);
      setHeroIndex(0);
      let nextCategories = Array.isArray(data.categories) ? data.categories : [];
      if (nextCategories.length === 0) {
        try {
          nextCategories = await fetchCategories();
        } catch {
          nextCategories = [];
        }
      }
      setCategories(nextCategories);
      setFlashDeals(Array.isArray(data.flashDeals) ? data.flashDeals : []);
      setTrending(Array.isArray(data.trending) ? data.trending : []);
      setRecommended(Array.isArray(data.recommended) ? data.recommended : []);
      setBanners(data.banners ?? null);
      setNewsletterPopup(data.newsletterPopup ?? null);
      setStorefront(data.storefront ?? null);
    } catch (err: any) {
      if (id !== requestId.current) return;
      const message = err?.message ?? 'Unable to load home data.';
      show({ type: 'error', message });
    } finally {
      if (id === requestId.current) setLoading(false);
    }
  }, [show]);

  useEffect(() => {
    loadHome();
    return () => {
      requestId.current += 1;
    };
  }, [loadHome]);

  const { refreshing, onRefresh } = usePullToRefresh(loadHome);

  useEffect(() => {
    let active = true;
    let timer: ReturnType<typeof setTimeout> | null = null;

    const checkDismissed = async (dismissDays: number | null | undefined) => {
      if (!dismissDays || dismissDays <= 0) return false;
      const dismissedAtRaw = await AsyncStorage.getItem(popupStorageKey);
      if (!dismissedAtRaw) return false;
      const dismissedAt = Number(dismissedAtRaw);
      if (!Number.isFinite(dismissedAt)) return false;
      const elapsed = Date.now() - dismissedAt;
      return elapsed < dismissDays * 24 * 60 * 60 * 1000;
    };

    const schedulePopup = async () => {
      if (loading) return;
      if (popupBanner) {
        if (!active) return;
        setPopupMessage(null);
        setPopupEmail('');
        setPopupKind('banner');
        setPopupVisible(true);
        return;
      }

      if (newsletterPopup?.enabled) {
        const dismissed = await checkDismissed(newsletterPopup.dismissDays);
        if (!active || dismissed) return;
        const delayMs = Math.max(0, Number(newsletterPopup.delaySeconds ?? 0)) * 1000;
        timer = setTimeout(() => {
          if (!active) return;
          setPopupMessage(null);
          setPopupEmail('');
          setPopupKind('newsletter');
          setPopupVisible(true);
        }, delayMs);
      }
    };

    schedulePopup();

    return () => {
      active = false;
      if (timer) clearTimeout(timer);
    };
  }, [loading, popupBanner, newsletterPopup, popupStorageKey]);

  const openHref = (href?: string | null) => {
    if (!href) return;
    if (href.startsWith('http://') || href.startsWith('https://')) {
      Linking.openURL(href).catch(() => {});
      return;
    }
    router.push(href);
  };

  const handleBannerPress = (banner?: Banner | null) => {
    const href = banner?.ctaUrl ?? null;
    if (href) {
      openHref(href);
    }
  };

  const handlePopupClose = () => {
    setPopupVisible(false);
    setPopupMessage(null);
    setPopupKind(null);
    if (popupKind === 'newsletter' && newsletterPopup?.dismissDays) {
      AsyncStorage.setItem(popupStorageKey, String(Date.now())).catch(() => {});
    }
  };

  const handleNewsletterSubscribe = async () => {
    const email = popupEmail.trim();
    if (!email) {
      show({ type: 'error', message: 'Please enter your email.' });
      return;
    }
    setPopupSubmitting(true);
    setPopupMessage(null);
    try {
      const response = await subscribeNewsletter({
        email,
        source: newsletterPopup?.source ?? 'mobile_popup',
      });
      setPopupMessage(response?.message ?? 'Thanks for subscribing!');
      await AsyncStorage.setItem(popupStorageKey, String(Date.now()));
    } catch (err: any) {
      const message = err?.message ?? 'Unable to subscribe.';
      show({ type: 'error', message });
    } finally {
      setPopupSubmitting(false);
    }
  };

  const formatPrice = (value: number, currency?: string | null) =>
    formatCurrency(value, currency, state.currency);

  const topProducts = useMemo(
    () =>
      (loading ? skeletonCategories : categories).slice(0, 5).map((category, index) => ({
        id: String(category.id ?? `top-${index}`),
        label: category.name ?? '',
        slug: category.slug,
        icon: (['shopping-bag', 'zap', 'layers', 'activity', 'briefcase'] as const)[index % 5],
      })),
    [categories, loading, skeletonCategories]
  );

  const newItems = useMemo(
    () => (loading ? skeletonProducts.slice(0, 4) : recommended.slice(0, 4)),
    [loading, recommended, skeletonProducts]
  );
  const flashItems = useMemo(
    () => (loading ? skeletonProducts.slice(0, 6) : flashDeals.slice(0, 6)),
    [flashDeals, loading, skeletonProducts]
  );
  const mostPopular = useMemo(
    () => (loading ? skeletonProducts.slice(0, 4) : trending.slice(0, 4)),
    [loading, skeletonProducts, trending]
  );
  const justForYou = useMemo(
    () => (loading ? skeletonProducts.slice(0, 4) : recommended.slice(0, 4)),
    [loading, recommended, skeletonProducts]
  );
  const heroGradientFor = (slide?: PromoSlide | null) =>
    slide?.tone ? [slide.tone, theme.colors.sand] : ['#f0b61f', '#f3c93a'];
  const resolveResizeMode = (mode?: string | null) =>
    mode === 'contain' || mode === 'split' ? 'contain' : 'cover';

  const renderCarouselBanner = ({ item }: { item: Banner }) => {
    const background = item.backgroundColor ?? theme.colors.sand;
    const textColor = item.textColor ?? theme.colors.inkDark;

    return (
      <Pressable
        style={[styles.carouselCard, { backgroundColor: background }]}
        onPress={() => handleBannerPress(item)}
      >
        {item.image ? (
          <Image
            source={{ uri: item.image }}
            style={styles.carouselImage}
            resizeMode={resolveResizeMode(item.imageMode)}
          />
        ) : (
          <View style={styles.carouselImagePlaceholder} />
        )}
        <View style={styles.carouselContent}>
          <Text style={[styles.carouselTitle, { color: textColor }]} numberOfLines={2}>
            {item.title || item.badgeText || 'Featured'}
          </Text>
          {item.description ? (
            <Text style={[styles.carouselSubtitle, { color: textColor }]} numberOfLines={2}>
              {item.description}
            </Text>
          ) : null}
        </View>
      </Pressable>
    );
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
          {storefront?.logo ? (
            <Image source={{ uri: storefront.logo }} style={styles.logo} resizeMode="contain" />
          ) : (
            <Text style={styles.title}>{storefront?.brandName ?? 'Simbazu'}</Text>
          )}
          <SearchBar
            placeholder="Search"
            onRightPress={() => router.push('/image-search')}
            onFocus={() => router.push('/search')}
            rightIconBorder={theme.colors.border}
            style={styles.searchBar}
          />
        </View>

        <View style={styles.banner}>
          <FlatList
            horizontal
            pagingEnabled
            data={heroSlides.length > 0 ? heroSlides : heroSlide ? [heroSlide] : []}
            keyExtractor={(item, index) => `${item.id}-${index}`}
            showsHorizontalScrollIndicator={false}
            onMomentumScrollEnd={(event) => {
              const nextIndex = Math.round(event.nativeEvent.contentOffset.x / Math.max(width, 1));
              setHeroIndex(nextIndex);
            }}
            renderItem={({ item }) => (
              <Pressable
                style={{ width: Math.max(width, 1) }}
                onPress={() => (item?.href ? openHref(item.href) : router.push('/flash-sale'))}
              >
                <LinearGradient colors={heroGradientFor(item)} style={styles.bannerGradient}>
                  <View style={styles.bannerCopy}>
                    <Text style={styles.bannerTitle}>{item?.title ?? 'Big Sale'}</Text>
                    <Text style={styles.bannerSubtitle}>{item?.subtitle ?? 'Up to 50%'}</Text>
                    <View style={styles.bannerBadge}>
                      <Text style={styles.bannerBadgeText}>Happening{'\n'}Now</Text>
                    </View>
                  </View>
                  <View style={styles.bannerImage} />
                </LinearGradient>
              </Pressable>
            )}
          />
          <View style={styles.bannerDots}>
            {(heroSlides.length > 0 ? heroSlides : heroSlide ? [heroSlide] : []).map((_, index) => (
              <View
                key={`hero-dot-${index}`}
                style={index === heroIndex ? styles.bannerDotActive : styles.bannerDot}
              />
            ))}
          </View>
        </View>

        {stripBanner ? (
          <Pressable
            style={[styles.stripBanner, { backgroundColor: stripBanner.backgroundColor ?? theme.colors.sand }]}
            onPress={() => handleBannerPress(stripBanner)}
          >
            <View style={styles.stripCopy}>
              <Text
                style={[styles.stripTitle, { color: stripBanner.textColor ?? theme.colors.inkDark }]}
                numberOfLines={1}
              >
                {stripBanner.title || stripBanner.badgeText || 'Limited offer'}
              </Text>
              {stripBanner.description ? (
                <Text
                  style={[styles.stripSubtitle, { color: stripBanner.textColor ?? theme.colors.ink }]}
                  numberOfLines={2}
                >
                  {stripBanner.description}
                </Text>
              ) : null}
            </View>
            {stripBanner.ctaText ? (
              <Text style={[styles.stripCta, { color: stripBanner.badgeColor ?? theme.colors.primary }]}>
                {stripBanner.ctaText}
              </Text>
            ) : null}
          </Pressable>
        ) : null}

        {safeCarouselBanners.length > 0 ? (
          <View style={styles.section}>
            <View style={styles.sectionHeader}>
              <Text style={styles.sectionTitle}>Featured</Text>
            </View>
            <FlatList
              horizontal
              data={safeCarouselBanners}
              keyExtractor={(item, index) => `${String(item?.id ?? 'banner')}-${index}`}
              showsHorizontalScrollIndicator={false}
              contentContainerStyle={styles.carouselRow}
              renderItem={renderCarouselBanner}
            />
          </View>
        ) : null}

        {safeFullBanners.length > 0 ? (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>Spotlight</Text>
            {safeFullBanners.map((banner, index) => (
              <Pressable
                key={`${String(banner.id ?? 'full')}-${index}`}
                style={styles.fullBanner}
                onPress={() => handleBannerPress(banner)}
              >
                {banner.image ? (
                  <Image
                    source={{ uri: banner.image }}
                    style={styles.fullBannerImage}
                    resizeMode={resolveResizeMode(banner.imageMode)}
                  />
                ) : (
                  <View style={[styles.fullBannerImage, styles.fullBannerPlaceholder]} />
                )}
                <View style={styles.fullBannerOverlay} />
                <View style={styles.fullBannerContent}>
                  <Text
                    style={[styles.fullBannerTitle, { color: banner.textColor ?? theme.colors.white }]}
                    numberOfLines={2}
                  >
                    {banner.title || banner.badgeText || 'Featured'}
                  </Text>
                  {banner.description ? (
                    <Text
                      style={[styles.fullBannerSubtitle, { color: banner.textColor ?? theme.colors.white }]}
                      numberOfLines={2}
                    >
                      {banner.description}
                    </Text>
                  ) : null}
                </View>
              </Pressable>
            ))}
          </View>
        ) : null}

        <View style={styles.section}>
          <View style={styles.sectionHeader}>
            <Text style={styles.sectionTitle}>Categories</Text>
            <SeeAllAction onPress={() => router.push('/(tabs)/categories')} />
          </View>
          <FlatList
            data={loading ? skeletonCategories : categories}
            keyExtractor={(item) => String(item.id)}
            numColumns={2}
            scrollEnabled={false}
            columnWrapperStyle={styles.column}
            renderItem={({ item }) => (
              <CategoryCard
                loading={loading}
                label={item.name}
                count={item.count}
                width={gridItemWidth}
                onPress={() => router.push(`/products?category=${encodeURIComponent(item.slug || item.name)}`)}
              />
            )}
          />
        </View>

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Top Products</Text>
          <FlatList
            horizontal
            data={topProducts}
            keyExtractor={(item) => item.id}
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={styles.topRow}
            renderItem={({ item }) => (
              <Pressable
                style={styles.topCard}
                onPress={() =>
                  router.push(`/products?category=${encodeURIComponent(item.slug || item.label)}`)
                }
                disabled={loading}
              >
                {loading ? (
                  <>
                    <Skeleton
                      height={theme.moderateScale(56)}
                      width={theme.moderateScale(56)}
                      radius={theme.moderateScale(18)}
                    />
                    <Skeleton height={theme.moderateScale(10)} radius={theme.moderateScale(5)} width="80%" />
                  </>
                ) : (
                  <>
                    <View style={styles.topIcon}>
                      <Feather name={item.icon as any} size={theme.moderateScale(18)} color={theme.colors.ink} />
                    </View>
                    <Text style={styles.topLabel}>{item.label}</Text>
                  </>
                )}
              </Pressable>
            )}
          />
        </View>

        <View style={styles.section}>
          <View style={styles.sectionHeader}>
            <Text style={styles.sectionTitle}>New Items</Text>
            <SeeAllAction onPress={() => router.push('/products')} />
          </View>
          <FlatList
            horizontal
            data={newItems}
            keyExtractor={(item) => String(item.id)}
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={styles.horizontalRow}
            renderItem={({ item }) => (
              <ProductCard
                loading={loading}
                title={item.name}
                price={formatPrice(item.price, item.currency)}
                width={theme.moderateScale(140)}
                imageHeight={theme.moderateScale(130)}
                image={item.image || item.media?.[0] ? { uri: item.image || item.media?.[0] } : undefined}
                onPress={() => router.push(`/products/${item.slug}`)}
              />
            )}
          />
        </View>

        <View style={styles.section}>
          <View style={styles.flashHeader}>
            <Text style={styles.sectionTitle}>Flash Sale</Text>
            <View style={styles.timer}>
              <Feather name="clock" size={theme.moderateScale(12)} color={theme.colors.primary} />
              <Text style={styles.timerText}>00</Text>
              <Text style={styles.timerText}>36</Text>
              <Text style={styles.timerText}>58</Text>
            </View>
          </View>
          <FlatList
            data={flashItems}
            keyExtractor={(item) => String(item.id)}
            numColumns={3}
            scrollEnabled={false}
            columnWrapperStyle={styles.flashColumn}
            renderItem={({ item }) => (
              <ProductCard
                loading={loading}
                title={item.name}
                price={formatPrice(item.price, item.currency)}
                oldPrice={item.compareAt ? formatPrice(item.compareAt, item.currency) : undefined}
                badge={item.compareAt ? 'Sale' : undefined}
                width={flashItemWidth}
                imageHeight={theme.moderateScale(90)}
                image={item.image || item.media?.[0] ? { uri: item.image || item.media?.[0] } : undefined}
                onPress={() => router.push(`/products/${item.slug}`)}
              />
            )}
          />
        </View>

        <View style={styles.section}>
          <View style={styles.sectionHeader}>
            <Text style={styles.sectionTitle}>Most Popular</Text>
            <SeeAllAction onPress={() => router.push('/products')} />
          </View>
          <FlatList
            horizontal
            data={mostPopular}
            keyExtractor={(item) => String(item.id)}
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={styles.popularRow}
            renderItem={({ item }) => (
              <PopularCard
                loading={loading}
                label={item.name}
                count={`${item.reviews}`}
                onPress={() => router.push(`/products/${item.slug}`)}
              />
            )}
          />
        </View>

        <View style={styles.section}>
          <View style={styles.sectionHeader}>
            <View style={styles.justRow}>
              <Text style={styles.sectionTitle}>Just For You</Text>
              <Feather name="star" size={theme.moderateScale(14)} color={theme.colors.primary} />
            </View>
          </View>
          <FlatList
            data={justForYou}
            keyExtractor={(item) => String(item.id)}
            numColumns={2}
            scrollEnabled={false}
            columnWrapperStyle={styles.column}
            renderItem={({ item }) => (
              <ProductCard
                loading={loading}
                title={item.name}
                price={formatPrice(item.price, item.currency)}
                width={gridItemWidth}
                imageHeight={theme.moderateScale(160)}
                image={item.image || item.media?.[0] ? { uri: item.image || item.media?.[0] } : undefined}
                onPress={() => router.push(`/products/${item.slug}`)}
              />
            )}
          />
        </View>
      </ScrollView>

      <ModalDialog visible={popupVisible} onClose={handlePopupClose}>
        {popupKind === 'newsletter' ? (
          <View style={styles.popupContent}>
            {newsletterPopup?.image ? (
              <Image source={{ uri: newsletterPopup.image }} style={styles.popupImage} resizeMode="cover" />
            ) : null}
            <Text style={styles.popupTitle}>{newsletterPopup?.title ?? 'Join our list'}</Text>
            {newsletterPopup?.body ? (
              <Text style={styles.popupBody}>{newsletterPopup.body}</Text>
            ) : null}
            {newsletterPopup?.incentive ? (
              <Text style={styles.popupIncentive}>{newsletterPopup.incentive}</Text>
            ) : null}
            <RoundedInput
              placeholder="Email address"
              keyboardType="email-address"
              containerStyle={styles.popupInput}
              inputProps={{
                value: popupEmail,
                onChangeText: setPopupEmail,
              }}
            />
            <Pressable
              style={[styles.popupAction, popupSubmitting ? styles.popupActionDisabled : null]}
              onPress={handleNewsletterSubscribe}
              disabled={popupSubmitting}
            >
              <Text style={styles.popupActionText}>
                {popupSubmitting ? 'Submitting...' : 'Subscribe'}
              </Text>
            </Pressable>
            {popupMessage ? <Text style={styles.popupMessage}>{popupMessage}</Text> : null}
          </View>
        ) : (
          <View style={styles.popupContent}>
            {popupBanner?.image ? (
              <Image source={{ uri: popupBanner.image }} style={styles.popupImage} resizeMode="cover" />
            ) : null}
            <Text style={styles.popupTitle}>{popupBanner?.title ?? 'Special offer'}</Text>
            {popupBanner?.description ? (
              <Text style={styles.popupBody}>{popupBanner.description}</Text>
            ) : null}
            {popupBanner?.ctaText ? (
              <Pressable style={styles.popupAction} onPress={() => handleBannerPress(popupBanner)}>
                <Text style={styles.popupActionText}>{popupBanner.ctaText}</Text>
              </Pressable>
            ) : null}
          </View>
        )}
      </ModalDialog>
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
    gap: theme.moderateScale(12),
    marginBottom: theme.moderateScale(16),
  },
  searchBar: {
    flex: 1,
    backgroundColor: theme.colors.white,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  title: {
    fontSize: theme.moderateScale(24),
    fontWeight: '700',
    color: theme.colors.ink,
  },
  logo: {
    width: theme.moderateScale(120),
    height: theme.moderateScale(36),
  },
  banner: {
    marginBottom: theme.moderateScale(20),
  },
  bannerGradient: {
    height: theme.moderateScale(150),
    borderRadius: theme.moderateScale(18),
    padding: theme.moderateScale(16),
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  bannerCopy: {
    gap: theme.moderateScale(6),
  },
  bannerTitle: {
    fontSize: theme.moderateScale(20),
    fontWeight: '700',
    color: theme.colors.ink,
  },
  bannerSubtitle: {
    fontSize: theme.moderateScale(12),
    color: theme.colors.ink,
  },
  bannerBadge: {
    marginTop: theme.moderateScale(8),
    backgroundColor: theme.colors.primary,
    borderRadius: theme.moderateScale(16),
    paddingHorizontal: theme.moderateScale(12),
    paddingVertical: theme.moderateScale(6),
    alignSelf: 'flex-start',
  },
  bannerBadgeText: {
    fontSize: theme.moderateScale(10),
    color: theme.colors.white,
    fontWeight: '600',
  },
  bannerImage: {
    width: theme.moderateScale(110),
    height: theme.moderateScale(110),
    borderRadius: theme.moderateScale(16),
    backgroundColor: 'rgba(255,255,255,0.4)',
  },
  bannerDots: {
    flexDirection: 'row',
    gap: theme.moderateScale(6),
    justifyContent: 'center',
    marginTop: theme.moderateScale(10),
  },
  bannerDot: {
    width: theme.moderateScale(8),
    height: theme.moderateScale(8),
    borderRadius: theme.moderateScale(4),
    backgroundColor: theme.colors.primarySoftAlt,
  },
  bannerDotActive: {
    width: theme.moderateScale(18),
    height: theme.moderateScale(8),
    borderRadius: theme.moderateScale(4),
    backgroundColor: theme.colors.primary,
  },
  stripBanner: {
    marginTop: theme.moderateScale(14),
    paddingVertical: theme.moderateScale(14),
    paddingHorizontal: theme.moderateScale(16),
    borderRadius: theme.moderateScale(16),
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: theme.moderateScale(12),
  },
  stripCopy: {
    flex: 1,
  },
  stripTitle: {
    fontSize: theme.moderateScale(14),
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  stripSubtitle: {
    marginTop: theme.moderateScale(4),
    fontSize: theme.moderateScale(12),
    color: theme.colors.ink,
  },
  stripCta: {
    fontSize: theme.moderateScale(12),
    fontWeight: '700',
    color: theme.colors.primary,
  },
  section: {
    marginTop: theme.moderateScale(20),
  },
  sectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: theme.moderateScale(12),
  },
  sectionTitle: {
    fontSize: theme.moderateScale(16),
    fontWeight: '700',
    color: theme.colors.ink,
  },
  carouselRow: {
    gap: theme.moderateScale(14),
    paddingBottom: theme.moderateScale(6),
  },
  carouselCard: {
    width: theme.moderateScale(220),
    borderRadius: theme.moderateScale(18),
    overflow: 'hidden',
  },
  carouselImage: {
    width: '100%',
    height: theme.moderateScale(120),
  },
  carouselImagePlaceholder: {
    width: '100%',
    height: theme.moderateScale(120),
    backgroundColor: theme.colors.primarySoftAlt,
  },
  carouselContent: {
    padding: theme.moderateScale(12),
    gap: theme.moderateScale(6),
  },
  carouselTitle: {
    fontSize: theme.moderateScale(13),
    fontWeight: '700',
  },
  carouselSubtitle: {
    fontSize: theme.moderateScale(12),
    color: theme.colors.ink,
  },
  fullBanner: {
    marginTop: theme.moderateScale(12),
    borderRadius: theme.moderateScale(18),
    overflow: 'hidden',
  },
  fullBannerImage: {
    width: '100%',
    height: theme.moderateScale(160),
  },
  fullBannerPlaceholder: {
    backgroundColor: theme.colors.primarySoftAlt,
  },
  fullBannerOverlay: {
    position: 'absolute',
    left: 0,
    right: 0,
    top: 0,
    bottom: 0,
    backgroundColor: 'rgba(0,0,0,0.25)',
  },
  fullBannerContent: {
    position: 'absolute',
    left: theme.moderateScale(16),
    right: theme.moderateScale(16),
    bottom: theme.moderateScale(14),
  },
  fullBannerTitle: {
    fontSize: theme.moderateScale(16),
    fontWeight: '700',
    color: theme.colors.white,
  },
  fullBannerSubtitle: {
    marginTop: theme.moderateScale(4),
    fontSize: theme.moderateScale(12),
    color: theme.colors.white,
  },
  column: {
    justifyContent: 'space-between',
    marginBottom: theme.moderateScale(12),
  },
  topRow: {
    gap: theme.moderateScale(14),
  },
  topCard: {
    width: theme.moderateScale(64),
    alignItems: 'center',
  },
  topIcon: {
    width: theme.moderateScale(56),
    height: theme.moderateScale(56),
    borderRadius: theme.moderateScale(18),
    backgroundColor: theme.colors.primarySoft,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: theme.moderateScale(6),
  },
  topLabel: {
    fontSize: theme.moderateScale(11),
    color: theme.colors.ink,
  },
  horizontalRow: {
    gap: theme.moderateScale(12),
  },
  flashHeader: {
    flexDirection: 'row',
    alignItems: 'center',
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
  popularRow: {
    gap: theme.moderateScale(12),
  },
  justRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.moderateScale(6),
  },
  popupContent: {
    width: '100%',
    alignItems: 'center',
    gap: theme.moderateScale(10),
  },
  popupImage: {
    width: '100%',
    height: theme.moderateScale(140),
    borderRadius: theme.moderateScale(12),
  },
  popupTitle: {
    fontSize: theme.moderateScale(16),
    fontWeight: '700',
    color: theme.colors.ink,
    textAlign: 'center',
  },
  popupBody: {
    fontSize: theme.moderateScale(12),
    color: theme.colors.ink,
    textAlign: 'center',
  },
  popupIncentive: {
    fontSize: theme.moderateScale(12),
    color: theme.colors.primary,
    fontWeight: '600',
    textAlign: 'center',
  },
  popupInput: {
    width: '100%',
  },
  popupAction: {
    marginTop: theme.moderateScale(6),
    paddingVertical: theme.moderateScale(10),
    paddingHorizontal: theme.moderateScale(18),
    borderRadius: theme.moderateScale(12),
    backgroundColor: theme.colors.primary,
  },
  popupActionDisabled: {
    opacity: 0.7,
  },
  popupActionText: {
    color: theme.colors.white,
    fontSize: theme.moderateScale(12),
    fontWeight: '700',
  },
  popupMessage: {
    fontSize: theme.moderateScale(12),
    color: theme.colors.primary,
  },
});
