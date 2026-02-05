import { StyleSheet, View } from 'react-native';
import { Text } from '@/src/components/i18n/Text';
import { Feather } from '@expo/vector-icons';
import { Dialog } from './Dialog';
import { PrimaryButton } from '@/src/components/buttons/PrimaryButton';
import { theme } from '@/src/theme';

type MaximumAttemptsDialogProps = {
  visible: boolean;
  onConfirm: () => void;
};

export function MaximumAttemptsDialog({ visible, onConfirm }: MaximumAttemptsDialogProps) {
  return (
    <Dialog visible={visible} onClose={onConfirm}>
      <View style={styles.iconWrap}>
        <View style={styles.iconInner}>
          <Feather name="alert-circle" size={22} color={theme.colors.white} />
        </View>
      </View>
      <Text style={styles.message}>
        You reached out maximum amount of attempts.{'\n'}Please, try later.
      </Text>
      <PrimaryButton
        label="Okay"
        onPress={onConfirm}
        style={styles.button}
        textStyle={styles.buttonText}
      />
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
    borderColor: theme.colors.pinkSoft,
    marginBottom: theme.moderateScale(14),
  },
  iconInner: {
    width: theme.moderateScale(48),
    height: theme.moderateScale(48),
    borderRadius: theme.moderateScale(24),
    backgroundColor: theme.colors.pink,
    alignItems: 'center',
    justifyContent: 'center',
  },
  message: {
    fontSize: theme.moderateScale(14),
    lineHeight: theme.moderateScale(20),
    color: theme.colors.ink,
    textAlign: 'center',
    marginBottom: theme.moderateScale(16),
  },
  button: {
    width: theme.moderateScale(180),
    backgroundColor: theme.colors.ink,
    paddingVertical: theme.moderateScale(10),
  },
  buttonText: {
    fontSize: theme.moderateScale(15),
    fontWeight: '600',
  },
});
