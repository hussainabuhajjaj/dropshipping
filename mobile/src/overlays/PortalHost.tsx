import { ReactNode, createContext, useCallback, useMemo, useState } from 'react';
import { StyleSheet, View } from 'react-native';

type PortalEntry = {
  key: string;
  node: ReactNode;
  priority: number;
};

type PortalContextValue = {
  upsert: (entry: PortalEntry) => void;
  remove: (key: string) => void;
};

export const PortalContext = createContext<PortalContextValue | null>(null);

export function PortalHost({ children }: { children: ReactNode }) {
  const [entries, setEntries] = useState<PortalEntry[]>([]);

  const upsert = useCallback((entry: PortalEntry) => {
    setEntries((prev) => {
      const next = prev.filter((item) => item.key !== entry.key);
      return [...next, entry];
    });
  }, []);

  const remove = useCallback((key: string) => {
    setEntries((prev) => prev.filter((item) => item.key !== key));
  }, []);

  const value = useMemo(() => ({ upsert, remove }), [remove, upsert]);

  const ordered = useMemo(
    () => [...entries].sort((a, b) => a.priority - b.priority),
    [entries],
  );

  return (
    <PortalContext.Provider value={value}>
      <View style={styles.root}>
        {children}
        {ordered.map((entry) => (
          <View
            key={entry.key}
            pointerEvents="box-none"
            style={[StyleSheet.absoluteFill, { zIndex: entry.priority, elevation: entry.priority }]}
          >
            {entry.node}
          </View>
        ))}
      </View>
    </PortalContext.Provider>
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
  },
});
