import { Feather } from '@expo/vector-icons';
import { StyleSheet, View } from 'react-native';
import { Text } from '@/src/components/i18n/Text';
import { Dialog } from './Dialog';
import { PrimaryButton } from '@/src/components/buttons/PrimaryButton';
import { TextButton } from '@/src/components/buttons/TextButton';
import { theme } from '@/src/theme';

type RemoveCartItemDialogProps = {
  visible: boolean;
  itemName?: string;
  onConfirm: () => void;
  onCancel: () => void;
};

export function RemoveCartItemDialog({
  visible,
  itemName,
  onConfirm,
  onCancel,
}: RemoveCartItemDialogProps) {
  const title = 'Remove item?';
  const message = itemName
    ? `Remove "${itemName}" from your cart?`
    : 'Remove this item from your cart?';

  return (
    <Dialog visible={visible} onClose={onCancel}>
      <View style={styles.iconWrap}>
        <View style={styles.iconInner}>
          <Feather name="trash-2" size={20} color={theme.colors.white} />
        </View>
      </View>
      <Text style={styles.title}>{title}</Text>
      <Text style={styles.body}>{message}</Text>
      <PrimaryButton
        label="Remove"
        onPress={onConfirm}
        style={styles.primary}
        textStyle={styles.primaryText}
      />
      <TextButton label="Cancel" onPress={onCancel} textStyle={styles.cancelText} />
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
    borderColor: theme.colors.orangeSoft,
    marginBottom: theme.moderateScale(12),
  },
  iconInner: {
    width: theme.moderateScale(48),
    height: theme.moderateScale(48),
    // borderRadius: theme.moderateScale(24),
    backgroundColor: theme.colors.orangeSoft,
    borderRadius: theme.moderateScale(50),
    alignItems: 'center',
    justifyContent: 'center',
  },
  title: {
    fontSize: theme.moderateScale(16),
    fontWeight: '700',
    color: theme.colors.ink,
    marginBottom: theme.moderateScale(6),
  },
  body: {
    fontSize: theme.moderateScale(12),
    color: theme.colors.muted,
    textAlign: 'center',
    marginBottom: theme.moderateScale(16),
  },
  primary: {
    width: '100%',
    backgroundColor: theme.colors.orange,
  },
  primaryText: {
    fontSize: theme.moderateScale(14),
    fontWeight: '600',
  },
  cancelText: {
    color: theme.colors.muted,
  },
});
