import { router } from 'expo-router';
import { StyleSheet, Text, View } from 'react-native';
import { AuthScreen } from '@/src/components/auth/AuthScreen';
import { AvatarBadge } from '@/src/components/auth/AvatarBadge';
import { RoundedInput } from '@/src/components/auth/RoundedInput';
import { PrimaryButton } from '@/src/components/buttons/PrimaryButton';
import { TextButton } from '@/src/components/buttons/TextButton';
import { theme } from '@/src/theme';
import { routes } from '@/src/navigation/routes';

export default function NewPasswordScreen() {
  return (
    <AuthScreen variant="recovery" contentStyle={styles.content}>
      <View style={styles.header}>
        <AvatarBadge innerColor="#f4d6e6" />
        <Text style={styles.title}>Setup New Password</Text>
        <Text style={styles.subtitle}>Please, setup a new password for your account</Text>
      </View>

      <View style={styles.form}>
        <RoundedInput placeholder="New Password" secureTextEntry containerStyle={styles.input} />
        <RoundedInput placeholder="Repeat Password" secureTextEntry containerStyle={styles.input} />
      </View>

      <View style={styles.actions}>
        <PrimaryButton label="Save" onPress={() => router.push(routes.login)} />
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
  form: {
    gap: theme.moderateScale(12),
    marginTop: theme.moderateScale(18),
  },
  input: {
    backgroundColor: theme.colors.primarySoftLight,
  },
  actions: {
    alignItems: 'center',
    gap: theme.moderateScale(12),
  },
  cancelText: {
    color: theme.colors.muted,
  },
});
