import { createContext, ReactNode, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { Animated, StyleSheet, View } from 'react-native';
import { Text } from '@/src/components/i18n/Text';
import { Feather } from '@expo/vector-icons';
import { PortalContext } from './PortalHost';
import { theme } from '@/src/theme';

export type ToastType = 'success' | 'warning' | 'error' | 'info';

type Toast = {
  id: string;
  type: ToastType;
  message: string;
  title?: string;
  duration?: number;
};

type ToastContextValue = {
  show: (toast: Omit<Toast, 'id'>) => void;
};

const ToastContext = createContext<ToastContextValue | null>(null);

const TYPE_STYLES: Record<ToastType, { bg: string; text: string; icon: keyof typeof Feather.glyphMap }>
  = {
    success: { bg: '#0f9d58', text: '#ffffff', icon: 'check-circle' },
    warning: { bg: '#f6a700', text: '#1f1f1f', icon: 'alert-triangle' },
    error: { bg: theme.colors.danger, text: '#ffffff', icon: 'x-circle' },
    info: { bg: '#2f6fed', text: '#ffffff', icon: 'info' },
  };

export function ToastProvider({ children }: { children: ReactNode }) {
  const portal = useContext(PortalContext);
  const [toasts, setToasts] = useState<Toast[]>([]);
  const timeouts = useRef<Record<string, NodeJS.Timeout>>({});

  const show = useCallback((toast: Omit<Toast, 'id'>) => {
    const id = `${Date.now()}-${Math.random().toString(36).slice(2)}`;
    const next: Toast = { id, duration: 2800, ...toast };
    setToasts((prev) => [...prev, next]);

    timeouts.current[id] = setTimeout(() => {
      setToasts((prev) => prev.filter((item) => item.id !== id));
    }, next.duration);
  }, []);

  useEffect(() => {
    if (!portal) return;
    portal.upsert({
      key: 'toast-stack',
      priority: 9999,
      node: (
        <View pointerEvents="box-none" style={styles.toastRoot}>
          {toasts.map((toast) => (
            <ToastCard key={toast.id} toast={toast} />
          ))}
        </View>
      ),
    });

    return () => {
      portal.remove('toast-stack');
    };
  }, [portal, toasts]);

  const value = useMemo(() => ({ show }), [show]);

  return <ToastContext.Provider value={value}>{children}</ToastContext.Provider>;
}

function ToastCard({ toast }: { toast: Toast }) {
  const slide = useRef(new Animated.Value(16)).current;
  const opacity = useRef(new Animated.Value(0)).current;
  const style = TYPE_STYLES[toast.type];

  useEffect(() => {
    Animated.parallel([
      Animated.timing(slide, { toValue: 0, duration: 200, useNativeDriver: true }),
      Animated.timing(opacity, { toValue: 1, duration: 200, useNativeDriver: true }),
    ]).start();
  }, [opacity, slide]);

  return (
    <Animated.View
      style={[
        styles.toastCard,
        { backgroundColor: style.bg, transform: [{ translateY: slide }], opacity },
      ]}
    >
      <View style={styles.toastIcon}>
        <Feather name={style.icon} size={18} color={style.text} />
      </View>
      <View style={styles.toastBody}>
        {toast.title ? <Text style={[styles.toastTitle, { color: style.text }]}>{toast.title}</Text> : null}
        <Text style={[styles.toastMessage, { color: style.text }]}>{toast.message}</Text>
      </View>
    </Animated.View>
  );
}

export function useToast() {
  const ctx = useContext(ToastContext);
  if (!ctx) throw new Error('useToast must be used within ToastProvider');
  return ctx;
}

const styles = StyleSheet.create({
  toastRoot: {
    position: 'absolute',
    left: theme.moderateScale(16),
    right: theme.moderateScale(16),
    bottom: theme.moderateScale(24),
    gap: theme.moderateScale(10),
  },
  toastCard: {
    borderRadius: theme.radius.lg,
    paddingVertical: theme.moderateScale(12),
    paddingHorizontal: theme.moderateScale(14),
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.moderateScale(10),
    ...theme.shadows.md,
  },
  toastIcon: {
    width: theme.moderateScale(24),
    height: theme.moderateScale(24),
    alignItems: 'center',
    justifyContent: 'center',
  },
  toastBody: {
    flex: 1,
  },
  toastTitle: {
    fontSize: theme.moderateScale(12),
    fontWeight: '700',
    marginBottom: theme.moderateScale(2),
  },
  toastMessage: {
    fontSize: theme.moderateScale(12),
  },
});
