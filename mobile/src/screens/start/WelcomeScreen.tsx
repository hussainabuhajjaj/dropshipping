import { router } from 'expo-router';
import { useEffect, useRef } from 'react';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Animated, Image, Pressable, StyleSheet, Text, View } from 'react-native';
import { IconCircleButton } from '@/src/components/buttons/IconCircleButton';
import { PrimaryButton } from '@/src/components/buttons/PrimaryButton';
import { AuthBlobBackground } from '@/src/components/auth/AuthBlobBackground';
import { theme } from '@/src/theme';
import { routes } from '@/src/navigation/routes';
import { useAuth } from '@/lib/authStore';

export default function WelcomeScreen() {
  const { status } = useAuth();
  const pulseAnim = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    if (status === 'authed') {
      router.replace('/(tabs)/home');
    }
  }, [status]);

  useEffect(() => {
    const loop = Animated.loop(
      Animated.sequence([
        Animated.timing(pulseAnim, {
          toValue: 1,
          duration: 900,
          useNativeDriver: true,
        }),
        Animated.timing(pulseAnim, {
          toValue: 0,
          duration: 900,
          useNativeDriver: true,
        }),
      ]),
    );

    loop.start();
    return () => loop.stop();
  }, [pulseAnim]);

  const scale = pulseAnim.interpolate({
    inputRange: [0, 1],
    outputRange: [1, 1.06],
  });
  const opacity = pulseAnim.interpolate({
    inputRange: [0, 1],
    outputRange: [0.88, 1],
  });

  return (
    <SafeAreaView style={styles.container}>
      <AuthBlobBackground variant="welcome" />
      <View style={styles.center}>
        <View style={styles.logoWrap}>
          <Animated.View style={[styles.logoInner, { transform: [{ scale }], opacity }]}>
            <Image
              source={require('@/assets/images/logo1.png')}
              style={styles.logoImage}
              resizeMode="contain"
            />
          </Animated.View>
        </View>
        <Text style={styles.brand}>Simbazu</Text>
        <Text style={styles.tagline}>
          Shop now, smile sooner.{'\n'}Simbazu
        </Text>
      </View>

      <View style={styles.actions}>
        <PrimaryButton label="Let's get started" onPress={() => router.push('/onboarding/hello-card')} />
        <Pressable style={styles.loginRow} onPress={() => router.push(routes.login)}>
          <Text style={styles.loginText}>I already have an account</Text>
          <IconCircleButton icon="arrow-right" size={theme.moderateScale(32)} />
        </Pressable>
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.white,
    paddingHorizontal: theme.spacing.lg,
    paddingTop: theme.spacing.lg,
    paddingBottom: theme.spacing.lg,
    justifyContent: 'space-between',
  },
  center: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  logoWrap: {
    width: theme.moderateScale(140),
    height: theme.moderateScale(140),
    borderRadius: theme.moderateScale(70),
    backgroundColor: theme.colors.white,
    alignItems: 'center',
    justifyContent: 'center',
    ...theme.shadows.md,
  },
  logoInner: {
    width: theme.moderateScale(200),
    height: theme.moderateScale(200),
    borderRadius: theme.moderateScale(100),
    backgroundColor: theme.colors.sand,
    alignItems: 'center',
    justifyContent: 'center',
  },
  logoImage: {
    width: theme.moderateScale(200),
    height: theme.moderateScale(200),
  },
  brand: {
    marginTop: theme.moderateScale(26),
    fontSize: theme.moderateScale(32),
    fontWeight: '800',
    color: theme.colors.ink,
    letterSpacing: theme.moderateScale(0.6),
  },
  tagline: {
    marginTop: theme.moderateScale(10),
    fontSize: theme.moderateScale(14),
    lineHeight: theme.moderateScale(20),
    color: theme.colors.muted,
    textAlign: 'center',
  },
  actions: {
    alignItems: 'center',
    gap: theme.moderateScale(18),
  },
  loginRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.moderateScale(12),
  },
  loginText: {
    fontSize: theme.moderateScale(13),
    color: theme.colors.mutedLight,
    fontWeight: '500',
  },
});
