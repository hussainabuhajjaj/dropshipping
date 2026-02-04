import { useEffect, useRef, useState } from 'react';
import { router, useLocalSearchParams } from 'expo-router';
import { Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { AuthScreen } from '@/src/components/auth/AuthScreen';
import { PrimaryButton } from '@/src/components/buttons/PrimaryButton';
import { TextButton } from '@/src/components/buttons/TextButton';
import { theme } from '@/src/theme';
import { sendPhoneOtpRequest, verifyPhoneOtpRequest } from '@/src/api/auth';
import { useAuth } from '@/lib/authStore';
import { useToast } from '@/src/overlays/ToastProvider';

export default function VerifyPhoneScreen() {
  const { user } = useAuth();
  const { show } = useToast();
  const params = useLocalSearchParams<{ email?: string }>();
  const email = typeof params.email === 'string' ? params.email : user?.email ?? '';
  const phone = user?.phone ?? '';

  const [code, setCode] = useState('');
  const inputs = useRef<Array<TextInput | null>>([]);
  const [isSending, setIsSending] = useState(false);
  const [isVerifying, setIsVerifying] = useState(false);
  const [cooldown, setCooldown] = useState(60);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const sendInitial = async () => {
      setIsSending(true);
      setError(null);
      try {
        await sendPhoneOtpRequest();
        show({ type: 'info', message: 'Verification code sent to your email.' });
      } catch (err: any) {
        const message = err?.message ?? 'Unable to send verification code.';
        setError(message);
        show({ type: 'error', message });
      } finally {
        setIsSending(false);
      }
    };

    sendInitial();
  }, []);

  useEffect(() => {
    const timer = setInterval(() => {
      setCooldown((prev) => (prev > 0 ? prev - 1 : 0));
    }, 1000);
    return () => clearInterval(timer);
  }, []);

  useEffect(() => {
    if (code.length === 4 && !isVerifying) {
      handleVerify();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [code]);

  const handleVerify = async () => {
    if (code.trim().length !== 4) {
      setError('Enter the 4-digit verification code.');
      show({ type: 'warning', message: 'Enter the 4-digit verification code.' });
      return;
    }
    setIsVerifying(true);
    setError(null);
    try {
      await verifyPhoneOtpRequest({ code: code.trim() });
      show({ type: 'success', message: 'Phone verified successfully.' });
      router.replace('/(tabs)/home');
    } catch (err: any) {
      const message = err?.message ?? 'Invalid verification code.';
      setError(message);
      show({ type: 'error', message });
    } finally {
      setIsVerifying(false);
    }
  };

  const handleResend = async () => {
    if (cooldown > 0) return;
    setIsSending(true);
    setError(null);
    try {
      await sendPhoneOtpRequest();
      show({ type: 'info', message: 'Verification code resent.' });
      setCooldown(60);
    } catch (err: any) {
      const message = err?.message ?? 'Unable to resend code.';
      setError(message);
      show({ type: 'error', message });
    } finally {
      setIsSending(false);
    }
  };

  return (
    <AuthScreen variant="register" contentStyle={styles.content}>
      <View>
        <Text style={styles.title}>Verify Phone</Text>
        <Text style={styles.subtitle}>
          We sent a 4-digit verification code to {email || 'your email'} to confirm your phone
          number. Look for the subject “Your phone verification code”.
        </Text>
        {phone ? <Text style={styles.phone}>{phone}</Text> : null}
      </View>

      <View style={styles.form}>
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
        {error ? <Text style={styles.errorText}>{error}</Text> : null}
        <PrimaryButton
          label={isVerifying ? 'Verifying...' : 'Verify phone'}
          onPress={handleVerify}
          disabled={isVerifying}
        />
        <Pressable onPress={handleResend} disabled={isSending}>
          <Text style={styles.resendText}>
            {isSending
              ? 'Sending...'
              : cooldown > 0
              ? `Resend in 00:${String(cooldown).padStart(2, '0')}`
              : 'Resend code'}
          </Text>
        </Pressable>
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
  phone: {
    marginTop: theme.spacing.xs,
    color: theme.colors.ink,
    fontSize: theme.moderateScale(14),
  },
  form: {
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
