import { backendApiBaseUrl } from './config';
import { apiPost } from './http';

export async function requestAccountDeletion() {
  // Backend should authenticate (cookie or bearer) and schedule/perform deletion.
  return apiPost<{ ok?: boolean }>(`${backendApiBaseUrl}/account/delete`, {});
}

