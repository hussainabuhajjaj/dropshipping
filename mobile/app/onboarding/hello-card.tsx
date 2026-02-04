import { Stack } from 'expo-router';
import HelloCardScreen from '@/src/screens/onboarding/HelloCardScreen';

export default function HelloCardRoute() {
  return (
    <>
      <Stack.Screen options={{ headerShown: false }} />
      <HelloCardScreen />
    </>
  );
}
