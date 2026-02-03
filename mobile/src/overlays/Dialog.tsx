import { ReactNode, useEffect } from 'react';
import { BackHandler, Platform, Pressable, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { Portal } from './Portal';
import { theme } from '@/src/theme';

type DialogProps = {
  visible: boolean;
  onClose: () => void;
  children: ReactNode;
};

export function Dialog({ visible, onClose, children }: DialogProps) {
  const insets = useSafeAreaInsets();

  useEffect(() => {
    if (!visible) return;
    const sub = BackHandler.addEventListener('hardwareBackPress', () => {
      onClose();
      return true;
    });
    return () => sub.remove();
  }, [visible, onClose]);

  if (!visible) {
    return null;
  }

  return (
    <Portal priority={3000}>
      <View
        style={[
          styles.overlay,
          {
            paddingTop: Math.max(insets.top, theme.moderateScale(8)),
            paddingBottom: Math.max(insets.bottom, theme.moderateScale(8)),
          },
        ]}
        accessibilityViewIsModal
        accessibilityLabel="Dialog"
        accessibilityRole={Platform.OS === 'web' ? undefined : 'alert'}
      >
        <Pressable
          style={styles.backdrop}
          onPress={onClose}
          accessibilityRole="button"
          accessibilityLabel="Dismiss dialog"
        />
        <View style={styles.dialog} accessibilityRole="none" importantForAccessibility="yes">
          {children}
        </View>
      </View>
    </Portal>
  );
}

const styles = StyleSheet.create({
  overlay: {
    ...StyleSheet.absoluteFillObject,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: theme.moderateScale(24),
  },
  backdrop: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: theme.colors.overlay,
  },
  dialog: {
    width: '100%',
    maxWidth: theme.moderateScale(320),
    borderRadius: theme.moderateScale(24),
    backgroundColor: theme.colors.white,
    paddingHorizontal: theme.moderateScale(20),
    paddingVertical: theme.moderateScale(20),
    alignItems: 'center',
  },
});
