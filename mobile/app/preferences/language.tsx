import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator } from 'react-native';
import { Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
import { usePreferences } from '@/src/store/preferencesStore';
import { useToast } from '@/src/overlays/ToastProvider';
import { useTranslations } from '@/src/i18n/TranslationsProvider';
import { SafeAreaView } from 'react-native-safe-area-context';
const fallbackLanguages = ['English', 'French', 'German', 'Spanish', 'Arabic'];

export default function ChooseLanguageScreen() {
  const { state, setLanguage } = usePreferences();
  const { show } = useToast();
  const { t } = useTranslations();
  const [updating, setUpdating] = useState<string | null>(null);
  const selected = state.language;
  const languages = state.lookups.languages.length > 0 ? state.lookups.languages : fallbackLanguages;

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView style={styles.scroll} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.headerRow}>
          <Pressable style={styles.iconButton} onPress={() => router.back()}>
            <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
          </Pressable>
          <Text style={styles.title}>{t('Choose language', 'Choose language')}</Text>
          <Pressable style={styles.iconButton} onPress={() => router.push('/(tabs)/home')}>
            <Feather name="x" size={16} color={theme.colors.inkDark} />
          </Pressable>
        </View>

        <View style={styles.list}>
          {languages.map((language) => (
            <Pressable
              key={language}
              style={styles.row}
              disabled={Boolean(updating)}
              onPress={async () => {
                if (language === selected || updating) return;
                setUpdating(language);
                const result = await setLanguage(language);
                setUpdating(null);
                if (result.ok) {
                  show({ type: 'success', message: t('Language updated.', 'Language updated.') });
                  router.back();
                } else {
                  show({
                    type: 'error',
                    message: result.message ?? t('Unable to update language.', 'Unable to update language.'),
                  });
                }
              }}
            >
              <Text style={styles.rowText}>{language}</Text>
              {updating === language ? (
                <ActivityIndicator size="small" color={theme.colors.inkDark} />
              ) : selected === language ? (
                <Feather name="check" size={16} color={theme.colors.inkDark} />
              ) : null}
            </Pressable>
          ))}
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.white,
  },
  scroll: {
    flex: 1,
  },
  content: {
    paddingHorizontal: 20,
    paddingTop: 12,
    paddingBottom: 32,
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 20,
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
  list: {
    gap: 12,
  },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: 14,
    borderRadius: 18,
    backgroundColor: theme.colors.sand,
  },
  rowText: {
    fontSize: 14,
    fontWeight: '600',
    color: theme.colors.inkDark,
  },
});
