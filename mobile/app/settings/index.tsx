import { Stack } from 'expo-router';
import SettingsScreen from '@/src/screens/settings/SettingsScreen';
import { useTranslations } from '@/src/i18n/TranslationsProvider';

export default function SettingsRoute() {
  const { t } = useTranslations();
  return (
    <>
      <Stack.Screen options={{ title: t('Settings', 'Settings') }} />
      <SettingsScreen />
    </>
  );
}
