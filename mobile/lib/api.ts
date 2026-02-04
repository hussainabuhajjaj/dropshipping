import { storefrontBaseUrl } from '@/src/api/config';
import { apiFetch as baseFetch } from '@/src/api/http';

export { storefrontBaseUrl };

export const apiFetch = async <T>(path: string, init?: RequestInit): Promise<T> => {
  return baseFetch<T>(`${storefrontBaseUrl}${path}`, init);
};
