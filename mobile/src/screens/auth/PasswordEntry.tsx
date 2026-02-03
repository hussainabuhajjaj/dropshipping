import { Pressable, StyleSheet, Text, View } from 'react-native';
import { AvatarBadge } from '@/src/components/auth/AvatarBadge';
import { theme } from '@/src/theme';

type PasswordEntryVariant = 'empty' | 'typing' | 'wrong';

type PasswordEntryProps = {
  variant: PasswordEntryVariant;
  onAdvance?: () => void;
  onNotYou?: () => void;
  onForgot?: () => void;
  onDotsPress?: () => void;
  name?: string;
  avatarUri?: string | null;
};

export function PasswordEntry({
  variant,
  onAdvance,
  onNotYou,
  onForgot,
  onDotsPress,
  name,
  avatarUri,
}: PasswordEntryProps) {
  const showForgot = variant === 'wrong';

  return (
    <View style={styles.container}>
      <View style={styles.avatarWrap}>
        <AvatarBadge
          innerColor="#f4d6e6"
          imageSource={avatarUri ? { uri: avatarUri } : undefined}
        />
      </View>
      <Text style={styles.title}>Welcome back{name ? `, ${name}` : ''}</Text>
      <Text style={styles.subtitle}>Enter your password to continue</Text>
      <View style={styles.inlineActions}>
        {onNotYou ? (
          <Pressable onPress={onNotYou}>
            <Text style={styles.notYou}>Not you?</Text>
          </Pressable>
        ) : null}
        {showForgot ? (
          <Pressable onPress={onForgot}>
            <Text style={styles.forgot}>Forgot password?</Text>
          </Pressable>
          ) : null}
        {variant === 'empty' && onAdvance ? (
          <Pressable onPress={onAdvance}>
            <Text style={styles.continueText}>Continue</Text>
          </Pressable>
        ) : null}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    alignItems: 'center',
    paddingHorizontal: theme.spacing.lg,
  },
  avatarWrap: {
    backgroundColor: theme.colors.white,
    padding: theme.moderateScale(6),
    borderRadius: theme.radius.pill,
    ...theme.shadows.sm,
  },
  title: {
    marginTop: theme.spacing.md,
    fontSize: theme.moderateScale(22),
    fontWeight: '800',
    color: theme.colors.ink,
  },
  subtitle: {
    marginTop: theme.moderateScale(8),
    fontSize: theme.moderateScale(13),
    color: theme.colors.muted,
  },
  inlineActions: {
    marginTop: theme.spacing.md,
    flexDirection: 'row',
    gap: theme.moderateScale(16),
  },
  forgot: {
    fontSize: theme.moderateScale(13),
    color: theme.colors.primary,
    fontWeight: '600',
  },
  notYou: {
    fontSize: theme.moderateScale(13),
    color: theme.colors.muted,
    fontWeight: '600',
  },
  continueText: {
    fontSize: theme.moderateScale(13),
    color: theme.colors.ink,
    fontWeight: '600',
  },
});
