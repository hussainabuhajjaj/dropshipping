import { useEffect, useRef, useState } from 'react';
import { router } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { FlatList, Pressable, StyleSheet, View, useWindowDimensions } from 'react-native';
import { OnboardingBackground } from '@/src/components/onboarding/OnboardingBackground';
import { OnboardingCard } from '@/src/components/onboarding/OnboardingCard';
import { PaginationDots } from '@/src/components/onboarding/PaginationDots';
import { theme } from '@/src/theme';
import { useAuth } from '@/lib/authStore';

type Slide = {
  key: string;
  background: 'hello' | 'ready';
  title: string;
  body: string;
  imageColors: [string, string];
  actionLabel?: string;
  onAction?: () => void;
};

const slides: Slide[] = [
  {
    key: 'hello',
    background: 'hello',
    title: 'Hello',
    body: 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed non consectetur turpis. Morbi eu eleifend lacus.',
    imageColors: ['#ffcad9', '#f39db0'],
  },
  {
    key: 'ready',
    background: 'ready',
    title: 'Ready?',
    body: 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
    imageColors: ['#8ec5ff', '#f5b7c6'],
    actionLabel: "Let's Start",
    onAction: () => router.replace('/(tabs)/home'),
  },
];

export default function OnboardingSliderScreen() {
  const { status } = useAuth();
  const listRef = useRef<FlatList<Slide> | null>(null);
  const { width } = useWindowDimensions();
  const [pageIndex, setPageIndex] = useState(0);

  const backgroundVariant = slides[pageIndex]?.background ?? 'hello';

  useEffect(() => {
    if (status === 'authed') {
      router.replace('/(tabs)/home');
    }
  }, [status]);

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
                imageColors={item.imageColors}
                actionLabel={item.actionLabel}
                onAction={item.onAction}
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
