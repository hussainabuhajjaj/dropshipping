import { backendApiBaseUrl } from './config';
import { apiDelete } from './http';

export async function requestAccountDeletion() {
  // Backend authenticates via bearer token and deletes the account + all tokens
  return apiDelete<{ ok?: boolean }>(`${backendApiBaseUrl}/mobile/v1/account/delete`);
}

