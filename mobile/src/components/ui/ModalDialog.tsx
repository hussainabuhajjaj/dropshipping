import React from 'react';
import { Modal, View, Text, Pressable, StyleSheet, TouchableWithoutFeedback } from 'react-native';
import { theme } from '@/src/theme';

export default function ModalDialog({ visible, onClose, children }: { visible: boolean; onClose: () => void; children: React.ReactNode }) {
  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onClose}>
      <TouchableWithoutFeedback onPress={onClose}>
        <View style={styles.backdrop} />
      </TouchableWithoutFeedback>
      <View style={styles.container} pointerEvents="box-none">
        <View style={styles.dialog}>
          {children}
          <Pressable onPress={onClose} style={styles.closeButton}>
            <Text style={styles.closeText}>Close</Text>
          </Pressable>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  backdrop: { flex: 1, backgroundColor: 'rgba(0,0,0,0.45)' },
  container: { position: 'absolute', left: 0, right: 0, top: 0, bottom: 0, alignItems: 'center', justifyContent: 'center' },
  dialog: { width: '86%', backgroundColor: theme.colors.white, borderRadius: 16, padding: 18, alignItems: 'center' },
  closeButton: { marginTop: 12, paddingVertical: 8, paddingHorizontal: 12, borderRadius: 10, backgroundColor: theme.colors.sand },
  closeText: { color: theme.colors.inkDark },
});
