import { Dimensions, Image, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useState } from 'react';
import { theme } from '@/constants/theme';
import { PromoSlide } from '@/lib/mockData';

const screenWidth = Dimensions.get('window').width;

export const PromoCarousel = ({
  slides,
  onPress,
}: {
  slides: PromoSlide[];
  onPress?: (slide: PromoSlide) => void;
}) => {
  const [activeIndex, setActiveIndex] = useState(0);

  return (
    <View>
      <ScrollView
        horizontal
        pagingEnabled
        showsHorizontalScrollIndicator={false}
        onMomentumScrollEnd={(event) => {
          const index = Math.round(event.nativeEvent.contentOffset.x / screenWidth);
          setActiveIndex(index);
        }}
      >
        {slides.map((slide) => (
          <View key={slide.id} style={{ width: screenWidth }}>
            <Pressable
              style={[styles.card, { backgroundColor: slide.tone }]}
              onPress={() => onPress?.(slide)}
            >
              <View style={styles.copy}>
                <Text style={styles.kicker}>{slide.kicker ?? 'Hot drop'}</Text>
                <Text style={styles.title}>{slide.title}</Text>
                <Text style={styles.subtitle}>{slide.subtitle}</Text>
                <View style={styles.cta}>
                  <Text style={styles.ctaText}>{slide.cta}</Text>
                </View>
              </View>
              {slide.image ? (
                <Image source={{ uri: slide.image }} style={styles.image} />
              ) : (
                <View style={styles.imageFallback}>
                  <Text style={styles.imageFallbackText}>{slide.title.slice(0, 1).toUpperCase()}</Text>
                </View>
              )}
            </Pressable>
          </View>
        ))}
      </ScrollView>
      <View style={styles.dots}>
        {slides.map((slide, index) => (
          <View key={slide.id} style={[styles.dot, index === activeIndex && styles.dotActive]} />
        ))}
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  card: {
    marginHorizontal: theme.spacing.lg,
    padding: theme.spacing.md,
    borderRadius: theme.radius.lg,
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.sm,
  },
  copy: {
    flex: 1,
  },
  kicker: {
    textTransform: 'uppercase',
    letterSpacing: 1.8,
    fontSize: 10,
    fontWeight: '700',
    color: theme.colors.muted,
  },
  title: {
    fontSize: 16,
    fontWeight: '800',
    color: theme.colors.ink,
    marginTop: 6,
  },
  subtitle: {
    fontSize: 12,
    color: theme.colors.muted,
    marginTop: 6,
  },
  cta: {
    marginTop: theme.spacing.sm,
    backgroundColor: theme.colors.ink,
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: theme.radius.pill,
    alignSelf: 'flex-start',
  },
  ctaText: {
    color: '#fff',
    fontSize: 11,
    fontWeight: '700',
  },
  image: {
    width: 120,
    height: 120,
    borderRadius: theme.radius.md,
  },
  imageFallback: {
    width: 120,
    height: 120,
    borderRadius: theme.radius.md,
    backgroundColor: theme.colors.surface,
    alignItems: 'center',
    justifyContent: 'center',
  },
  imageFallbackText: {
    fontSize: 18,
    fontWeight: '700',
    color: theme.colors.ink,
  },
  dots: {
    flexDirection: 'row',
    justifyContent: 'center',
    gap: 8,
    marginTop: theme.spacing.sm,
  },
  dot: {
    width: 8,
    height: 8,
    borderRadius: theme.radius.pill,
    backgroundColor: theme.colors.border,
  },
  dotActive: {
    width: 20,
    backgroundColor: theme.colors.brandCoral,
  },
});
