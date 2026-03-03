import { Image, Pressable, StyleSheet, Text, View, Dimensions } from 'react-native';
import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { theme } from '@/src/theme';
import type { Story } from '@/src/api/stories';
import { getStoryLayout } from '@/src/config/storyLayouts';
import { useEffect, useState } from 'react';

const { width: SCREEN_WIDTH, height: SCREEN_HEIGHT } = Dimensions.get('window');

type StoryViewerProps = {
  story: Story;
  onClose?: () => void;
  onNext?: () => void;
  onPrevious?: () => void;
};

export const StoryViewer = ({ story, onClose, onNext, onPrevious }: StoryViewerProps) => {
  const config = getStoryLayout(story.storyType);
  const [progress, setProgress] = useState(0);

  useEffect(() => {
    const duration = 5000; // 5 seconds
    const interval = 50;
    const increment = (interval / duration) * 100;

    const timer = setInterval(() => {
      setProgress((prev) => {
        if (prev >= 100) {
          clearInterval(timer);
          onNext?.();
          return 100;
        }
        return prev + increment;
      });
    }, interval);

    return () => clearInterval(timer);
  }, [story.id, onNext]);

  const handlePress = (side: 'left' | 'right') => {
    if (side === 'left') {
      onPrevious?.();
    } else {
      onNext?.();
    }
  };

  const handleCTA = () => {
    if (story.ctaUrl) {
      router.push(story.ctaUrl as any);
    } else if (story.productId) {
      router.push(`/products/${story.productId}` as any);
    } else if (story.categoryId) {
      router.push(`/categories/${story.categoryId}` as any);
    }
  };

  const getStringValue = (value: unknown): string => {
    return typeof value === 'string' ? value : String(value || '');
  };

  const getBoolValue = (value: unknown): boolean => {
    return Boolean(value);
  };

  const renderBadge = () => {
    if (!config.showBadge || !story.badgeText) return null;

    const badgeStyles = [
      styles.badge,
      config.badgePosition === 'top-left' && styles.badgeTopLeft,
      config.badgePosition === 'top-right' && styles.badgeTopRight,
      config.badgePosition === 'bottom' && styles.badgeBottom,
      config.badgePosition === 'center' && styles.badgeCenter,
      { backgroundColor: story.badgeColor || theme.colors.danger },
    ];

    return (
      <View style={badgeStyles}>
        <Text style={styles.badgeText}>{story.badgeText}</Text>
      </View>
    );
  };

  const renderContent = () => {
    const content = story.storyContent;

    switch (story.storyType) {
      case 'offer':
        return (
          <View style={styles.contentOverlay}>
            {content.discount_percent && (
              <Text style={styles.discountText}>{getStringValue(content.discount_percent)}% OFF</Text>
            )}
            <Text style={styles.titleText}>{story.title}</Text>
            {story.description && <Text style={styles.descriptionText}>{story.description}</Text>}
            {content.discount_code && (
              <View style={styles.codeBox}>
                <Text style={styles.codeLabel}>Use code:</Text>
                <Text style={styles.codeText}>{getStringValue(content.discount_code)}</Text>
              </View>
            )}
            {content.expires_at && (
              <Text style={styles.expiryText}>Expires: {getStringValue(content.expires_at)}</Text>
            )}
          </View>
        );

      case 'promotion':
        return (
          <View style={styles.contentOverlay}>
            {content.tagline && <Text style={styles.taglineText}>{getStringValue(content.tagline)}</Text>}
            <Text style={styles.titleText}>{story.title}</Text>
            {story.description && <Text style={styles.descriptionText}>{story.description}</Text>}
            {content.highlight && <Text style={styles.highlightText}>{getStringValue(content.highlight)}</Text>}
          </View>
        );

      case 'seasonal':
        return (
          <View style={styles.contentOverlay}>
            {content.season && <Text style={styles.seasonText}>{getStringValue(content.season).toUpperCase()}</Text>}
            <Text style={styles.titleText}>{story.title}</Text>
            {content.collection_name && (
              <Text style={styles.collectionText}>{getStringValue(content.collection_name)}</Text>
            )}
            {getBoolValue(content.limited_edition) && (
              <View style={styles.limitedBadge}>
                <Text style={styles.limitedText}>LIMITED EDITION</Text>
              </View>
            )}
          </View>
        );

      case 'product':
        return (
          <View style={styles.contentOverlay}>
            <Text style={styles.titleText}>{story.title}</Text>
            {story.description && <Text style={styles.descriptionText}>{story.description}</Text>}
            {content.highlight_feature && (
              <Text style={styles.featureText}>✨ {getStringValue(content.highlight_feature)}</Text>
            )}
            {config.showPrice && content.price_display && (
              <Text style={styles.priceText}>{getStringValue(content.price_display)}</Text>
            )}
          </View>
        );

      case 'announcement':
        return (
          <View style={styles.contentCard}>
            <Feather name="bell" size={32} color={theme.colors.primary} />
            <Text style={styles.titleText}>{story.title}</Text>
            {story.description && <Text style={styles.descriptionText}>{story.description}</Text>}
            {content.priority && (
              <View style={[styles.priorityBadge, { backgroundColor: getPriorityColor(getStringValue(content.priority)) }]}>
                <Text style={styles.priorityText}>{getStringValue(content.priority).toUpperCase()}</Text>
              </View>
            )}
          </View>
        );

      default:
        return (
          <View style={styles.contentOverlay}>
            <Text style={styles.titleText}>{story.title}</Text>
            {story.description && <Text style={styles.descriptionText}>{story.description}</Text>}
          </View>
        );
    }
  };

  const getPriorityColor = (priority: string) => {
    switch (priority) {
      case 'urgent': return '#ff0000';
      case 'high': return '#ff6b00';
      case 'medium': return '#ffa500';
      case 'low': return '#00bfff';
      default: return theme.colors.primary;
    }
  };

  return (
    <View style={styles.container}>
      {/* Progress Bar */}
      <View style={styles.progressBar}>
        <View style={[styles.progressFill, { width: `${progress}%` }]} />
      </View>

      {/* Close Button */}
      <Pressable style={styles.closeButton} onPress={onClose}>
        <Feather name="x" size={24} color="#fff" />
      </Pressable>

      {/* Background Image */}
      {story.image ? (
        <Image source={{ uri: story.image }} style={styles.backgroundImage} />
      ) : (
        <View style={[styles.backgroundFallback, { backgroundColor: story.backgroundColor || theme.colors.sand }]} />
      )}

      {/* Touch Areas for Navigation */}
      <View style={styles.touchContainer}>
        <Pressable style={styles.touchLeft} onPress={() => handlePress('left')} />
        <Pressable style={styles.touchRight} onPress={() => handlePress('right')} />
      </View>

      {/* Badge */}
      {renderBadge()}

      {/* Content */}
      {renderContent()}

      {/* CTA Button */}
      {story.ctaText && (
        <Pressable style={styles.ctaButton} onPress={handleCTA}>
          <Text style={styles.ctaText}>{story.ctaText}</Text>
          <Feather name="arrow-right" size={18} color="#fff" />
        </Pressable>
      )}
    </View>
  );
};

