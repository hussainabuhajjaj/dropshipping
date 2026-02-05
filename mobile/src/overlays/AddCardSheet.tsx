import { useMemo, useState } from 'react';
import { StyleSheet, TextInput, View } from 'react-native';
import { Text } from '@/src/components/i18n/Text';
import { PrimaryButton } from '@/src/components/buttons/PrimaryButton';
import { ModalSheet } from './ModalSheet';
import { theme } from '@/src/theme';
import { usePaymentMethods } from '@/lib/paymentMethodsStore';

type AddCardSheetProps = {
  visible: boolean;
  onClose: () => void;
};

export function AddCardSheet({ visible, onClose }: AddCardSheetProps) {
  const { addCard } = usePaymentMethods();
  const [name, setName] = useState('');
  const [number, setNumber] = useState('');
  const [expiry, setExpiry] = useState('');
  const [cvv, setCvv] = useState('');

  const canSave = useMemo(() => {
    const digits = number.replace(/\D+/g, '');
    return digits.length >= 12 && expiry.trim().length >= 4 && name.trim().length >= 2;
  }, [number, expiry, name]);

  return (
    <ModalSheet visible={visible} onClose={onClose}>
      <Text style={styles.title}>Add Card</Text>
      <View style={styles.field}>
        <Text style={styles.label}>Card Holder</Text>
        <TextInput
          style={styles.input}
          placeholder="Required"
          placeholderTextColor={theme.colors.primary}
          value={name}
          onChangeText={setName}
        />
      </View>
      <View style={styles.field}>
        <Text style={styles.label}>Card Number</Text>
        <TextInput
          style={styles.input}
          placeholder="Required"
          placeholderTextColor={theme.colors.primary}
          keyboardType="number-pad"
          value={number}
          onChangeText={setNumber}
        />
      </View>
      <View style={styles.row}>
        <View style={styles.fieldHalf}>
          <Text style={styles.label}>Valid</Text>
          <TextInput
            style={styles.input}
            placeholder="MM/YY"
            placeholderTextColor={theme.colors.primary}
            value={expiry}
            onChangeText={setExpiry}
          />
        </View>
        <View style={styles.fieldHalf}>
          <Text style={styles.label}>CVV</Text>
          <TextInput
            style={styles.input}
            placeholder="Required"
            placeholderTextColor={theme.colors.primary}
            keyboardType="number-pad"
            value={cvv}
            onChangeText={setCvv}
          />
        </View>
      </View>
      <PrimaryButton
        label="Save Changes"
        onPress={() => {
          if (!canSave) return;
          addCard({ name, number, expiry });
          setName('');
          setNumber('');
          setExpiry('');
          setCvv('');
          onClose();
        }}
        style={[styles.button, !canSave ? styles.buttonDisabled : null]}
      />
    </ModalSheet>
  );
}

const styles = StyleSheet.create({
  title: {
    fontSize: theme.moderateScale(18),
    fontWeight: '700',
    color: theme.colors.ink,
    marginBottom: theme.moderateScale(14),
  },
  field: {
    marginBottom: theme.moderateScale(12),
  },
  row: {
    flexDirection: 'row',
    gap: theme.moderateScale(12),
  },
  fieldHalf: {
    flex: 1,
  },
  label: {
    fontSize: theme.moderateScale(12),
    color: theme.colors.muted,
    marginBottom: theme.moderateScale(6),
  },
  input: {
    height: theme.moderateScale(44),
    borderRadius: theme.moderateScale(12),
    backgroundColor: theme.colors.primarySoftLight,
    paddingHorizontal: theme.moderateScale(12),
    fontSize: theme.moderateScale(13),
    color: theme.colors.ink,
  },
  button: {
    marginTop: theme.moderateScale(8),
    marginBottom: theme.moderateScale(6),
  },
  buttonDisabled: {
    opacity: 0.5,
  },
});
