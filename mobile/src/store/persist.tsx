type PersistConfig<T> = {
  key: string;
  version?: number;
  migrate?: (data: any, version?: number) => T | null;
};

type StorageLike = {
  getItem: (key: string) => Promise<string | null>;
  setItem: (key: string, value: string) => Promise<void>;
};

const memoryStore: Record<string, string> = {};

const getStorage = (): StorageLike => {
  try {
    // eslint-disable-next-line @typescript-eslint/no-var-requires
    const asyncStorageModule = require('@react-native-async-storage/async-storage');
    const asyncStorage = asyncStorageModule?.default ?? asyncStorageModule;
    if (
      asyncStorage &&
      typeof asyncStorage.getItem === 'function' &&
      typeof asyncStorage.setItem === 'function'
    ) {
      return asyncStorage as StorageLike;
    }
  } catch {
    // fallback below
  }

  const maybeLocalStorage = (globalThis as unknown as { localStorage?: Storage }).localStorage;
  if (
    maybeLocalStorage &&
    typeof maybeLocalStorage.getItem === 'function' &&
    typeof maybeLocalStorage.setItem === 'function'
  ) {
    return {
      getItem: async (key) => maybeLocalStorage.getItem(key),
      setItem: async (key, value) => {
        maybeLocalStorage.setItem(key, value);
      },
    };
  }

  return {
    getItem: async (key) => (key in memoryStore ? memoryStore[key] : null),
    setItem: async (key, value) => {
      memoryStore[key] = value;
    },
  };
};

export async function loadPersisted<T>(config: PersistConfig<T>, fallback: T): Promise<T> {
  try {
    const storage = getStorage();
    const raw = await storage.getItem(config.key);
    if (!raw) return fallback;
    const parsed = JSON.parse(raw);
    if (config.migrate) {
      const migrated = config.migrate(parsed.data, parsed.version);
      return migrated ?? fallback;
    }
    return parsed.data ?? fallback;
  } catch {
    return fallback;
  }
}

export async function savePersisted<T>(config: PersistConfig<T>, data: T) {
  try {
    const payload = { data, version: config.version ?? 1 };
    const storage = getStorage();
    await storage.setItem(config.key, JSON.stringify(payload));
  } catch {
    // ignore persistence errors
  }
}
