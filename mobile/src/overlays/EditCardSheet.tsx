import { Feather } from '@expo/vector-icons';
import { Pressable, StyleSheet, TextInput, View } from 'react-native';
import { Text } from '@/src/components/i18n/Text';
import { useEffect, useState } from 'react';
import { PrimaryButton } from '@/src/components/buttons/PrimaryButton';
import { ModalSheet } from './ModalSheet';
import { theme } from '@/src/theme';
import { usePaymentMethods } from '@/lib/paymentMethodsStore';

type EditCardSheetProps = {
  visible: boolean;
  onClose: () => void;
  cardId: string | null;
};

export function EditCardSheet({ visible, onClose, cardId }: EditCardSheetProps) {
  const { cards, updateCard, removeCard } = usePaymentMethods();
  const card = cardId ? cards.find((c) => c.id === cardId) : undefined;
  const [name, setName] = useState('');
  const [expiry, setExpiry] = useState('');

  useEffect(() => {
    if (!visible || !card) return;
    setName(card.name);
    setExpiry(card.expiry);
  }, [visible, card]);

  return (
    <ModalSheet visible={visible} onClose={onClose}>
      <View style={styles.headerRow}>
        <Text style={styles.title}>Edit Card</Text>
        <Pressable
          style={styles.deleteButton}
          onPress={() => {
            if (!cardId) return;
            removeCard(cardId);
            onClose();
          }}
          accessibilityRole="button"
        >
          <Feather name="trash-2" size={16} color={theme.colors.pink} />
        </Pressable>
      </View>
      <View style={styles.field}>
        <Text style={styles.label}>Card Holder</Text>
        <TextInput style={styles.input} value={name} onChangeText={setName} />
      </View>
      <View style={styles.field}>
        <Text style={styles.label}>Card Number</Text>
        <TextInput
          style={styles.input}
          value={card ? `•••• •••• •••• ${card.last4}` : '•••• •••• •••• 0000'}
          editable={false}
        />
      </View>
      <View style={styles.row}>
        <View style={styles.fieldHalf}>
          <Text style={styles.label}>Valid</Text>
          <TextInput style={styles.input} value={expiry} onChangeText={setExpiry} />
        </View>
        <View style={styles.fieldHalf}>
          <Text style={styles.label}>CVV</Text>
          <TextInput style={styles.input} value="•••" editable={false} />
        </View>
      </View>
      <PrimaryButton
        label="Save Changes"
        onPress={() => {
          if (!cardId) return;
          updateCard(cardId, { name: name.trim() || 'CARD HOLDER', expiry: expiry.trim() || '12/29' });
          onClose();
        }}
        style={styles.button}
      />
    </ModalSheet>
  );
}

const styles = StyleSheet.create({
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: theme.moderateScale(14),
  },
  title: {
    fontSize: theme.moderateScale(18),
    fontWeight: '700',
    color: theme.colors.ink,
  },
  deleteButton: {
    width: theme.moderateScale(32),
    height: theme.moderateScale(32),
    borderRadius: theme.moderateScale(16),
    backgroundColor: theme.colors.pinkSoft,
    alignItems: 'center',
    justifyContent: 'center',
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
});
