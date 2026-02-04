import { apiPost } from './http';
import { mobileApiBaseUrl } from './config';
import type { ApiEnvelope } from './catalog';

type NewsletterResponse = {
  subscriber_id?: string | number | null;
  email?: string | null;
  message?: string | null;
};

export const subscribeNewsletter = async (payload: {
  email: string;
  source?: string;
}): Promise<NewsletterResponse> => {
  const response = await apiPost<ApiEnvelope<NewsletterResponse>>(
    `${mobileApiBaseUrl}/newsletter/subscribe`,
    payload
  );
  if (response && response.success && response.data) {
    return response.data;
  }
  throw new Error(response?.message ?? 'Unable to subscribe.');
};
