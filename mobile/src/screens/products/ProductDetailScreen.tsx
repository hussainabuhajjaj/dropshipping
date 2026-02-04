import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Gesture, GestureDetector } from 'react-native-gesture-handler';
import Animated, {
  runOnJS,
  useAnimatedStyle,
  useSharedValue,
  withTiming,
} from 'react-native-reanimated';
import {
  FlatList,
  Image,
  Modal,
  Pressable,
  Share,
  ScrollView,
  StyleSheet,
  Text,
  View,
  useWindowDimensions,
} from '@/src/utils/responsiveStyleSheet';
import { useCart } from '@/lib/cartStore';
import { useWishlist } from '@/lib/wishlistStore';
import { useRecentlyViewed } from '@/lib/recentlyViewedStore';
import { Skeleton } from '@/src/components/ui/Skeleton';
import { theme } from '@/src/theme';
import { useToast } from '@/src/overlays/ToastProvider';
import type { Product } from '@/src/types/storefront';
import { getProductShareUrl } from '@/src/lib/shareLinks';

const materials = ['Cotton', 'Polyester'];
const detailItems = ['Soft-touch knit', 'Breathable fabric', 'Machine washable'];

type ProductDetailScreenProps = {
  product: Product | null;
  loading?: boolean;
  mode?: 'full' | 'modal';
  onClose?: () => void;
  related?: Product[];
  slug?: string;
};

type LayoutRect = {
  x: number;
  y: number;
  width: number;
  height: number;
};

type ZoomableImageProps = {
  uri: string;
  width: number;
  height: number;
};

function ZoomableImage({ uri, width, height }: ZoomableImageProps) {
  const scale = useSharedValue(1);
  const savedScale = useSharedValue(1);
  const translateX = useSharedValue(0);
  const translateY = useSharedValue(0);
  const savedX = useSharedValue(0);
  const savedY = useSharedValue(0);

  const panGesture = Gesture.Pan()
    .onUpdate((event) => {
      if (scale.value <= 1) return;
      translateX.value = savedX.value + event.translationX;
      translateY.value = savedY.value + event.translationY;
    })
    .onEnd(() => {
      if (scale.value <= 1) {
        translateX.value = withTiming(0);
        translateY.value = withTiming(0);
        savedX.value = 0;
        savedY.value = 0;
        return;
      }
      savedX.value = translateX.value;
      savedY.value = translateY.value;
    });

  const pinchGesture = Gesture.Pinch()
    .onUpdate((event) => {
      const nextScale = Math.min(4, Math.max(1, savedScale.value * event.scale));
      scale.value = nextScale;
      if (nextScale === 1) {
        translateX.value = 0;
        translateY.value = 0;
        savedX.value = 0;
        savedY.value = 0;
      }
    })
    .onEnd(() => {
      if (scale.value <= 1) {
        scale.value = withTiming(1);
        translateX.value = withTiming(0);
        translateY.value = withTiming(0);
        savedScale.value = 1;
        savedX.value = 0;
        savedY.value = 0;
        return;
      }
      savedScale.value = scale.value;
    });

  const animatedStyle = useAnimatedStyle(() => ({
    transform: [
      { translateX: translateX.value },
      { translateY: translateY.value },
      { scale: scale.value },
    ],
  }));

  const composedGesture = Gesture.Simultaneous(pinchGesture, panGesture);

  return (
    <View style={[styles.viewerSlide, { width: Math.max(width, 1), height: Math.max(height, 1) }]}>
      <GestureDetector gesture={composedGesture}>
        <Animated.Image
          source={{ uri }}
          style={[styles.viewerImage, { width: Math.max(width, 1), height: Math.max(height, 1) }, animatedStyle]}
        />
      </GestureDetector>
    </View>
  );
}