const getPriorityColor = (priority: string) => {
  switch (priority) {
    case 'urgent': return '#ff0000';
    case 'high': return '#ff6b00';
    case 'medium': return '#ffa500';
    case 'low': return '#00bfff';
    default: return theme.colors.primary;
  }
};

const styles = StyleSheet.create({
  container: {
    width: SCREEN_WIDTH,
    height: SCREEN_HEIGHT,
    backgroundColor: '#000',
  },
  progressBar: {
    position: 'absolute',
    top: 50,
    left: 10,
    right: 10,
    height: 3,
    backgroundColor: 'rgba(255, 255, 255, 0.3)',
    borderRadius: 2,
    zIndex: 10,
  },
  progressFill: {
    height: '100%',
    backgroundColor: '#fff',
    borderRadius: 2,
  },
  closeButton: {
    position: 'absolute',
    top: 50,
    right: 20,
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    alignItems: 'center',
    justifyContent: 'center',
    zIndex: 10,
  },
  backgroundImage: {
    width: '100%',
    height: '100%',
    resizeMode: 'cover',
  },
  backgroundFallback: {
    width: '100%',
    height: '100%',
  },
  touchContainer: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    flexDirection: 'row',
  },
  touchLeft: {
    flex: 1,
  },
  touchRight: {
    flex: 1,
  },
  badge: {
    position: 'absolute',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 20,
    zIndex: 5,
  },
  badgeTopLeft: {
    top: 80,
    left: 20,
  },
  badgeTopRight: {
    top: 80,
    right: 20,
  },
  badgeBottom: {
    bottom: 120,
    alignSelf: 'center',
  },
  badgeCenter: {
    top: '50%',
    alignSelf: 'center',
  },
  badgeText: {
    color: '#fff',
    fontSize: 12,
    fontWeight: '700',
    textTransform: 'uppercase',
  },
  contentOverlay: {
    position: 'absolute',
    bottom: 100,
    left: 20,
    right: 20,
    padding: 20,
    backgroundColor: 'rgba(0, 0, 0, 0.6)',
    borderRadius: 16,
  },
  contentCard: {
    position: 'absolute',
    bottom: 100,
    left: 20,
    right: 20,
    padding: 24,
    backgroundColor: '#fff',
    borderRadius: 16,
    alignItems: 'center',
  },
  titleText: {
    fontSize: 24,
    fontWeight: '700',
    color: '#fff',
    marginBottom: 8,
  },
  descriptionText: {
    fontSize: 14,
    color: 'rgba(255, 255, 255, 0.9)',
    lineHeight: 20,
  },
  discountText: {
    fontSize: 48,
    fontWeight: '900',
    color: '#fff',
    marginBottom: 8,
  },
  codeBox: {
    marginTop: 12,
    padding: 12,
    backgroundColor: 'rgba(255, 255, 255, 0.2)',
    borderRadius: 8,
    borderWidth: 2,
    borderColor: '#fff',
    borderStyle: 'dashed',
  },
  codeLabel: {
    fontSize: 12,
    color: 'rgba(255, 255, 255, 0.8)',
    marginBottom: 4,
  },
  codeText: {
    fontSize: 20,
    fontWeight: '700',
    color: '#fff',
    letterSpacing: 2,
  },
  expiryText: {
    marginTop: 8,
    fontSize: 12,
    color: 'rgba(255, 255, 255, 0.7)',
  },
  taglineText: {
    fontSize: 16,
    fontWeight: '600',
    color: '#ffd700',
    marginBottom: 8,
    textTransform: 'uppercase',
  },
  highlightText: {
    marginTop: 8,
    fontSize: 14,
    fontWeight: '600',
    color: '#ffd700',
  },
  seasonText: {
    fontSize: 14,
    fontWeight: '700',
    color: '#ffd700',
    marginBottom: 8,
    letterSpacing: 2,
  },
  collectionText: {
    fontSize: 18,
    fontWeight: '600',
    color: 'rgba(255, 255, 255, 0.9)',
    marginTop: 4,
  },
  limitedBadge: {
    marginTop: 12,
    paddingHorizontal: 12,
    paddingVertical: 6,
    backgroundColor: '#ff0000',
    borderRadius: 4,
  },
  limitedText: {
    fontSize: 10,
    fontWeight: '700',
    color: '#fff',
    letterSpacing: 1,
  },
  featureText: {
    marginTop: 8,
    fontSize: 14,
    color: 'rgba(255, 255, 255, 0.9)',
  },
  priceText: {
    marginTop: 8,
    fontSize: 20,
    fontWeight: '700',
    color: '#ffd700',
  },
  priorityBadge: {
    marginTop: 12,
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 4,
  },
  priorityText: {
    fontSize: 10,
    fontWeight: '700',
    color: '#fff',
  },
  ctaButton: {
    position: 'absolute',
    bottom: 30,
    left: 20,
    right: 20,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 16,
    backgroundColor: theme.colors.primary,
    borderRadius: 24,
  },
  ctaText: {
    fontSize: 16,
    fontWeight: '700',
    color: '#fff',
  },
});
