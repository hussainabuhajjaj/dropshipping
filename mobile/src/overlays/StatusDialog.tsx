import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';
import { Feather } from '@expo/vector-icons';
import { Dialog } from './Dialog';
import { PrimaryButton } from '@/src/components/buttons/PrimaryButton';
import { TextButton } from '@/src/components/buttons/TextButton';
import { theme } from '@/src/theme';

type StatusDialogProps = {
  visible: boolean;
  variant: 'success' | 'error' | 'info' | 'loading';
  title: string;
  message: string;
  primaryLabel: string;
  onPrimary: () => void;
  secondaryLabel?: string;
  onSecondary?: () => void;
  onClose: () => void;
};

export function StatusDialog({
  visible,
  variant,
  title,
  message,
  primaryLabel,
  onPrimary,
  secondaryLabel,
  onSecondary,
  onClose,
}: StatusDialogProps) {
  const icon =
    variant === 'success' ? 'check' : variant === 'error' ? 'x' : variant === 'info' ? 'info' : null;
  const iconBackground =
    variant === 'success'
      ? theme.colors.sand
      : variant === 'error'
        ? theme.colors.dangerSoft
        : theme.colors.blueSoft;

  return (
    <Dialog visible={visible} onClose={onClose}>
      <View style={styles.iconWrap}>
        <View style={[styles.iconInner, { backgroundColor: iconBackground }]}>
          {variant === 'loading' ? (
            <ActivityIndicator size="small" color={theme.colors.inkDark} />
          ) : (
            <Feather name={icon as any} size={20} color={theme.colors.inkDark} />
          )}
        </View>
      </View>
      <Text style={styles.title}>{title}</Text>
      <Text style={styles.body}>{message}</Text>
      <PrimaryButton label={primaryLabel} onPress={onPrimary} style={styles.primary} textStyle={styles.primaryText} />
      {secondaryLabel && onSecondary ? (
        <TextButton label={secondaryLabel} onPress={onSecondary} textStyle={styles.secondaryText} />
      ) : null}
    </Dialog>
  );
}

const styles = StyleSheet.create({
  iconWrap: {
    width: theme.moderateScale(72),
    height: theme.moderateScale(72),
    borderRadius: theme.moderateScale(36),
    backgroundColor: theme.colors.white,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: theme.colors.borderSoft,
    marginBottom: theme.moderateScale(12),
  },
  iconInner: {
    width: theme.moderateScale(48),
    height: theme.moderateScale(48),
    borderRadius: theme.moderateScale(24),
    alignItems: 'center',
    justifyContent: 'center',
  },
  title: {
    fontSize: theme.moderateScale(16),
    fontWeight: '700',
    color: theme.colors.ink,
    marginBottom: theme.moderateScale(6),
    textAlign: 'center',
  },
  body: {
    fontSize: theme.moderateScale(12),
    color: theme.colors.muted,
    textAlign: 'center',
    marginBottom: theme.moderateScale(16),
  },
  primary: {
    width: '100%',
    backgroundColor: theme.colors.sun,
  },
  primaryText: {
    fontSize: theme.moderateScale(14),
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  secondaryText: {
    color: theme.colors.muted,
  },
});

