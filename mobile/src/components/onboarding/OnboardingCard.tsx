import { LinearGradient } from 'expo-linear-gradient';
import { Pressable, StyleSheet, View } from 'react-native';
import { Text } from '@/src/components/i18n/Text';
import { theme } from '@/src/theme';

type OnboardingCardProps = {
  title: string;
  body: string;
  imageColors: [string, string];
  actionLabel?: string;
  onAction?: () => void;
};

export function OnboardingCard({ title, body, imageColors, actionLabel, onAction }: OnboardingCardProps) {
  return (
    <View style={styles.card}>
      <LinearGradient colors={imageColors} style={styles.imageCard}>
        <View style={styles.imageHighlight} />
      </LinearGradient>
      <View style={styles.copy}>
        <Text style={styles.title}>{title}</Text>
        <Text style={styles.body}>{body}</Text>
        {actionLabel ? (
          <Pressable style={styles.button} onPress={onAction} accessibilityRole="button">
            <Text style={styles.buttonText}>{actionLabel}</Text>
          </Pressable>
        ) : null}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    width: '100%',
    borderRadius: theme.moderateScale(24),
    backgroundColor: theme.colors.white,
    overflow: 'hidden',
    ...theme.shadows.md,
  },
  imageCard: {
    height: theme.moderateScale(300),
    borderTopLeftRadius: theme.moderateScale(24),
    borderTopRightRadius: theme.moderateScale(24),
    justifyContent: 'flex-end',
  },
  imageHighlight: {
    height: theme.moderateScale(40),
    backgroundColor: 'rgba(255,255,255,0.28)',
  },
  copy: {
    paddingHorizontal: theme.moderateScale(24),
    paddingVertical: theme.moderateScale(20),
    alignItems: 'center',
  },
  title: {
    fontSize: theme.moderateScale(22),
    fontWeight: '700',
    color: theme.colors.ink,
  },
  body: {
    marginTop: theme.moderateScale(10),
    fontSize: theme.moderateScale(13),
    color: theme.colors.ink,
    textAlign: 'center',
    lineHeight: theme.moderateScale(20),
  },
  button: {
    marginTop: theme.moderateScale(16),
    backgroundColor: theme.colors.primary,
    borderRadius: theme.moderateScale(20),
    paddingVertical: theme.moderateScale(12),
    paddingHorizontal: theme.moderateScale(32),
  },
  buttonText: {
    fontSize: theme.moderateScale(13),
    fontWeight: '600',
    color: theme.colors.white,
  },
});
