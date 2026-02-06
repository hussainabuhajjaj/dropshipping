import React, { createContext, useContext, useEffect, useMemo, useReducer } from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { loadAuthToken, setAuthToken } from '@/src/api/authToken';
import { clearExpoPushToken } from '@/src/lib/pushTokens';

export type AuthUser = {
  name: string;
  email?: string;
  avatar?: string | null;
  phone?: string | null;
};

type AuthState = {
  status: 'guest' | 'authed' | 'loading';
  user: AuthUser | null;
  token: string | null;
};

type AuthAction =
  | { type: 'login'; user: AuthUser; token?: string | null }
  | { type: 'logout' }
  | { type: 'update'; user: Partial<AuthUser> };

const AuthContext = createContext<{
  status: AuthState['status'];
  user: AuthUser | null;
  token: string | null;
  login: (user: AuthUser, token?: string | null) => void;
  logout: () => Promise<void>;
  updateUser: (user: Partial<AuthUser>) => void;
} | null>(null);

const AUTH_USER_KEY = 'auth.user';

const reducer = (state: AuthState, action: AuthAction): AuthState => {
  switch (action.type) {
    case 'login':
      return { status: 'authed', user: action.user, token: action.token ?? null };
    case 'logout':
      return { status: 'guest', user: null, token: null };
    case 'update':
      return { ...state, user: state.user ? { ...state.user, ...action.user } : null };
    default:
      return state;
  }
};

export const AuthProvider = ({ children }: { children: React.ReactNode }) => {
  const [state, dispatch] = useReducer(reducer, { status: 'loading', user: null, token: null });

  useEffect(() => {
    const hydrate = async () => {
      const token = await loadAuthToken();
      const rawUser = await AsyncStorage.getItem(AUTH_USER_KEY).catch(() => null);
      const user = rawUser ? (JSON.parse(rawUser) as AuthUser) : null;

      if (token) {
        dispatch({ type: 'login', user: user ?? { name: 'Customer' }, token });
      } else {
        dispatch({ type: 'logout' });
      }
    };

    hydrate();
  }, []);

  const value = useMemo(() => {
    return {
      status: state.status,
      user: state.user,
      token: state.token,
      login: (user: AuthUser, token?: string | null) => {
        setAuthToken(token ?? null);
        AsyncStorage.setItem(AUTH_USER_KEY, JSON.stringify(user)).catch(() => {});
        dispatch({ type: 'login', user, token });
      },
      logout: async () => {
        await clearExpoPushToken();
        setAuthToken(null);
        AsyncStorage.removeItem(AUTH_USER_KEY).catch(() => {});
        dispatch({ type: 'logout' });
      },
      updateUser: (user: Partial<AuthUser>) => {
        const nextUser = state.user ? { ...state.user, ...user } : null;
        if (nextUser) {
          AsyncStorage.setItem(AUTH_USER_KEY, JSON.stringify(nextUser)).catch(() => {});
        }
        dispatch({ type: 'update', user });
      },
    };
  }, [state.status, state.user, state.token]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};

export const useAuth = () => {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
};

// Optional variant for screens that can render without providers (e.g., previews).
export const useAuthOptional = () => useContext(AuthContext);
