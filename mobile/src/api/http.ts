import { getAuthToken } from './authToken';

export type ApiError = {
  status: number;
  message: string;
  bodyText?: string;
  errors?: Record<string, string[]>;
  url?: string;
};

export async function apiFetch<T>(url: string, init?: RequestInit): Promise<T> {
  const token = getAuthToken();
  const headers: Record<string, string> = {
    Accept: 'application/json',
    ...(init?.headers as Record<string, string> | undefined),
  };

  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  let response: Response;
  try {
    response = await fetch(url, {
      ...init,
      headers,
      credentials: init?.credentials ?? 'include',
    });
  } catch (err: any) {
    const error: ApiError = {
      status: 0,
      message: err?.message ?? 'Network request failed',
      url,
    };
    throw error;
  }

  if (!response.ok) {
    const bodyText = await response.text().catch(() => '');
    let message = bodyText || `Request failed: ${response.status}`;
    let errors: Record<string, string[]> | undefined;

    try {
      const parsed = JSON.parse(bodyText || '{}');
      if (typeof parsed?.message === 'string') {
        message = parsed.message;
      }
      if (parsed?.errors && typeof parsed.errors === 'object') {
        errors = parsed.errors as Record<string, string[]>;
      }
    } catch {
      // ignore JSON parse errors
    }

    const error: ApiError = {
      status: response.status,
      message,
      bodyText,
      errors,
      url,
    };
    throw error;
  }

  return response.json() as Promise<T>;
}

export async function apiPost<T>(url: string, body: unknown, init?: RequestInit): Promise<T> {
  return apiFetch<T>(url, {
    method: 'POST',
    ...init,
    headers: {
      'Content-Type': 'application/json',
      ...(init?.headers as Record<string, string> | undefined),
    },
    body: JSON.stringify(body),
  });
}

export async function apiPatch<T>(url: string, body: unknown, init?: RequestInit): Promise<T> {
  return apiFetch<T>(url, {
    method: 'PATCH',
    ...init,
    headers: {
      'Content-Type': 'application/json',
      ...(init?.headers as Record<string, string> | undefined),
    },
    body: JSON.stringify(body),
  });
}