export function ProductDetailScreen({
  product,
  loading = false,
  mode = 'full',
  onClose,
  related = [],
  slug: routeSlug,
}: ProductDetailScreenProps) {
  const { addItem, items } = useCart();
  const { toggle, contains } = useWishlist();
  const { track } = useRecentlyViewed();
  const { show } = useToast();
  const youMayLike: Product[] = related;
  const showExtended = mode === 'full' || mode === 'modal';
  const isModal = mode === 'modal';
  const closeIcon = isModal ? 'x' : 'chevron-left';
  const closeLabel = isModal ? 'Close' : 'Go back';
  const handleClose = onClose ?? (() => router.back());
  const resolvedSlug = routeSlug || product?.slug || '';
  const slugParam = resolvedSlug ? encodeURIComponent(resolvedSlug) : '';
  const { width, height } = useWindowDimensions();
  const sliderRef = useRef<FlatList<string>>(null);
  const [activeIndex, setActiveIndex] = useState(0);
  const [viewerVisible, setViewerVisible] = useState(false);
  const [viewerIndex, setViewerIndex] = useState(0);
  const [flyVisible, setFlyVisible] = useState(false);
  const [flyImage, setFlyImage] = useState<string | null>(null);
  const imageWrapRef = useRef<View>(null);
  const cartFloatRef = useRef<View>(null);
  const [selectedVariantId, setSelectedVariantId] = useState<string | number | null>(null);
  const lastTrackedSlug = useRef<string | null>(null);
  const cartCount = useMemo(
    () => items.reduce((sum, item) => sum + (item.quantity ?? 0), 0),
    [items]
  );
  const variants = useMemo(() => product?.variants ?? [], [product?.variants]);
  const variantOptions = useMemo(
    () =>
      variants.map((variant, index) => ({
        id: variant.id,
        label: variant.title?.trim() || variant.sku?.trim() || `Variant ${index + 1}`,
        price: variant.price ?? null,
        compareAt: variant.compare_at_price ?? null,
      })),
    [variants]
  );
  const activeVariant = useMemo(
    () => (selectedVariantId ? variants.find((variant) => variant.id === selectedVariantId) ?? null : null),
    [selectedVariantId, variants]
  );
  const displayPrice = activeVariant?.price ?? product?.price ?? 0;
  const displayCompareAt = activeVariant?.compare_at_price ?? product?.compareAt ?? null;
  const imageSlides = useMemo(() => {
    if (!product) return [];
    const media = product.media && product.media.length > 0 ? product.media : [];
    const images = [product.image, ...media].filter(Boolean) as string[];
    return Array.from(new Set(images));
  }, [product]);
  const descriptionImages = product
    ? ([product.image, ...(product.media ?? [])].filter(Boolean) as string[]).slice(0, 3)
    : [];
  const wishlistKey = product?.id ?? '';
  const isWishlisted = wishlistKey ? contains(wishlistKey) : false;
  const handleToggle = async () => {
    if (!product) return;
    const result = await toggle(product);
    if (!result.ok) {
      show({ type: 'error', message: result.message ?? 'Unable to update wishlist.' });
    }
  };
  const handleShare = async () => {
    if (!product) return;
    try {
      const shareUrl = getProductShareUrl(resolvedSlug);
      await Share.share({
        message: `${product.name}\n${shareUrl}`,
        url: shareUrl,
        title: product.name,
      });
    } catch (err: any) {
      show({ type: 'error', message: err?.message ?? 'Unable to share.' });
    }
  };
  const descriptionBlocks = useMemo(() => {
    if (!product?.description) return ['No description available.'];
    return product.description
      .split(/\n+/)
      .map((paragraph) => paragraph.trim())
      .filter((paragraph) => paragraph.length > 0);
  }, [product?.description]);

  const flySize = theme.moderateScale(56);
  const flyX = useSharedValue(0);
  const flyY = useSharedValue(0);
  const flyScale = useSharedValue(1);
  const flyOpacity = useSharedValue(0);
  const cartX = useSharedValue(0);
  const cartY = useSharedValue(0);
  const cartStartX = useSharedValue(0);
  const cartStartY = useSharedValue(0);
  const cartPadding = theme.moderateScale(16);
  const cartSize = theme.moderateScale(56);
  const cartBottomInset = theme.moderateScale(90);
  const cartMaxX = useSharedValue(0);
  const cartMaxY = useSharedValue(0);

  const clamp = (value: number, min: number, max: number) => {
    'worklet';
    return Math.min(Math.max(value, min), max);
  };

  const cartDragGesture = Gesture.Pan()
    .onStart(() => {
      cartStartX.value = cartX.value;
      cartStartY.value = cartY.value;
    })
    .onUpdate((event) => {
      cartX.value = clamp(cartStartX.value + event.translationX, cartPadding, cartMaxX.value);
      cartY.value = clamp(cartStartY.value + event.translationY, cartPadding, cartMaxY.value);
    });

  const cartDragStyle = useAnimatedStyle(() => ({
    transform: [{ translateX: cartX.value }, { translateY: cartY.value }],
  }));

  const flyStyle = useAnimatedStyle(() => ({
    transform: [{ translateX: flyX.value }, { translateY: flyY.value }, { scale: flyScale.value }],
    opacity: flyOpacity.value,
  }));

  const measureInWindow = (ref: React.RefObject<View>) =>
    new Promise<LayoutRect | null>((resolve) => {
      if (!ref.current) {
        resolve(null);
        return;
      }
      ref.current.measureInWindow((x, y, width, height) => resolve({ x, y, width, height }));
    });

  const openViewer = (index: number) => {
    const safeIndex = Math.max(0, Math.min(index, imageSlides.length - 1));
    setViewerIndex(safeIndex);
    setViewerVisible(true);
  };

  const triggerFlyAnimation = async () => {
    const uri = product?.image || product?.media?.[0] || null;
    if (!uri) return;
    setFlyImage(uri);
    const [start, end] = await Promise.all([
      measureInWindow(imageWrapRef),
      measureInWindow(cartFloatRef),
    ]);
    if (!start || !end) return;

    const startX = start.x + start.width / 2 - flySize / 2;
    const startY = start.y + start.height / 2 - flySize / 2;
    const endX = end.x + end.width / 2 - flySize / 2;
    const endY = end.y + end.height / 2 - flySize / 2;

    flyX.value = startX;
    flyY.value = startY;
    flyScale.value = 1;
    flyOpacity.value = 1;
    setFlyVisible(true);

    flyX.value = withTiming(endX, { duration: 650 });
    flyY.value = withTiming(endY, { duration: 650 });
    flyScale.value = withTiming(0.2, { duration: 650 });
    flyOpacity.value = withTiming(0, { duration: 650 }, (finished) => {
      if (finished) {
        runOnJS(setFlyVisible)(false);
      }
    });
  };

  const handleAddToCart = async () => {
    const ok = await addItem(product);
    if (ok) {
      triggerFlyAnimation();
    }
  };

  useEffect(() => {
    if (loading || !product?.slug) {
      return;
    }
    if (lastTrackedSlug.current === product.slug) {
      return;
    }
    lastTrackedSlug.current = product.slug;
    track(product.slug);
  }, [loading, product?.slug, track]);

  useEffect(() => {
    if (variants.length === 0) {
      setSelectedVariantId(null);
      return;
    }
    if (selectedVariantId && variants.some((variant) => variant.id === selectedVariantId)) {
      return;
    }
    setSelectedVariantId(variants[0].id);
  }, [selectedVariantId, variants]);

  useEffect(() => {
    const maxX = Math.max(width, 1) - cartSize - cartPadding;
    const maxY = Math.max(height, 1) - cartSize - cartPadding - cartBottomInset;
    cartX.value = maxX;
    cartY.value = maxY;
    cartMaxX.value = maxX;
    cartMaxY.value = maxY;
  }, [cartBottomInset, cartPadding, cartSize, cartMaxX, cartMaxY, cartX, cartY, height, width]);

  if (loading) {
    return (
      <View style={styles.loaderWrap}>
        <Skeleton height={theme.moderateScale(430)} radius={0} />
        <View style={styles.skeletonBody}>
          <Skeleton height={theme.moderateScale(18)} radius={theme.moderateScale(8)} width="70%" />
          <Skeleton height={theme.moderateScale(12)} radius={theme.moderateScale(6)} width="40%" />
          <Skeleton height={theme.moderateScale(20)} radius={theme.moderateScale(10)} width="35%" />
          <Skeleton height={theme.moderateScale(12)} radius={theme.moderateScale(6)} width="90%" />
          <Skeleton height={theme.moderateScale(12)} radius={theme.moderateScale(6)} width="80%" />
        </View>
      </View>
    );
  }

  if (!product) {
    return (
      <View style={styles.loaderWrap}>
        <Text style={styles.emptyText}>Product not found</Text>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <ScrollView style={styles.scroll} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.imageWrap} ref={imageWrapRef}>
          <FlatList
            ref={sliderRef}
            data={imageSlides}
            horizontal
            pagingEnabled
            keyExtractor={(item, index) => `${item}-${index}`}
            style={styles.slider}
            showsHorizontalScrollIndicator={false}
            getItemLayout={(_, index) => ({
              length: Math.max(width, 1),
              offset: Math.max(width, 1) * index,
              index,
            })}
            onMomentumScrollEnd={(event) => {
              const nextIndex = Math.round(event.nativeEvent.contentOffset.x / Math.max(width, 1));
              setActiveIndex(nextIndex);
            }}
            renderItem={({ item, index }) => (
              <View style={[styles.slide, { width: Math.max(width, 1) }]}>
                <Pressable
                  style={styles.slidePressable}
                  onPress={() => openViewer(index)}
                  accessibilityRole="button"
                  accessibilityLabel="Open image viewer"
                >
                  <Image source={{ uri: item }} style={styles.image} />
                </Pressable>
              </View>
            )}
          />
          <View style={styles.topActions}>
            <Pressable
              style={styles.iconButton}
              onPress={handleClose}
              accessibilityRole="button"
              accessibilityLabel={closeLabel}
            >
              <Feather name={closeIcon} size={18} color={theme.colors.inkDark} />
            </Pressable>
            <Pressable
              style={styles.iconButton}
              onPress={handleShare}
              accessibilityRole="button"
              accessibilityLabel="Share product"
            >
              <Feather name="share-2" size={18} color={theme.colors.inkDark} />
            </Pressable>
          </View>
          {displayCompareAt ? (
            <View style={styles.saleBadge}>
              <Text style={styles.saleText}>Sale</Text>
            </View>
          ) : null}
        </View>
        <View style={styles.controls}>
          {imageSlides.map((_, index) => (
            <View
              key={`control-${index}`}
              style={index === activeIndex ? styles.controlActive : styles.controlDot}
            />
          ))}
        </View>

        <View style={styles.body}>
          <View style={styles.titleRow}>
            <Text style={styles.name}>{product.name}</Text>
            <Pressable
              style={styles.likeButtonSmall}
              onPress={handleToggle}
            >
              <Feather
                name={isWishlisted ? 'heart' : 'heart'}
                size={14}
                color={isWishlisted ? theme.colors.pink : theme.colors.inkDark}
              />
            </Pressable>
          </View>

          <View style={styles.ratingRow}>
            {[0, 1, 2, 3, 4].map((item) => (
              <Feather key={`star-${item}`} name="star" size={14} color={theme.colors.inkDark} />
            ))}
            <Text style={styles.ratingText}>{product.rating.toFixed(1)}</Text>
            <Pressable
              onPress={() =>
                router.push(`/products/reviews${slugParam ? `?slug=${slugParam}` : ''}`)
              }
            >
              <Text style={styles.reviewLink}>({product.reviews} reviews)</Text>
            </Pressable>
          </View>

          <View style={styles.priceRow}>
            <Text style={styles.price}>${displayPrice.toFixed(2)}</Text>
            {displayCompareAt ? (
              <View style={styles.compareRow}>
                <Text style={styles.compareAt}>${displayCompareAt.toFixed(2)}</Text>
                <View style={styles.savePill}>
                  <Text style={styles.saveText}>Save 20%</Text>
                </View>
              </View>
            ) : null}
          </View>

          <Text style={styles.sectionTitle}>Description</Text>
          <View style={styles.descriptionBlock}>
            {descriptionBlocks.map((paragraph) => (
              <Text key={paragraph} style={styles.descriptionParagraph}>
                {paragraph}
              </Text>
            ))}
            {descriptionImages.length > 0 ? (
              <View style={styles.descriptionGallery}>
                <View style={styles.descriptionImageRow}>
                  {descriptionImages.slice(0, 2).map((uri, index) => (
                    <Pressable
                      key={`desc-img-${index}`}
                      onPress={() => {
                        const nextIndex = imageSlides.findIndex((slide) => slide === uri);
                        openViewer(nextIndex >= 0 ? nextIndex : 0);
                      }}
                    >
                      <Image source={{ uri }} style={styles.descriptionImageSmall} />
                    </Pressable>
                  ))}
                </View>
                {descriptionImages[2] ? (
                  <Pressable
                    onPress={() => {
                      const nextIndex = imageSlides.findIndex((slide) => slide === descriptionImages[2]);
                      openViewer(nextIndex >= 0 ? nextIndex : 0);
                    }}
                  >
                    <Image source={{ uri: descriptionImages[2] }} style={styles.descriptionImageLarge} />
                  </Pressable>
                ) : null}
              </View>
            ) : null}
          </View>

          {variantOptions.length > 0 ? (
            <>
              <View style={styles.sectionHeader}>
                <Text style={styles.sectionTitle}>Variants</Text>
                <Pressable
                  onPress={() =>
                    router.push(`/products/variations${slugParam ? `?slug=${slugParam}` : ''}`)
                  }
                >
                  <Text style={styles.sectionNote}>{variantOptions.length} options</Text>
                </Pressable>
              </View>
              <View style={styles.sizeRow}>
                {variantOptions.map((variant) => {
                  const isActive = variant.id === selectedVariantId;
                  return (
                    <Pressable
                      key={String(variant.id)}
                      style={[styles.sizeChip, isActive ? styles.sizeChipActive : null]}
                      onPress={() => setSelectedVariantId(variant.id)}
                    >
                      <Text style={[styles.sizeText, isActive ? styles.sizeTextActive : null]}>
                        {variant.label}
                      </Text>
                    </Pressable>
                  );
                })}
              </View>
            </>
          ) : null}

          <View style={styles.thumbRow}>
            {imageSlides.slice(0, 3).map((uri, index) => (
              <Pressable
                key={`thumb-${uri}-${index}`}
                onPress={() => {
                  setActiveIndex(index);
                  sliderRef.current?.scrollToIndex({ index, animated: true });
                }}
              >
                <Image
                  source={{ uri }}
                  style={[styles.thumb, index === activeIndex ? styles.thumbActive : null]}
                />
              </Pressable>
            ))}
          </View>

          {showExtended ? (
            <>
              <Text style={styles.sectionTitle}>Specifications</Text>
              <Text style={styles.sectionLabel}>Material</Text>
              <View style={styles.materialRow}>
                {materials.map((item) => (
                  <View key={item} style={styles.materialChip}>
                    <Text style={styles.materialText}>{item}</Text>
                  </View>
                ))}
              </View>

              <View style={styles.detailCard}>
                <Text style={styles.sectionTitle}>Details</Text>
                {detailItems.map((item) => (
                  <View key={item} style={styles.detailRow}>
                    <View style={styles.detailDot} />
                    <Text style={styles.detailText}>{item}</Text>
                  </View>
                ))}
              </View>

              <View style={styles.reviewCard}>
                <View style={styles.reviewHeader}>
                  <Text style={styles.sectionTitle}>Rating & Reviews</Text>
                  <Pressable
                    onPress={() =>
                      router.push(`/products/reviews${slugParam ? `?slug=${slugParam}` : ''}`)
                    }
                  >
                    <Text style={styles.linkText}>View all</Text>
                  </Pressable>
                </View>
                <View style={styles.ratingRowLarge}>
                  {[0, 1, 2, 3, 4].map((item) => (
                    <Feather key={`star-large-${item}`} name="star" size={18} color={theme.colors.inkDark} />
                  ))}
                <View style={styles.ratingBadge}>
                    <Text style={styles.ratingBadgeText}>{Math.round(product.rating)}/5</Text>
                </View>
              </View>
                <View style={styles.reviewSnippet}>
                  <View style={styles.reviewAvatar} />
                  <View style={styles.reviewBody}>
                    <Text style={styles.reviewName}>Veronika</Text>
                    <Text style={styles.reviewText}>
                      {product.reviews > 0
                        ? 'See what shoppers are saying about this item.'
                        : 'Be the first to review this product.'}
                    </Text>
                  </View>
                </View>
              </View>

              {youMayLike.length > 0 ? (
                <View style={styles.mayLikeCard}>
                  <Text style={styles.sectionTitle}>You May Like</Text>
                  <ScrollView
                    horizontal
                    showsHorizontalScrollIndicator={false}
                    contentContainerStyle={styles.horizontalRow}
                  >
                    {youMayLike.map((item) => (
                      <Pressable
                        key={item.id}
                        style={styles.popularCard}
                        onPress={() => router.push(`/products/${item.slug}`)}
                      >
                        {item.image ? (
                          <Image source={{ uri: item.image }} style={styles.popularImage} />
                        ) : (
                          <View style={styles.popularImage} />
                        )}
                        <Text style={styles.popularCount}>{item.reviews}</Text>
                        <Text style={styles.popularLabel}>{item.badge ?? 'New'}</Text>
                      </Pressable>
                    ))}
                  </ScrollView>
                </View>
              ) : null}
            </>
          ) : null}
        </View>
      </ScrollView>

      <View style={styles.bottomBar}>
        <Pressable
          style={styles.likeButton}
          onPress={handleToggle}
        >
          <Feather
            name="heart"
            size={16}
            color={isWishlisted ? theme.colors.pink : theme.colors.inkDark}
          />
        </Pressable>
        <Pressable style={styles.addButton} onPress={handleAddToCart}>
          <Text style={styles.addText}>Add to cart</Text>
        </Pressable>
        <Pressable style={styles.buyButton} onPress={() => router.push('/checkout')}>
          <Text style={styles.buyText}>Buy now</Text>
        </Pressable>
      </View>

      {flyVisible && flyImage ? (
        <Animated.Image
          pointerEvents="none"
          source={{ uri: flyImage }}
          style={[styles.flyImage, flyStyle]}
        />
      ) : null}

      <GestureDetector gesture={cartDragGesture}>
        <Animated.View style={[styles.floatingCartWrap, cartDragStyle]}>
          <View ref={cartFloatRef}>
            <Pressable
              style={styles.floatingCartButton}
              onPress={() => router.push('/(tabs)/cart')}
              accessibilityRole="button"
              accessibilityLabel="Open cart"
            >
              <Feather name="shopping-bag" size={20} color={theme.colors.white} />
              {cartCount > 0 ? (
                <View style={styles.floatingBadge}>
                  <Text style={styles.floatingBadgeText}>{cartCount}</Text>
                </View>
              ) : null}
            </Pressable>
          </View>
        </Animated.View>
      </GestureDetector>

      <Modal
        visible={viewerVisible}
        onRequestClose={() => setViewerVisible(false)}
        animationType="fade"
        presentationStyle="fullScreen"
      >
        <View style={styles.viewerBackdrop}>
          <FlatList
            data={imageSlides}
            horizontal
            pagingEnabled
            keyExtractor={(item, index) => `${item}-viewer-${index}`}
            showsHorizontalScrollIndicator={false}
            initialScrollIndex={viewerIndex}
            getItemLayout={(_, index) => ({
              length: Math.max(width, 1),
              offset: Math.max(width, 1) * index,
              index,
            })}
            onMomentumScrollEnd={(event) => {
              const nextIndex = Math.round(event.nativeEvent.contentOffset.x / Math.max(width, 1));
              setViewerIndex(nextIndex);
            }}
            renderItem={({ item }) => (
              <ZoomableImage uri={item} width={width} height={height} />
            )}
          />
          <Pressable
            style={styles.viewerClose}
            onPress={() => setViewerVisible(false)}
            accessibilityRole="button"
            accessibilityLabel="Close image viewer"
          >
            <Feather name="x" size={20} color={theme.colors.white} />
          </Pressable>
          <View style={styles.viewerCounter}>
            <Text style={styles.viewerCounterText}>
              {imageSlides.length > 0 ? `${viewerIndex + 1} / ${imageSlides.length}` : ''}
            </Text>
          </View>
        </View>
      </Modal>
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
    paddingBottom: 120,
  },
  loaderWrap: {
    flex: 1,
    backgroundColor: theme.colors.white,
    paddingBottom: theme.moderateScale(20),
  },
  skeletonBody: {
    paddingHorizontal: theme.moderateScale(16),
    paddingTop: theme.moderateScale(16),
    gap: theme.moderateScale(10),
  },
  emptyText: {
    color: theme.colors.inkDark,
  },
  imageWrap: {
    height: 430,
    backgroundColor: theme.colors.sand,
  },
  slider: {
    height: '100%',
  },
  slide: {
    height: '100%',
  },
  image: {
    width: '100%',
    height: '100%',
  },
  slidePressable: {
    width: '100%',
    height: '100%',
  },
  topActions: {
    position: 'absolute',
    top: 16,
    left: 16,
    right: 16,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  iconButton: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: theme.colors.white,
    alignItems: 'center',
    justifyContent: 'center',
    shadowColor: '#0b0b0d',
    shadowOpacity: 0.08,
    shadowRadius: 10,
    shadowOffset: { width: 0, height: 6 },
    elevation: 6,
  },
  saleBadge: {
    position: 'absolute',
    bottom: 16,
    left: 16,
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 14,
    backgroundColor: theme.colors.sun,
  },
  saleText: {
    fontSize: 12,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  viewerBackdrop: {
    flex: 1,
    backgroundColor: '#0b0b0d',
  },
  viewerSlide: {
    justifyContent: 'center',
    alignItems: 'center',
  },
  viewerImage: {
    resizeMode: 'contain',
  },
  viewerClose: {
    position: 'absolute',
    top: 54,
    right: 20,
    width: 38,
    height: 38,
    borderRadius: 19,
    backgroundColor: 'rgba(0,0,0,0.55)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  viewerCounter: {
    position: 'absolute',
    bottom: 28,
    alignSelf: 'center',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 12,
    backgroundColor: 'rgba(0,0,0,0.6)',
  },
  viewerCounterText: {
    color: theme.colors.white,
    fontSize: 12,
    fontWeight: '600',
  },
  flyImage: {
    position: 'absolute',
    width: theme.moderateScale(56),
    height: theme.moderateScale(56),
    borderRadius: theme.moderateScale(12),
    zIndex: 40,
  },
  floatingCartWrap: {
    position: 'absolute',
    zIndex: 30,
  },
  floatingCartButton: {
    width: theme.moderateScale(56),
    height: theme.moderateScale(56),
    borderRadius: theme.moderateScale(28),
    backgroundColor: theme.colors.inkDark,
    alignItems: 'center',
    justifyContent: 'center',
    shadowColor: '#0b0b0d',
    shadowOpacity: 0.2,
    shadowRadius: 10,
    shadowOffset: { width: 0, height: 6 },
    elevation: 8,
  },
  floatingBadge: {
    position: 'absolute',
    top: -6,
    right: -6,
    minWidth: theme.moderateScale(20),
    height: theme.moderateScale(20),
    borderRadius: theme.moderateScale(10),
    backgroundColor: theme.colors.sun,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: theme.moderateScale(4),
  },
  floatingBadgeText: {
    fontSize: theme.moderateScale(10),
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  controls: {
    flexDirection: 'row',
    gap: 8,
    justifyContent: 'center',
    marginTop: 12,
  },
  controlActive: {
    width: 40,
    height: 10,
    borderRadius: 5,
    backgroundColor: theme.colors.sun,
  },
  controlDot: {
    width: 10,
    height: 10,
    borderRadius: 5,
    backgroundColor: theme.colors.sand,
  },
  priceRow: {
    marginTop: 12,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  price: {
    fontSize: 22,
    fontWeight: '800',
    color: theme.colors.black,
  },
  body: {
    marginTop: 18,
    paddingHorizontal: 16,
    paddingBottom: 16,
  },
  titleRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    gap: 12,
  },
  name: {
    flex: 1,
    fontSize: 18,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  likeButtonSmall: {
    width: 30,
    height: 30,
    borderRadius: 15,
    backgroundColor: theme.colors.sand,
    alignItems: 'center',
    justifyContent: 'center',
  },
  ratingRow: {
    marginTop: 10,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  ratingText: {
    fontSize: 12,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  reviewLink: {
    fontSize: 12,
    color: theme.colors.mutedDark,
  },
  compareRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  compareAt: {
    fontSize: 12,
    color: theme.colors.mutedDark,
    textDecorationLine: 'line-through',
  },
  savePill: {
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 12,
    backgroundColor: theme.colors.sand,
  },
  saveText: {
    fontSize: 10,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  sectionHeader: {
    marginTop: 16,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  sectionTitle: {
    fontSize: 14,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  sectionNote: {
    fontSize: 12,
    color: theme.colors.mutedDark,
  },
  descriptionBlock: {
    marginTop: 8,
  },
  descriptionParagraph: {
    marginBottom: 10,
    fontSize: 12,
    lineHeight: 18,
    color: theme.colors.inkDark,
  },
  descriptionGallery: {
    marginTop: 6,
  },
  descriptionImageRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 10,
  },
  descriptionImageSmall: {
    width: '48%',
    height: 120,
    borderRadius: 16,
    backgroundColor: theme.colors.sand,
  },
  descriptionImageLarge: {
    width: '100%',
    height: 190,
    borderRadius: 18,
    backgroundColor: theme.colors.sand,
  },
  colorRow: {
    marginTop: 10,
    flexDirection: 'row',
    gap: 12,
  },
  colorWrap: {
    width: 28,
    height: 28,
    borderRadius: 14,
    backgroundColor: theme.colors.white,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: theme.colors.sand,
  },
  colorDot: {
    width: 18,
    height: 18,
    borderRadius: 9,
  },
  colorActive: {
    borderWidth: 2,
    borderColor: theme.colors.inkDark,
  },
  sizeRow: {
    marginTop: 10,
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
  },
  sizeChip: {
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 16,
    backgroundColor: theme.colors.sand,
  },
  sizeChipActive: {
    backgroundColor: theme.colors.sun,
  },
  sizeText: {
    fontSize: 12,
    color: theme.colors.inkDark,
    fontWeight: '600',
  },
  sizeTextActive: {
    color: theme.colors.inkDark,
  },
  thumbRow: {
    marginTop: 14,
    flexDirection: 'row',
    gap: 10,
  },
  thumb: {
    width: 72,
    height: 72,
    borderRadius: 14,
    backgroundColor: theme.colors.sand,
  },
  thumbActive: {
    borderWidth: 2,
    borderColor: theme.colors.orange,
  },
  sectionLabel: {
    marginTop: 10,
    fontSize: 12,
    color: theme.colors.mutedDark,
  },
  materialRow: {
    marginTop: 10,
    flexDirection: 'row',
    gap: 10,
  },
  materialChip: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 12,
    backgroundColor: theme.colors.sand,
  },
  materialText: {
    fontSize: 12,
    color: theme.colors.inkDark,
  },
  detailCard: {
    marginTop: 16,
    paddingVertical: 8,
  },
  detailRow: {
    marginTop: 8,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  detailDot: {
    width: 6,
    height: 6,
    borderRadius: 3,
    backgroundColor: theme.colors.orange,
  },
  detailText: {
    fontSize: 12,
    color: theme.colors.inkDark,
  },
  reviewCard: {
    marginTop: 18,
    paddingVertical: 8,
  },
  reviewHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  linkText: {
    fontSize: 12,
    color: theme.colors.orange,
    fontWeight: '600',
  },
  ratingRowLarge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginTop: 10,
  },
  ratingBadge: {
    marginLeft: 10,
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 10,
    backgroundColor: theme.colors.sand,
  },
  ratingBadgeText: {
    fontSize: 12,
    color: theme.colors.inkDark,
  },
  reviewSnippet: {
    marginTop: 12,
    flexDirection: 'row',
    gap: 10,
  },
  reviewAvatar: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: theme.colors.sand,
  },
  reviewBody: {
    flex: 1,
  },
  reviewName: {
    fontSize: 13,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  reviewText: {
    marginTop: 6,
    fontSize: 12,
    color: theme.colors.inkDark,
  },
  mayLikeCard: {
    marginTop: 18,
  },
  horizontalRow: {
    paddingTop: 12,
    paddingBottom: 4,
  },
  popularCard: {
    width: 104,
    marginRight: 12,
  },
  popularImage: {
    width: 104,
    height: 140,
    borderRadius: 16,
    backgroundColor: theme.colors.sand,
  },
  popularCount: {
    marginTop: 6,
    fontSize: 12,
    color: theme.colors.inkDark,
    fontWeight: '700',
  },
  popularLabel: {
    fontSize: 12,
    color: theme.colors.mutedDark,
  },
  bottomBar: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
    height: 84,
    backgroundColor: theme.colors.white,
    borderTopWidth: 1,
    borderTopColor: theme.colors.sand,
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 20,
    gap: 10,
  },
  likeButton: {
    width: 47,
    height: 40,
    borderRadius: 14,
    backgroundColor: theme.colors.sand,
    alignItems: 'center',
    justifyContent: 'center',
  },
  addButton: {
    flex: 1,
    height: 40,
    borderRadius: 20,
    backgroundColor: theme.colors.sun,
    alignItems: 'center',
    justifyContent: 'center',
  },
  addText: {
    color: theme.colors.inkDark,
    fontSize: 14,
    fontWeight: '600',
  },
  buyButton: {
    flex: 1,
    height: 40,
    borderRadius: 20,
    backgroundColor: theme.colors.orange,
    alignItems: 'center',
    justifyContent: 'center',
  },
  buyText: {
    color: theme.colors.gray200,
    fontSize: 14,
    fontWeight: '600',
  },
});
