import { useEffect, useRef, useState } from 'react';
import { router } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { FlatList, Linking, Pressable, StyleSheet, View, useWindowDimensions } from 'react-native';
import { OnboardingBackground } from '@/src/components/onboarding/OnboardingBackground';
import { OnboardingCard } from '@/src/components/onboarding/OnboardingCard';
import { PaginationDots } from '@/src/components/onboarding/PaginationDots';
import { theme } from '@/src/theme';
import { useAuth } from '@/lib/authStore';
import { fetchOnboardingSettings } from '@/src/api/onboarding';

type Slide = {
  key: string;
  background: 'hello' | 'ready';
  title: string;
  body: string;
  image?: string | null;
  imageColors: [string, string];
  actionHref?: string | null;
};

const defaultSlides: Slide[] = [
  {
    key: 'hello',
    background: 'hello',
    title: 'Welcome',
    body: 'Discover trending styles and everyday essentials — delivered to your door.',
    imageColors: ['#ffcad9', '#f39db0'],
  },
  {
    key: 'ready',
    background: 'ready',
    title: 'Ready to shop?',
    body: 'Search, filter, and save your favorites. Checkout is fast and secure.',
    imageColors: ['#8ec5ff', '#f5b7c6'],
    actionHref: '/(tabs)/home',
  },
];

export default function OnboardingSliderScreen() {
  const { status } = useAuth();
  const listRef = useRef<FlatList<Slide> | null>(null);
  const { width } = useWindowDimensions();
  const [pageIndex, setPageIndex] = useState(0);
  const [slides, setSlides] = useState<Slide[]>(defaultSlides);

  const backgroundVariant = slides[pageIndex]?.background ?? 'hello';

  useEffect(() => {
    if (status === 'authed') {
      router.replace('/(tabs)/home');
    }
  }, [status]);

  useEffect(() => {
    let active = true;

    fetchOnboardingSettings()
      .then((settings) => {
        if (!active) return;
        if (settings.configured) {
          if (!settings.enabled || settings.slides.length === 0) {
            router.replace('/(tabs)/home');
            return;
          }
          setSlides(settings.slides);
          setPageIndex(0);
          listRef.current?.scrollToOffset({ offset: 0, animated: false });
        }
      })
      .catch(() => {});

    return () => {
      active = false;
    };
  }, []);

  const openHref = (href?: string | null) => {
    if (!href) return;
    if (href.startsWith('http://') || href.startsWith('https://')) {
      Linking.openURL(href).catch(() => {});
      return;
    }
    router.replace(href as any);
  };

  const goNext = () => {
    const next = Math.min(slides.length - 1, pageIndex + 1);
    listRef.current?.scrollToOffset({ offset: next * width, animated: true });
    setPageIndex(next);
  };

  return (
    <SafeAreaView style={styles.container}>
      <OnboardingBackground variant={backgroundVariant} />

      <FlatList
        ref={listRef}
        horizontal
        pagingEnabled
        data={slides}
        keyExtractor={(item) => item.key}
        showsHorizontalScrollIndicator={false}
        onMomentumScrollEnd={(e) => {
          const next = Math.round(e.nativeEvent.contentOffset.x / width);
          setPageIndex(Math.max(0, Math.min(slides.length - 1, next)));
        }}
        renderItem={({ item, index }) => (
          <View style={[styles.slide, { width }]}>
            <Pressable
              style={styles.cardWrap}
              onPress={() => {
                if (index < slides.length - 1) {
                  goNext();
                }
              }}
            >
              <OnboardingCard
                title={item.title}
                body={item.body}
                imageUri={item.image ?? undefined}
                imageColors={item.imageColors}
                actionLabel={index === slides.length - 1 ? 'Start shopping' : undefined}
                onAction={index === slides.length - 1 ? () => openHref(item.actionHref ?? '/(tabs)/home') : undefined}
              />
            </Pressable>
          </View>
        )}
      />

      <View style={styles.dots}>
        <PaginationDots total={slides.length} activeIndex={pageIndex} />
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.white,
    justifyContent: 'center',
  },
  slide: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: theme.spacing.lg,
    paddingBottom: theme.spacing.lg,
  },
  cardWrap: {
    width: '100%',
    marginBottom: theme.spacing.lg,
  },
  dots: {
    alignItems: 'center',
    paddingBottom: theme.spacing.lg,
  },
});
