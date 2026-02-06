import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, TextInput, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
import { KeyboardAvoidingView, Platform } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
export default function ContactScreen() {
  const [submitted, setSubmitted] = useState(false);

  if (submitted) {
    return (
      <SafeAreaView style={styles.successContainer}>
        <View style={styles.successCard}>
          <Feather name="check-circle" size={26} color={theme.colors.inkDark} />
          <Text style={styles.successTitle}>Message sent</Text>
          <Text style={styles.successBody}>We will get back to you within 24 hours.</Text>
          <Pressable style={styles.primaryButton} onPress={() => router.push('/support')}>
            <Text style={styles.primaryText}>Back to support</Text>
          </Pressable>
        </View>
      </SafeAreaView>
    );
  }

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
              <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
            </Pressable>
            <Text style={styles.title}>Contact us</Text>
            <Pressable style={styles.iconButton} onPress={() => router.push('/(tabs)/home')}>
              <Feather name="x" size={16} color={theme.colors.inkDark} />
            </Pressable>
          </View>
          <Text style={styles.subtitle}>We respond within 24 hours.</Text>

          <View style={styles.card}>
            <TextInput style={styles.input} placeholder="Full name" placeholderTextColor="#b6b6b6" />
            <TextInput style={styles.input} placeholder="Email" placeholderTextColor="#b6b6b6" />
            <TextInput style={styles.input} placeholder="Order number (optional)" placeholderTextColor="#b6b6b6" />
            <TextInput
              style={[styles.input, styles.message]}
              placeholder="How can we help?"
              placeholderTextColor="#b6b6b6"
              multiline
            />
            <Pressable style={styles.primaryButton} onPress={() => setSubmitted(true)}>
              <Text style={styles.primaryText}>Send message</Text>
            </Pressable>
          </View>
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
    paddingTop: 12,
    paddingBottom: 32,
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 12,
  },
  iconButton: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: theme.colors.sand,
    alignItems: 'center',
    justifyContent: 'center',
  },
  title: {
    fontSize: 20,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  subtitle: {
    fontSize: 13,
    color: theme.colors.mutedDark,
    marginBottom: 18,
  },
  card: {
    backgroundColor: theme.colors.white,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: theme.colors.sand,
    padding: 18,
    gap: 12,
  },
  input: {
    borderWidth: 1,
    borderColor: '#e6e8ef',
    borderRadius: 18,
    paddingHorizontal: 14,
    paddingVertical: 12,
    fontSize: 13,
    color: theme.colors.inkDark,
    backgroundColor: theme.colors.sand,
  },
  message: {
    height: 120,
    textAlignVertical: 'top',
  },
  successContainer: {
    flex: 1,
    backgroundColor: theme.colors.white,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 24,
  },
  successCard: {
    width: '100%',
    borderRadius: 24,
    backgroundColor: theme.colors.sand,
    padding: 24,
    alignItems: 'center',
    gap: 10,
  },
  successTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  successBody: {
    fontSize: 13,
    color: theme.colors.mutedDark,
    textAlign: 'center',
  },
  primaryButton: {
    marginTop: 6,
    backgroundColor: theme.colors.sun,
    paddingVertical: 12,
    borderRadius: 20,
    alignItems: 'center',
  },
  primaryText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '700',
  },
});
