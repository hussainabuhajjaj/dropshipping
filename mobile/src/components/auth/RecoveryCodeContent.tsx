import { Pressable, StyleSheet, View } from 'react-native';
import { Text } from '@/src/components/i18n/Text';
import { AvatarBadge } from './AvatarBadge';
import { PinDots } from './PinDots';
import { TextButton } from '@/src/components/buttons/TextButton';
import { theme } from '@/src/theme';

type RecoveryCodeContentProps = {
  onSubmit?: () => void;
  onSendAgain?: () => void;
  onCancel?: () => void;
};

export function RecoveryCodeContent({ onSubmit, onSendAgain, onCancel }: RecoveryCodeContentProps) {
  return (
    <View style={styles.content}>
      <View style={styles.header}>
        <AvatarBadge innerColor="#f4d6e6" />
        <Text style={styles.title}>Password Recovery</Text>
        <Text style={styles.subtitle}>
          Enter 4-digits code we sent you{'\n'}on your phone number
        </Text>
        <Text style={styles.phone}>+98*******00</Text>
        <Pressable style={styles.dotRow} onPress={onSubmit}>
          <PinDots total={4} filled={0} size={12} activeColor={theme.colors.primary} />
        </Pressable>
      </View>

      <View style={styles.actions}>
        <Pressable style={styles.sendButton} onPress={onSendAgain} accessibilityRole="button">
          <Text style={styles.sendText}>Send Again</Text>
        </Pressable>
        <TextButton label="Cancel" onPress={onCancel} textStyle={styles.cancelText} />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  content: {
    flex: 1,
    justifyContent: 'space-between',
    paddingTop: theme.spacing.lg,
    paddingBottom: theme.spacing.lg,
  },
  header: {
    alignItems: 'center',
    marginTop: theme.moderateScale(40),
  },
  title: {
    marginTop: theme.spacing.md,
    fontSize: theme.moderateScale(22),
    fontWeight: '700',
    color: theme.colors.ink,
  },
  subtitle: {
    marginTop: theme.spacing.xs,
    fontSize: theme.moderateScale(14),
    color: theme.colors.ink,
    textAlign: 'center',
    lineHeight: theme.moderateScale(20),
  },
  phone: {
    marginTop: theme.spacing.sm,
    fontSize: theme.moderateScale(14),
    color: theme.colors.ink,
    letterSpacing: theme.moderateScale(1),
  },
  dotRow: {
    marginTop: theme.spacing.md,
  },
  actions: {
    alignItems: 'center',
    gap: theme.moderateScale(12),
  },
  sendButton: {
    backgroundColor: theme.colors.pink,
    borderRadius: theme.radius.xl,
    paddingVertical: theme.moderateScale(12),
    paddingHorizontal: theme.moderateScale(36),
  },
  sendText: {
    color: theme.colors.white,
    fontSize: theme.moderateScale(15),
    fontWeight: '600',
  },
  cancelText: {
    color: theme.colors.muted,
  },
});
