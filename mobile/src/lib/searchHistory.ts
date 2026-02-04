import AsyncStorage from '@react-native-async-storage/async-storage';

const SEARCH_HISTORY_KEY = 'search.history';
const MAX_HISTORY = 8;

export const loadSearchHistory = async (): Promise<string[]> => {
  try {
    const raw = await AsyncStorage.getItem(SEARCH_HISTORY_KEY);
    if (!raw) return [];
    const parsed = JSON.parse(raw);
    if (!Array.isArray(parsed)) return [];
    return parsed.filter((item) => typeof item === 'string' && item.trim().length > 0);
  } catch {
    return [];
  }
};

export const saveSearchHistory = async (items: string[]): Promise<void> => {
  try {
    await AsyncStorage.setItem(SEARCH_HISTORY_KEY, JSON.stringify(items.slice(0, MAX_HISTORY)));
  } catch {
    // ignore storage errors
  }
};

export const addSearchHistory = async (term: string): Promise<string[]> => {
  const trimmed = term.trim();
  if (!trimmed) return loadSearchHistory();
  const items = await loadSearchHistory();
  const next = [trimmed, ...items.filter((item) => item.toLowerCase() !== trimmed.toLowerCase())];
  const limited = next.slice(0, MAX_HISTORY);
  await saveSearchHistory(limited);
  return limited;
};

export const clearSearchHistory = async (): Promise<void> => {
  try {
    await AsyncStorage.removeItem(SEARCH_HISTORY_KEY);
  } catch {
    // ignore storage errors
  }
};

