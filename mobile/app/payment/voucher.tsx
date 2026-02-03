import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, TextInput, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
import { StatusDialog } from '@/src/overlays/StatusDialog';
export default function VoucherScreen() {
  const [code, setCode] = useState('');
  const [visible, setVisible] = useState(false);

  return (
    <>
      <ScrollView style={styles.container} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.headerRow}>
          <Pressable style={styles.iconButton} onPress={() => router.back()}>
            <Feather name="arrow-left" size={16} color={theme.colors.inkDark} />
          </Pressable>
          <Text style={styles.title}>Add voucher</Text>
          <View style={styles.spacer} />
        </View>

        <View style={styles.card}>
          <Text style={styles.cardTitle}>Voucher code</Text>
          <TextInput
            style={styles.input}
            placeholder="Enter code"
            placeholderTextColor="#c7c7c7"
            value={code}
            onChangeText={(value) => setCode(value)}
          />
          <Pressable
            style={[styles.primaryButton, code.trim().length === 0 ? styles.primaryDisabled : null]}
            onPress={() => {
              if (code.trim().length === 0) return;
              setVisible(true);
            }}
          >
            <Text style={styles.primaryText}>Apply</Text>
          </Pressable>
        </View>
      </ScrollView>

      <StatusDialog
        visible={visible}
        variant="success"
        title="Voucher added"
        message="Your discount has been applied to the order."
        primaryLabel="Back to payment"
        onPrimary={() => {
          setVisible(false);
          router.back();
        }}
        onClose={() => setVisible(false)}
      />
    </>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.white,
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
  card: {
    padding: 18,
    borderRadius: 18,
    backgroundColor: theme.colors.sand,
  },
  cardTitle: {
    fontSize: 14,
    fontWeight: '700',
    color: theme.colors.inkDark,
    marginBottom: 12,
  },
  input: {
    height: 50,
    borderRadius: 18,
    backgroundColor: theme.colors.white,
    paddingHorizontal: 16,
    fontSize: 14,
    color: theme.colors.inkDark,
  },
  primaryButton: {
    marginTop: 16,
    backgroundColor: theme.colors.sun,
    borderRadius: 24,
    paddingVertical: 12,
    alignItems: 'center',
  },
  primaryText: {
    fontSize: 14,
    color: theme.colors.gray200,
    fontWeight: '700',
  },
  primaryDisabled: {
    opacity: 0.5,
  },
});
