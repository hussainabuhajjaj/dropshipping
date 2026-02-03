import { router } from 'expo-router';
import { useMemo, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { LinearGradient } from 'expo-linear-gradient';
import { RoundedInput } from '@/src/components/auth/RoundedInput';
import { PrimaryButton } from '@/src/components/buttons/PrimaryButton';
import { TextButton } from '@/src/components/buttons/TextButton';
import { theme } from '@/src/theme';
import { routes } from '@/src/navigation/routes';

export default function LoginScreen() {
  const [email, setEmail] = useState('');
  const [showErrors, setShowErrors] = useState(false);
  const isValidEmail = useMemo(() => /\S+@\S+\.\S+/.test(email.trim()), [email]);
  const canContinue = isValidEmail;
  const helperText = showErrors && !isValidEmail ? 'Enter a valid email address.' : 'We’ll continue on the next step.';

  return (
    <LinearGradient
      colors={['#fdf1d9', '#fbe7c0', '#f7c985', '#c97b2f']}
      style={styles.root}
    >
      <SafeAreaView style={styles.safe}>
        <View style={styles.hero}>
          <Text style={styles.heroTitle}>Welcome back</Text>
          <Text style={styles.heroSubtitle}>
            Sign in to keep shopping and track your deliveries in one place.
          </Text>
        </View>

        <View style={styles.card}>
          <Text style={styles.cardTitle}>Continue with Email</Text>
          <View style={styles.inputWrap}>
            <RoundedInput
              placeholder="Email"
              keyboardType="email-address"
              inputProps={{
                value: email,
                onChangeText: setEmail,
                autoComplete: 'email',
                textContentType: 'emailAddress',
                returnKeyType: 'next',
                onSubmitEditing: () => {
                  if (!canContinue) {
                    setShowErrors(true);
                    return;
                  }
                  router.push({ pathname: routes.password, params: { email: email.trim() } });
                },
              }}
            />
            <Text style={[styles.helperText, showErrors && !isValidEmail ? styles.helperError : null]}>
              {helperText}
            </Text>
          </View>
          <PrimaryButton
            label="Continue with Email"
            disabled={!canContinue}
            onPress={() => {
              if (!canContinue) {
                setShowErrors(true);
                return;
              }
              router.push({ pathname: routes.password, params: { email: email.trim() } });
            }}
          />
          <Pressable
            style={styles.forgotRow}
            onPress={() => router.push(routes.forgot)}
            accessibilityRole="button"
          >
            <Text style={styles.forgotText}>Forgot password?</Text>
          </Pressable>
        </View>

        <View style={styles.actions}>
          <TextButton label="Cancel" onPress={() => router.push(routes.start)} textStyle={styles.cancelText} />
        </View>
      </SafeAreaView>
    </LinearGradient>
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
  },
  safe: {
    flex: 1,
    justifyContent: 'space-between',
    paddingHorizontal: theme.spacing.lg,
    paddingBottom: theme.spacing.lg,
  },
  hero: {
    marginTop: theme.spacing.xl,
    maxWidth: theme.moderateScale(300),
  },
  heroTitle: {
    fontSize: theme.moderateScale(32),
    fontWeight: '800',
    color: theme.colors.inkDark,
  },
  heroSubtitle: {
    marginTop: theme.spacing.sm,
    fontSize: theme.moderateScale(14),
    color: theme.colors.ink,
    lineHeight: theme.moderateScale(20),
  },
  card: {
    backgroundColor: 'rgba(255,255,255,0.85)',
    borderRadius: theme.radius.xl,
    padding: theme.spacing.lg,
    gap: theme.moderateScale(12),
    ...theme.shadows.md,
  },
  cardTitle: {
    fontSize: theme.moderateScale(16),
    fontWeight: '700',
    color: theme.colors.ink,
  },
  inputWrap: {
    gap: theme.moderateScale(8),
  },
  helperText: {
    fontSize: theme.moderateScale(12),
    color: theme.colors.muted,
  },
  helperError: {
    color: theme.colors.danger,
  },
  forgotRow: {
    alignSelf: 'center',
  },
  forgotText: {
    fontSize: theme.moderateScale(12),
    color: theme.colors.primary,
    fontWeight: '600',
  },
  actions: {
    alignItems: 'center',
    gap: theme.moderateScale(14),
  },
  cancelText: {
    color: theme.colors.ink,
  },
});
