import { ReactNode } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { Portal } from './Portal';
import { theme } from '@/src/theme';

type ModalSheetProps = {
  visible: boolean;
  onClose: () => void;
  children: ReactNode;
};

export function ModalSheet({ visible, onClose, children }: ModalSheetProps) {
  const insets = useSafeAreaInsets();

  if (!visible) {
    return null;
  }

  return (
    <Portal priority={2000}>
      <View style={styles.overlay} pointerEvents="box-none">
        <Pressable style={styles.backdrop} onPress={onClose} />
        <View
          style={[
            styles.sheet,
            { paddingBottom: Math.max(theme.moderateScale(16), insets.bottom) },
          ]}
        >
          {children}
        </View>
      </View>
    </Portal>
  );
}

const styles = StyleSheet.create({
  overlay: {
    ...StyleSheet.absoluteFillObject,
    justifyContent: 'flex-end',
  },
  backdrop: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: theme.colors.overlay,
  },
  sheet: {
    backgroundColor: theme.colors.white,
    borderTopLeftRadius: theme.moderateScale(24),
    borderTopRightRadius: theme.moderateScale(24),
    paddingHorizontal: theme.moderateScale(20),
    paddingTop: theme.moderateScale(18),
  },
});
