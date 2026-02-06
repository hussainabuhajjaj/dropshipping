import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useMemo, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, TextInput, View } from '@/src/utils/responsiveStyleSheet';
import { usePaymentMethods } from '@/lib/paymentMethodsStore';
import { theme } from '@/src/theme';
import { KeyboardAvoidingView, Platform } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
export default function AddPaymentMethodScreen() {
  const { addCard } = usePaymentMethods();
  const [number, setNumber] = useState('');
  const [expiry, setExpiry] = useState('');
  const [cvv, setCvv] = useState('');
  const [name, setName] = useState('');
  const canSave = useMemo(() => {
    const digits = number.replace(/\D+/g, '');
    return digits.length >= 12 && expiry.trim().length >= 4 && name.trim().length >= 2;
  }, [number, expiry, name]);

  return (
    <SafeAreaView style={styles.container}>
      <KeyboardAvoidingView
        style={styles.keyboard}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        keyboardVerticalOffset={Platform.OS === 'ios' ? theme.moderateScale(20) : 0}
      >
        <ScrollView
          style={styles.scroll}
          contentContainerStyle={styles.content}
          showsVerticalScrollIndicator={false}
          keyboardShouldPersistTaps="handled"
          keyboardDismissMode="interactive"
          automaticallyAdjustKeyboardInsets
        >
          <View style={styles.headerRow}>
            <Pressable style={styles.iconButton} onPress={() => router.back()}>
              <Feather name="arrow-left" size={16} color={theme.colors.inkDark} />
            </Pressable>
            <Text style={styles.title}>Add card</Text>
            <View style={styles.spacer} />
          </View>

          <View style={styles.form}>
            <TextInput
              style={styles.input}
              placeholder="Card number"
              placeholderTextColor="#c7c7c7"
              keyboardType="number-pad"
              value={number}
              onChangeText={setNumber}
            />
            <View style={styles.row}>
              <TextInput
                style={[styles.input, styles.halfInput]}
                placeholder="MM/YY"
                placeholderTextColor="#c7c7c7"
                value={expiry}
                onChangeText={setExpiry}
              />
              <TextInput
                style={[styles.input, styles.halfInput]}
                placeholder="CVV"
                placeholderTextColor="#c7c7c7"
                keyboardType="number-pad"
                value={cvv}
                onChangeText={setCvv}
              />
            </View>
            <TextInput
              style={styles.input}
              placeholder="Card holder name"
              placeholderTextColor="#c7c7c7"
              value={name}
              onChangeText={setName}
            />
          </View>

          <Pressable
            style={[styles.primaryButton, !canSave ? { opacity: 0.5 } : null]}
            onPress={() => {
              if (!canSave) return;
              addCard({ name, number, expiry });
              router.push('/payment/methods');
            }}
            disabled={!canSave}
          >
            <Text style={styles.primaryText}>Save card</Text>
          </Pressable>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.white,
  },
  keyboard: {
    flex: 1,
  },
  scroll: {
    flex: 1,
  },
  content: {
    paddingHorizontal: 20,
    paddingTop: 10,
    paddingBottom: 24,
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 16,
  },
  title: {
    fontSize: 20,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  iconButton: {
    width: 32,
    height: 32,
    borderRadius: 16,
    backgroundColor: theme.colors.gray100,
    alignItems: 'center',
    justifyContent: 'center',
  },
  spacer: {
    width: 32,
    height: 32,
  },
  form: {
    gap: 12,
  },
  row: {
    flexDirection: 'row',
    gap: 12,
  },
  input: {
    height: 50,
    borderRadius: 18,
    backgroundColor: theme.colors.sand,
    paddingHorizontal: 16,
    fontSize: 14,
    color: theme.colors.inkDark,
    flex: 1,
  },
  halfInput: {
    flex: 1,
  },
  primaryButton: {
    marginTop: 24,
    backgroundColor: theme.colors.sun,
    borderRadius: 24,
    paddingVertical: 14,
    alignItems: 'center',
  },
  primaryText: {
    fontSize: 14,
    color: theme.colors.gray200,
    fontWeight: '700',
  },
});
