import { useEffect, useRef, useState } from 'react';
import { Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { useLocalSearchParams, router } from 'expo-router';
import { AuthScreen } from '@/src/components/auth/AuthScreen';
import { PrimaryButton } from '@/src/components/buttons/PrimaryButton';
import { TextButton } from '@/src/components/buttons/TextButton';
import { theme } from '@/src/theme';
import { meRequest, resendVerificationRequest, verifyEmailOtpRequest } from '@/src/api/auth';
import { useAuth } from '@/lib/authStore';
import { useToast } from '@/src/overlays/ToastProvider';

export default function VerifyEmailScreen() {
  const { user } = useAuth();
  const { show } = useToast();
  const params = useLocalSearchParams<{ email?: string }>();
  const email = typeof params.email === 'string' ? params.email : user?.email ?? '';
  const [code, setCode] = useState('');
  const inputs = useRef<Array<TextInput | null>>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [isResending, setIsResending] = useState(false);
  const [cooldown, setCooldown] = useState(60);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const timer = setInterval(() => {
      setCooldown((prev) => (prev > 0 ? prev - 1 : 0));
    }, 1000);
    return () => clearInterval(timer);
  }, []);

  useEffect(() => {
    if (code.length === 4 && !isLoading) {
      handleVerify();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [code]);

  const handleVerify = async () => {
    try {
      setIsLoading(true);
      setError(null);
      if (code.trim().length !== 4) {
        setError('Enter the 4-digit verification code.');
        show({ type: 'warning', message: 'Enter the 4-digit verification code.' });
        return;
      }
      await verifyEmailOtpRequest({ code: code.trim() });
      show({ type: 'success', message: 'Email verified successfully.' });
      const me = await meRequest();
      if (!me.phone_verified_at && !me.is_phone_verified) {
        const emailParam = encodeURIComponent(me.email ?? email ?? '');
        router.replace(`/auth/verify-phone?email=${emailParam}`);
        return;
      }
      router.replace('/(tabs)/home');
    } catch (err: any) {
      const message = err?.message ?? 'Unable to verify email.';
      setError(message);
      show({ type: 'error', message });
    } finally {
      setIsLoading(false);
    }
  };

  const handleResend = async () => {
    try {
      if (cooldown > 0) return;
      setIsResending(true);
      setError(null);
      await resendVerificationRequest();
      show({ type: 'info', message: 'Verification email sent. Please check your inbox.' });
      setCooldown(60);
    } catch (err: any) {
      const message = err?.message ?? 'Unable to resend email.';
      setError(message);
      show({ type: 'error', message });
    } finally {
      setIsResending(false);
    }
  };

  return (
    <AuthScreen variant="register" contentStyle={styles.content}>
      <View>
        <Text style={styles.title}>Verify Email</Text>
        <Text style={styles.subtitle}>
          We sent a 4-digit verification code to {email || 'your email'}. Enter the code below.
        </Text>
      </View>

      <View style={styles.actions}>
        <View style={styles.codeRow}>
          {[0, 1, 2, 3].map((index) => (
            <TextInput
              key={index}
              ref={(el) => {
                inputs.current[index] = el;
              }}
              style={styles.codeInput}
              keyboardType="number-pad"
              maxLength={1}
              value={code[index] ?? ''}
              onChangeText={(value) => {
                const digit = value.replace(/\D/g, '').slice(0, 1);
                const next = code.split('');
                next[index] = digit;
                setCode(next.join(''));
                if (digit && index < 3) {
                  inputs.current[index + 1]?.focus();
                }
              }}
              onKeyPress={({ nativeEvent }) => {
                if (nativeEvent.key === 'Backspace' && !code[index] && index > 0) {
                  inputs.current[index - 1]?.focus();
                }
              }}
              returnKeyType={index === 3 ? 'done' : 'next'}
            />
          ))}
        </View>
        <PrimaryButton
          label={isLoading ? 'Verifying...' : 'Verify email'}
          onPress={handleVerify}
          disabled={isLoading}
        />
        <Pressable onPress={handleResend} disabled={isResending}>
          <Text style={styles.resendText}>
            {isResending
              ? 'Resending...'
              : cooldown > 0
              ? `Resend in 00:${String(cooldown).padStart(2, '0')}`
              : 'Resend verification email'}
          </Text>
        </Pressable>
        {error ? <Text style={styles.errorText}>{error}</Text> : null}
        <TextButton label="Back to login" onPress={() => router.replace('/auth/login')} />
      </View>
    </AuthScreen>
  );
}

const styles = StyleSheet.create({
  content: {
    justifyContent: 'space-between',
    paddingTop: theme.spacing.xl,
    paddingBottom: theme.spacing.lg,
  },
  title: {
    fontSize: theme.moderateScale(28),
    fontWeight: '800',
    color: theme.colors.ink,
  },
  subtitle: {
    marginTop: theme.spacing.sm,
    color: theme.colors.muted,
    fontSize: theme.moderateScale(14),
  },
  actions: {
    gap: theme.moderateScale(12),
    alignItems: 'center',
  },
  codeRow: {
    flexDirection: 'row',
    gap: theme.moderateScale(12),
  },
  codeInput: {
    width: theme.moderateScale(48),
    height: theme.moderateScale(56),
    borderRadius: theme.radius.md,
    borderWidth: 1,
    borderColor: theme.colors.borderSoft,
    textAlign: 'center',
    fontSize: theme.moderateScale(18),
    color: theme.colors.ink,
    backgroundColor: theme.colors.input,
  },
  resendText: {
    color: theme.colors.primary,
    fontSize: theme.moderateScale(14),
  },
  errorText: {
    color: theme.colors.danger,
    fontSize: theme.moderateScale(12),
    textAlign: 'center',
  },
});
