import { useMemo, useState } from 'react';
import { Platform, StyleSheet, Text, View } from 'react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { PasswordEntry } from './PasswordEntry';
import { routes } from '@/src/navigation/routes';
import { useAuth } from '@/lib/authStore';
import { RoundedInput } from '@/src/components/auth/RoundedInput';
import { PrimaryButton } from '@/src/components/buttons/PrimaryButton';
import { theme } from '@/src/theme';
import { loginRequest } from '@/src/api/auth';
import { AuthScreen } from '@/src/components/auth/AuthScreen';

export default function PasswordScreen() {
  const [variant, setVariant] = useState<'empty' | 'typing' | 'wrong'>('empty');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [isLoading, setIsLoading] = useState(false);
  const { login } = useAuth();
  const params = useLocalSearchParams<{ email?: string; name?: string; avatar?: string }>();
  const email = typeof params.email === 'string' ? params.email : '';
  const avatar = typeof params.avatar === 'string' ? params.avatar : null;
  const nameParam = typeof params.name === 'string' ? params.name : '';
  const displayName = useMemo(() => {
    if (nameParam.trim()) return nameParam.trim();
    if (!email) return '';
    const local = email.split('@')[0] ?? '';
    if (!local) return '';
    return local
      .split(/[._-]+/)
      .filter(Boolean)
      .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
      .join(' ');
  }, [email, nameParam]);
  const isValidPassword = useMemo(() => password.trim().length >= 6, [password]);

  const handleLogin = async () => {
    if (!email || !isValidPassword || isLoading) {
      setVariant('wrong');
      setError(!email ? 'Missing email address.' : 'Enter a valid password.');
      return;
    }

    try {
      setIsLoading(true);
      setError(null);
      setFieldErrors({});
      const payload = await loginRequest({
        email: email.trim(),
        password: password.trim(),
        device_name: Platform.OS,
      });
      const user = payload.user;
      const fullName = `${user.first_name ?? ''} ${user.last_name ?? ''}`.trim();
      const name = user.name ?? (fullName || 'Customer');

      login(
        {
          name,
          email: user.email ?? email,
          phone: user.phone ?? null,
        },
        payload.token ?? null
      );
      if (!user.email_verified_at && !user.is_verified) {
        const emailParam = encodeURIComponent(user.email ?? email);
        router.replace(`${routes.verify}?email=${emailParam}`);
        return;
      }
      if (!user.phone_verified_at && !user.is_phone_verified) {
        const emailParam = encodeURIComponent(user.email ?? email);
        router.replace(`/auth/verify-phone?email=${emailParam}`);
        return;
      }
      router.replace('/(tabs)/home');
    } catch (err: any) {
      setVariant('wrong');
      setError(err?.message ?? 'Login failed.');
      if (err?.errors && typeof err.errors === 'object') {
        setFieldErrors(err.errors);
      }
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <AuthScreen variant="login" contentStyle={styles.content}>
      <View style={styles.heroCard}>
        <PasswordEntry
          variant={variant}
          name={displayName}
          avatarUri={avatar}
          onAdvance={() => {
            if (variant === 'empty') {
              setVariant('typing');
              return;
            }
          }}
          onNotYou={() => router.push(routes.login)}
          onDotsPress={() => {
            if (variant === 'typing') {
              setVariant('wrong');
              return;
            }
            if (variant === 'wrong') {
              setVariant('typing');
            }
          }}
          onForgot={() => router.push(routes.forgot)}
        />
      </View>

      <View style={styles.formCard}>
        {email ? (
          <View style={styles.formHeader}>
            <Text style={styles.formLabel}>Signing in as</Text>
            <Text style={styles.formValue}>{email}</Text>
          </View>
        ) : null}
        <RoundedInput
          placeholder="Password"
          secureTextEntry
          inputProps={{
            value: password,
            onChangeText: setPassword,
            autoComplete: 'password',
            textContentType: 'password',
            returnKeyType: 'done',
          }}
        />
        {fieldErrors.email?.length ? (
          <Text style={styles.errorText}>{fieldErrors.email[0]}</Text>
        ) : fieldErrors.password?.length ? (
          <Text style={styles.errorText}>{fieldErrors.password[0]}</Text>
        ) : error ? (
          <Text style={styles.errorText}>{error}</Text>
        ) : null}
        <PrimaryButton
          label={isLoading ? 'Signing in...' : 'Login'}
          disabled={!isValidPassword || isLoading}
          onPress={handleLogin}
        />
      </View>
    </AuthScreen>
  );
}

const styles = StyleSheet.create({
  content: {
    flex: 1,
    justifyContent: 'space-between',
    paddingTop: theme.spacing.lg,
    paddingBottom: theme.spacing.lg,
  },
  heroCard: {
    alignSelf: 'center',
    width: '100%',
    maxWidth: theme.moderateScale(360),
    backgroundColor: theme.colors.primarySoftLight,
    borderRadius: theme.radius.xl,
    paddingVertical: theme.spacing.lg,
    paddingHorizontal: theme.spacing.lg,
    ...theme.shadows.sm,
  },
  formCard: {
    backgroundColor: theme.colors.white,
    borderRadius: theme.radius.lg,
    padding: theme.spacing.lg,
    gap: theme.moderateScale(12),
    ...theme.shadows.sm,
  },
  formHeader: {
    gap: theme.moderateScale(4),
  },
  formLabel: {
    fontSize: theme.moderateScale(12),
    color: theme.colors.muted,
    textTransform: 'uppercase',
    letterSpacing: theme.moderateScale(0.6),
  },
  formValue: {
    fontSize: theme.moderateScale(14),
    color: theme.colors.ink,
    fontWeight: '600',
  },
  errorText: {
    color: theme.colors.danger,
    fontSize: theme.moderateScale(12),
  },
});
