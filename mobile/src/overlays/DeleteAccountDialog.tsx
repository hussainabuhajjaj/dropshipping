import { StyleSheet, Text, View } from 'react-native';
import { PrimaryButton } from '@/src/components/buttons/PrimaryButton';
import { TextButton } from '@/src/components/buttons/TextButton';
import { Dialog } from './Dialog';
import { theme } from '@/src/theme';

type DeleteAccountDialogProps = {
  visible: boolean;
  onConfirm: () => void;
  onCancel: () => void;
  onSignOut: () => void;
};

export function DeleteAccountDialog({
  visible,
  onConfirm,
  onCancel,
  onSignOut,
}: DeleteAccountDialogProps) {
  return (
    <Dialog visible={visible} onClose={onCancel}>
      <View style={styles.iconWrap}>
        <Text style={styles.iconText}>!</Text>
      </View>
      <Text style={styles.title}>Delete Account?</Text>
      <Text style={styles.body}>
        This action removes your account and order history. You can’t undo this action.
      </Text>
      <PrimaryButton label="Delete Account" onPress={onConfirm} style={styles.primary} />
      <TextButton label="Sign out" onPress={onSignOut} textStyle={styles.signOutText} />
      <TextButton label="Cancel" onPress={onCancel} textStyle={styles.cancelText} />
    </Dialog>
  );
}

const styles = StyleSheet.create({
  iconWrap: {
    width: theme.moderateScale(60),
    height: theme.moderateScale(60),
    borderRadius: theme.moderateScale(30),
    backgroundColor: theme.colors.pinkSoft,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: theme.moderateScale(12),
  },
  iconText: {
    fontSize: theme.moderateScale(22),
    fontWeight: '700',
    color: theme.colors.pink,
  },
  title: {
    fontSize: theme.moderateScale(16),
    fontWeight: '700',
    color: theme.colors.ink,
    marginBottom: theme.moderateScale(8),
  },
  body: {
    fontSize: theme.moderateScale(12),
    color: theme.colors.muted,
    textAlign: 'center',
    marginBottom: theme.moderateScale(16),
  },
  primary: {
    width: '100%',
    backgroundColor: theme.colors.pink,
  },
  signOutText: {
    color: theme.colors.ink,
  },
  cancelText: {
    color: theme.colors.muted,
  },
});
