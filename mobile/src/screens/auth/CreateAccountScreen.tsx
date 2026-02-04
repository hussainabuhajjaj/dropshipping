import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useMemo, useState } from 'react';
import { Image, Platform, Pressable, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { LinearGradient } from 'expo-linear-gradient';
import * as ImagePicker from 'expo-image-picker';
import { RoundedInput } from '@/src/components/auth/RoundedInput';
import { PrimaryButton } from '@/src/components/buttons/PrimaryButton';
import { TextButton } from '@/src/components/buttons/TextButton';
import { theme } from '@/src/theme';
import { routes } from '@/src/navigation/routes';
import { useAuth } from '@/lib/authStore';
import { registerRequest } from '@/src/api/auth';

export default function CreateAccountScreen() {
  const { login } = useAuth();
  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [phone, setPhone] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [showErrors, setShowErrors] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [avatarBase64, setAvatarBase64] = useState<string | null>(null);

  const isValidEmail = useMemo(() => /\S+@\S+\.\S+/.test(email.trim()), [email]);
  const isValidPassword = useMemo(() => password.trim().length >= 6, [password]);
  const isValidPhone = useMemo(() => phone.trim().length >= 7, [phone]);
  const canContinue = isValidEmail && isValidPassword && isValidPhone;

  return (
    <LinearGradient colors={['#fdf1d9', '#fbe7c0', '#f7c985', '#c97b2f']} style={styles.root}>
      <SafeAreaView style={styles.safe}>
        <View style={styles.hero}>
          <Text style={styles.heroTitle}>Create your account</Text>
          <Text style={styles.heroSubtitle}>
            Join to save your cart, track orders, and get personalized picks.
          </Text>
        </View>

        <View style={styles.card}>
          <View style={styles.avatarRow}>
            <Pressable
              style={styles.avatarButton}
              onPress={async () => {
                const { status } = await ImagePicker.requestMediaLibraryPermissionsAsync();
                if (status !== 'granted') {
                  setError('Photo permission is required to upload an avatar.');
                  return;
                }

                const result = await ImagePicker.launchImageLibraryAsync({
                  mediaTypes: ImagePicker.MediaTypeOptions.Images,
                  allowsEditing: true,
                  aspect: [1, 1],
                  quality: 0.7,
                  base64: true,
                });

                if (!result.canceled && result.assets?.[0]?.base64) {
                  const base64 = result.assets[0].base64;
                  setAvatarBase64(`data:image/jpeg;base64,${base64}`);
                }
              }}
            >
              <View style={styles.avatarRing}>
                {avatarBase64 ? (
                  <Image source={{ uri: avatarBase64 }} style={styles.avatarImage} />
                ) : (
                  <Feather name="camera" size={theme.moderateScale(20)} color={theme.colors.primary} />
                )}
              </View>
            </Pressable>
            <View style={styles.avatarCopy}>
              <Text style={styles.avatarTitle}>Add a photo</Text>
              <Text style={styles.avatarSub}>Optional, but helps personalize your account.</Text>
            </View>
          </View>

          <View style={styles.inlineRow}>
            <View style={[styles.field, styles.inlineField]}>
              <RoundedInput
                placeholder="First name"
                inputProps={{
                  value: firstName,
                  onChangeText: setFirstName,
                  autoComplete: 'name-given',
                  textContentType: 'givenName',
                  returnKeyType: 'next',
                }}
              />
              {fieldErrors.first_name?.length ? (
                <Text style={styles.helperError}>{fieldErrors.first_name[0]}</Text>
              ) : null}
            </View>
            <View style={[styles.field, styles.inlineField]}>
              <RoundedInput
                placeholder="Last name"
                inputProps={{
                  value: lastName,
                  onChangeText: setLastName,
                  autoComplete: 'name-family',
                  textContentType: 'familyName',
                  returnKeyType: 'next',
                }}
              />
              {fieldErrors.last_name?.length ? (
                <Text style={styles.helperError}>{fieldErrors.last_name[0]}</Text>
              ) : null}
            </View>
          </View>

          <View style={styles.field}>
            <RoundedInput
              placeholder="Email"
              keyboardType="email-address"
              inputProps={{
                value: email,
                onChangeText: setEmail,
                autoComplete: 'email',
                textContentType: 'emailAddress',
                returnKeyType: 'next',
              }}
            />
            <Text style={[styles.helperText, showErrors && !isValidEmail ? styles.helperError : null]}>
              {showErrors && !isValidEmail ? 'Enter a valid email address.' : 'Use your active email for updates.'}
            </Text>
            {fieldErrors.email?.length ? (
              <Text style={styles.helperError}>{fieldErrors.email[0]}</Text>
            ) : null}
          </View>
          <View style={styles.field}>
            <RoundedInput
              placeholder="Password"
              secureTextEntry={!showPassword}
              right={
                <Pressable onPress={() => setShowPassword((prev) => !prev)} hitSlop={8}>
                  <Feather
                    name={showPassword ? 'eye-off' : 'eye'}
                    size={theme.moderateScale(16)}
                    color={theme.colors.mutedLight}
                  />
                </Pressable>
              }
              inputProps={{
                value: password,
                onChangeText: setPassword,
                autoComplete: 'password',
                textContentType: 'newPassword',
                returnKeyType: 'next',
              }}
            />
            <Text style={[styles.helperText, showErrors && !isValidPassword ? styles.helperError : null]}>
              {showErrors && !isValidPassword ? 'Use at least 6 characters.' : 'Use at least 6 characters.'}
            </Text>
            {fieldErrors.password?.length ? (
              <Text style={styles.helperError}>{fieldErrors.password[0]}</Text>
            ) : null}
          </View>
          <View style={styles.field}>
            <RoundedInput
              placeholder="Your number"
              keyboardType="phone-pad"
              left={
                <View style={styles.phonePrefix}>
                  <Feather name="flag" size={theme.moderateScale(16)} color={theme.colors.primary} />
                  <Feather
                    name="chevron-down"
                    size={theme.moderateScale(14)}
                    color={theme.colors.mutedLight}
                  />
                  <View style={styles.phoneDivider} />
                </View>
              }
              inputStyle={styles.phoneInput}
              inputProps={{
                value: phone,
                onChangeText: setPhone,
                autoComplete: 'tel',
                textContentType: 'telephoneNumber',
                returnKeyType: 'done',
              }}
            />
            <Text style={[styles.helperText, showErrors && !isValidPhone ? styles.helperError : null]}>
              {showErrors && !isValidPhone ? 'Enter a valid phone number.' : 'Used for delivery updates.'}
            </Text>
            {fieldErrors.phone?.length ? (
              <Text style={styles.helperError}>{fieldErrors.phone[0]}</Text>
            ) : null}
          </View>

          <PrimaryButton
            label={isLoading ? 'Creating...' : 'Create account'}
            onPress={async () => {
              if (!canContinue) {
                setShowErrors(true);
                return;
              }
              try {
                setIsLoading(true);
                setError(null);
                setFieldErrors({});
                const payload = await registerRequest({
                  first_name: firstName.trim() || undefined,
                  last_name: lastName.trim() || undefined,
                  email: email.trim(),
                  password: password.trim(),
                  phone: phone.trim(),
                  device_name: Platform.OS,
                  avatar: avatarBase64 ?? undefined,
                });
                const user = payload.user;
                const fullName = `${user.first_name ?? ''} ${user.last_name ?? ''}`.trim();
                const name = user.name ?? (fullName || 'Customer');
                login(
                  {
                    name,
                    email: user.email ?? email,
                    avatar: user.avatar ?? avatarBase64,
                    phone: user.phone ?? phone,
                  },
                  payload.token ?? null
                );
                const emailParam = encodeURIComponent(user.email ?? email);
                router.replace(`${routes.verify}?email=${emailParam}`);
              } catch (err: any) {
                setError(err?.message ?? 'Registration failed.');
                if (err?.errors && typeof err.errors === 'object') {
                  setFieldErrors(err.errors);
                }
              } finally {
                setIsLoading(false);
              }
            }}
            disabled={!canContinue}
          />
          {error ? <Text style={styles.errorText}>{error}</Text> : null}
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
    marginTop: theme.spacing.lg,
    maxWidth: theme.moderateScale(320),
  },
  heroTitle: {
    fontSize: theme.moderateScale(30),
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
    backgroundColor: 'rgba(255,255,255,0.9)',
    borderRadius: theme.radius.xl,
    padding: theme.spacing.lg,
    gap: theme.moderateScale(12),
    ...theme.shadows.md,
  },
  avatarRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.moderateScale(12),
  },
  avatarButton: {
    alignSelf: 'flex-start',
  },
  avatarRing: {
    width: theme.moderateScale(64),
    height: theme.moderateScale(64),
    borderRadius: theme.moderateScale(32),
    borderWidth: theme.moderateScale(2),
    borderColor: theme.colors.primary,
    borderStyle: 'dashed',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: theme.colors.white,
  },
  avatarImage: {
    width: theme.moderateScale(56),
    height: theme.moderateScale(56),
    borderRadius: theme.moderateScale(28),
  },
  avatarCopy: {
    flex: 1,
  },
  avatarTitle: {
    fontSize: theme.moderateScale(14),
    fontWeight: '700',
    color: theme.colors.ink,
  },
  avatarSub: {
    marginTop: theme.moderateScale(4),
    fontSize: theme.moderateScale(12),
    color: theme.colors.muted,
  },
  inlineRow: {
    flexDirection: 'row',
    gap: theme.moderateScale(12),
  },
  inlineField: {
    flex: 1,
  },
  field: {
    gap: theme.moderateScale(6),
  },
  helperText: {
    fontSize: theme.moderateScale(12),
    color: theme.colors.muted,
  },
  helperError: {
    color: theme.colors.danger,
  },
  phonePrefix: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.moderateScale(6),
  },
  phoneDivider: {
    width: theme.moderateScale(1),
    height: theme.moderateScale(22),
    backgroundColor: theme.colors.inputBorder,
    marginLeft: theme.moderateScale(6),
  },
  phoneInput: {
    paddingLeft: theme.moderateScale(6),
  },
  actions: {
    alignItems: 'center',
    gap: theme.moderateScale(12),
  },
  errorText: {
    fontSize: theme.moderateScale(12),
    color: theme.colors.danger,
  },
  cancelText: {
    color: theme.colors.ink,
  },
});
