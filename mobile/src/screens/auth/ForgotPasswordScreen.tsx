import { useState } from 'react';
import { StyleSheet, View } from 'react-native';
import { Text } from '@/src/components/i18n/Text';
import { router } from 'expo-router';
import { AuthScreen } from '@/src/components/auth/AuthScreen';
import { AvatarBadge } from '@/src/components/auth/AvatarBadge';
import { OptionPill } from '@/src/components/auth/OptionPill';
import { PrimaryButton } from '@/src/components/buttons/PrimaryButton';
import { TextButton } from '@/src/components/buttons/TextButton';
import { theme } from '@/src/theme';
import { routes } from '@/src/navigation/routes';

export default function ForgotPasswordScreen() {
  const [method, setMethod] = useState<'sms' | 'email'>('sms');

  return (
    <AuthScreen variant="recovery" contentStyle={styles.content}>
      <View style={styles.header}>
        <AvatarBadge innerColor="#f4d6e6" />
        <Text style={styles.title}>Password Recovery</Text>
        <Text style={styles.subtitle}>How you would like to restore your password?</Text>
      </View>

      <View style={styles.options}>
        <OptionPill
          label="SMS"
          selected={method === 'sms'}
          backgroundColor={theme.colors.primarySoft}
          onPress={() => setMethod('sms')}
        />
        <OptionPill
          label="Email"
          selected={method === 'email'}
          backgroundColor={theme.colors.pinkSoft}
          onPress={() => setMethod('email')}
        />
      </View>

      <View style={styles.actions}>
        <PrimaryButton label="Next" onPress={() => router.push(routes.passwordCode)} />
        <TextButton label="Cancel" onPress={() => router.back()} textStyle={styles.cancelText} />
      </View>
    </AuthScreen>
  );
}

const styles = StyleSheet.create({
  content: {
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
  },
  options: {
    gap: theme.moderateScale(12),
    marginTop: theme.spacing.lg,
  },
  actions: {
    alignItems: 'center',
    gap: theme.moderateScale(12),
  },
  cancelText: {
    color: theme.colors.muted,
  },
});
