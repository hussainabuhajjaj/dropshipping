import { ReactNode } from 'react';
import { SafeAreaView } from 'react-native-safe-area-context';
import { StyleProp, StyleSheet, View, ViewStyle } from 'react-native';
import { theme } from '@/src/theme';
import { AuthBlobBackground } from './AuthBlobBackground';

type AuthScreenProps = {
  variant: 'login' | 'recovery' | 'register';
  children: ReactNode;
  contentStyle?: StyleProp<ViewStyle>;
};

export function AuthScreen({ variant, children, contentStyle }: AuthScreenProps) {
  return (
    <SafeAreaView style={styles.container}>
      <AuthBlobBackground variant={variant} />
      <View style={[styles.content, contentStyle]}>{children}</View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.white,
  },
  content: {
    flex: 1,
    paddingHorizontal: theme.spacing.lg,
    paddingTop: theme.spacing.lg,
    paddingBottom: theme.spacing.lg,
  },
});
