import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Image, KeyboardAvoidingView, Platform, ScrollView, TextInput, View } from 'react-native';
import { Pressable, StyleSheet, Text } from '@/src/utils/responsiveStyleSheet';
import { SafeAreaView } from 'react-native-safe-area-context';
import * as ImagePicker from 'expo-image-picker';
import { theme } from '@/src/theme';
import { useAuth } from '@/lib/authStore';
import { meRequest, sendPhoneOtpRequest, updateProfileRequest } from '@/src/api/auth';
import { PrimaryButton } from '@/src/components/buttons/PrimaryButton';
import { useToast } from '@/src/overlays/ToastProvider';

export default function SettingsProfileScreen() {
  const { user, updateUser, status } = useAuth();
  const { show } = useToast();
  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [initialPhone, setInitialPhone] = useState('');
  const [avatarBase64, setAvatarBase64] = useState<string | null>(null);
  const [avatarUri, setAvatarUri] = useState<string | null>(user?.avatar ?? null);
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [isEmailVerified, setIsEmailVerified] = useState<boolean>(true);
  const [isPhoneVerified, setIsPhoneVerified] = useState<boolean>(true);
  const [hydrated, setHydrated] = useState(false);
  const hasLoadedMe = useRef(false);

  const fullName = useMemo(() => `${firstName} ${lastName}`.trim(), [firstName, lastName]);

  const formatCIPretty = (value: string) => {
    const digits = value.replace(/\D/g, '');
    const local = digits.startsWith('225') ? digits.slice(3) : digits;
    const pairs = local.match(/.{1,2}/g) ?? [];
    return `${digits.startsWith('225') ? '+225 ' : ''}${pairs.join(' ')}`.trim();
  };

  const normalizeCI = (value: string) => {
    const digits = value.replace(/\D/g, '');
    if (!digits) return '';
    if (digits.startsWith('225')) return `+${digits}`;
    return digits.startsWith('0') ? `+225${digits}` : `+2250${digits}`;
  };

  useEffect(() => {
    if (status !== 'authed') {
      hasLoadedMe.current = false;
      setHydrated(false);
      return;
    }
    if (hasLoadedMe.current) return;
    hasLoadedMe.current = true;

    const hydrate = async () => {
      try {
        const me = await meRequest();
        setFirstName(me.first_name ?? '');
        setLastName(me.last_name ?? '');
        setEmail(me.email ?? '');
        const resolvedPhone = me.phone ?? '';
        setPhone(formatCIPretty(resolvedPhone));
        setInitialPhone(resolvedPhone);
        setAvatarUri(me.avatar ?? null);
        setIsEmailVerified(Boolean(me.email_verified_at));
        setIsPhoneVerified(Boolean(me.phone_verified_at));
        setHydrated(true);
      } catch {
        // ignore and fall back to cached user in separate effect
      }
    };

    hydrate();
  }, [status]);

  useEffect(() => {
    if (hydrated || !user) return;
    setEmail(user.email ?? '');
    setAvatarUri(user.avatar ?? null);
    const nameParts = (user.name ?? '').split(' ');
    setFirstName(nameParts[0] ?? '');
    setLastName(nameParts.slice(1).join(' '));
    setInitialPhone(user.phone ?? '');
    setPhone(formatCIPretty(user.phone ?? ''));
    setHydrated(true);
  }, [hydrated, user]);

  const handlePickAvatar = async () => {
    const { status } = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (status !== 'granted') {
      setError('Photo permission is required to upload an avatar.');
      show({ type: 'warning', message: 'Photo permission is required to upload an avatar.' });
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
      setAvatarUri(`data:image/jpeg;base64,${base64}`);
    }
  };

  const handleSave = async () => {
    try {
      setIsSaving(true);
      setError(null);
      setFieldErrors({});
      const payload = await updateProfileRequest({
        name: fullName || undefined,
        first_name: firstName || undefined,
        last_name: lastName || undefined,
        email: email.trim() || undefined,
        phone: normalizeCI(phone.trim()) || undefined,
        avatar: avatarBase64 ?? undefined,
      });

      updateUser({
        name: (payload.name ?? fullName) || 'Customer',
        email: payload.email ?? email,
        avatar: payload.avatar ?? avatarUri ?? null,
        phone: payload.phone ?? phone,
      });

      show({ type: 'success', message: 'Profile updated.' });
      setIsEmailVerified(Boolean(payload.email_verified_at));
      setIsPhoneVerified(Boolean(payload.phone_verified_at));
      const normalizedPhone = normalizeCI(phone.trim());
      const phoneChanged = normalizedPhone !== (initialPhone || '').trim();
      if (phoneChanged) {
        await sendPhoneOtpRequest({ phone: normalizedPhone || undefined });
        show({ type: 'info', message: 'Phone verification code sent.' });
        router.replace('/auth/verify-phone');
        return;
      }
      if (!payload.email_verified_at) {
        const emailParam = encodeURIComponent(payload.email ?? email);
        router.replace(`/auth/verify?email=${emailParam}`);
        return;
      }
    } catch (err: any) {
      const message = err?.message ?? 'Unable to update profile.';
      setError(message);
      if (err?.errors && typeof err.errors === 'object') {
        setFieldErrors(err.errors);
      }
      show({ type: 'error', message });
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <SafeAreaView style={styles.container}>
      <KeyboardAvoidingView
        style={styles.keyboard}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        keyboardVerticalOffset={Platform.OS === 'ios' ? theme.moderateScale(20) : 0}
      >
        <ScrollView
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
            <Text style={styles.title}>Profile</Text>
            <Pressable style={styles.iconButton} onPress={() => router.push('/(tabs)/home')}>
              <Feather name="x" size={16} color={theme.colors.inkDark} />
            </Pressable>
          </View>

          <View style={styles.avatarRow}>
            <View style={styles.avatarWrap}>
              {avatarUri ? (
                <Image source={{ uri: avatarUri }} style={styles.avatarImage} />
              ) : (
                <View style={styles.avatarPlaceholder} />
              )}
            </View>
            <Pressable style={styles.avatarButton} onPress={handlePickAvatar}>
              <Text style={styles.avatarButtonText}>Change photo</Text>
            </Pressable>
          </View>

          <View style={styles.field}>
            <Text style={styles.label}>First name</Text>
            <TextInput
              style={styles.input}
              placeholder="First name"
              placeholderTextColor="#b6b6b6"
              value={firstName}
              onChangeText={setFirstName}
            />
          </View>
          <View style={styles.field}>
            <Text style={styles.label}>Last name</Text>
            <TextInput
              style={styles.input}
              placeholder="Last name"
              placeholderTextColor="#b6b6b6"
              value={lastName}
              onChangeText={setLastName}
            />
          </View>
          <View style={styles.field}>
            <View style={styles.labelRow}>
              <Text style={styles.label}>Email</Text>
              <Pressable onPress={() => router.replace(`/auth/verify?email=${encodeURIComponent(email)}`)}>
                <Text style={[styles.badge, isEmailVerified ? styles.badgeSuccess : styles.badgeWarn]}>
                  {isEmailVerified ? 'Verified' : 'Verify'}
                </Text>
              </Pressable>
            </View>
            <TextInput
              style={styles.input}
              placeholder="email@example.com"
              placeholderTextColor="#b6b6b6"
              keyboardType="email-address"
              value={email}
              onChangeText={setEmail}
            />
            {fieldErrors.email?.length ? <Text style={styles.errorText}>{fieldErrors.email[0]}</Text> : null}
          </View>
          <View style={styles.field}>
            <View style={styles.labelRow}>
              <Text style={styles.label}>Phone</Text>
              <Pressable onPress={() => router.replace('/auth/verify-phone')}>
                <Text style={[styles.badge, isPhoneVerified ? styles.badgeSuccess : styles.badgeWarn]}>
                  {isPhoneVerified ? 'Verified' : 'Verify'}
                </Text>
              </Pressable>
            </View>
            <TextInput
              style={styles.input}
              placeholder="+225 07 12 34 56 78"
              placeholderTextColor="#b6b6b6"
              keyboardType="phone-pad"
              value={phone}
              onChangeText={setPhone}
              onBlur={() => setPhone(formatCIPretty(phone))}
              autoComplete="tel"
              textContentType="telephoneNumber"
              autoCorrect={false}
            />
            {fieldErrors.phone?.length ? <Text style={styles.errorText}>{fieldErrors.phone[0]}</Text> : null}
          </View>

          {error ? <Text style={styles.errorText}>{error}</Text> : null}

          <PrimaryButton label={isSaving ? 'Saving...' : 'Save changes'} onPress={handleSave} disabled={isSaving} />
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
  content: {
    paddingHorizontal: 20,
    paddingTop: 12,
    paddingBottom: 32,
    gap: 12,
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  title: {
    fontSize: 18,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  iconButton: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: theme.colors.sand,
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarRow: {
    alignItems: 'center',
    marginBottom: 8,
  },
  avatarWrap: {
    width: 88,
    height: 88,
    borderRadius: 44,
    backgroundColor: theme.colors.sand,
    overflow: 'hidden',
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarImage: {
    width: '100%',
    height: '100%',
  },
  avatarPlaceholder: {
    width: 72,
    height: 72,
    borderRadius: 36,
    backgroundColor: '#e1e5f2',
  },
  avatarButton: {
    marginTop: 12,
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 16,
    backgroundColor: theme.colors.blueSoft,
  },
  avatarButtonText: {
    fontSize: 12,
    fontWeight: '700',
    color: theme.colors.sun,
  },
  field: {
    marginBottom: 6,
  },
  labelRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  label: {
    fontSize: 12,
    fontWeight: '600',
    color: theme.colors.mutedDark,
    marginBottom: 8,
  },
  badge: {
    fontSize: 11,
    fontWeight: '700',
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 999,
    overflow: 'hidden',
  },
  badgeSuccess: {
    backgroundColor: theme.colors.green,
    color: theme.colors.white,
  },
  badgeWarn: {
    backgroundColor: theme.colors.sun,
    color: theme.colors.inkDark,
  },
  input: {
    borderRadius: 16,
    backgroundColor: theme.colors.sand,
    paddingHorizontal: 14,
    paddingVertical: 12,
    fontSize: 13,
    color: theme.colors.inkDark,
  },
  errorText: {
    fontSize: 12,
    color: theme.colors.danger,
  },
});
