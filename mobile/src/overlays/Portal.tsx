import { ReactNode, useContext, useEffect, useRef } from 'react';
import { PortalContext } from './PortalHost';

type PortalProps = {
  children: ReactNode;
  priority?: number;
};

export function Portal({ children, priority = 1000 }: PortalProps) {
  const context = useContext(PortalContext);
  const keyRef = useRef(`portal-${Math.random().toString(36).slice(2)}`);

  useEffect(() => {
    if (!context) {
      return;
    }
    context.upsert({ key: keyRef.current, node: children, priority });
    return () => {
      context.remove(keyRef.current);
    };
  }, [children, context, priority]);

  return null;
}
