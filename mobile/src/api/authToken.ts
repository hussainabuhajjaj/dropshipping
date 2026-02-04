import AsyncStorage from '@react-native-async-storage/async-storage';

const AUTH_TOKEN_KEY = 'auth.token';

let authToken: string | null = null;

export function setAuthToken(token: string | null) {
  authToken = token;
  if (token) {
    AsyncStorage.setItem(AUTH_TOKEN_KEY, token).catch(() => {});
  } else {
    AsyncStorage.removeItem(AUTH_TOKEN_KEY).catch(() => {});
  }
}

export function getAuthToken() {
  return authToken;
}

export async function loadAuthToken() {
  try {
    authToken = (await AsyncStorage.getItem(AUTH_TOKEN_KEY)) ?? null;
  } catch {
    authToken = null;
  }
  return authToken;
}
