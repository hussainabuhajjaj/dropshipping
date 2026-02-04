import { useCallback, useRef, useState } from 'react';

type RefreshHandler = () => Promise<void> | void;

export const usePullToRefresh = (handler: RefreshHandler) => {
  const [refreshing, setRefreshing] = useState(false);
  const handlerRef = useRef<RefreshHandler>(handler);
  handlerRef.current = handler;

  const onRefresh = useCallback(async () => {
    if (refreshing) return;
    setRefreshing(true);
    try {
      await handlerRef.current();
    } finally {
      setRefreshing(false);
    }
  }, [refreshing]);

  return { refreshing, onRefresh, setRefreshing };
};
