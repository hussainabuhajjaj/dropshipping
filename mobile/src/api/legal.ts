import { backendApiBaseUrl } from './config';
import { apiFetch } from './http';

export interface LegalPage {
  slug: string;
  title: string;
  content: string;
}

export interface LegalPageListItem {
  slug: string;
  title: string;
  has_content: boolean;
}

export interface LegalPagesResponse {
  success: boolean;
  data: {
    pages: LegalPageListItem[];
  };
}

export interface LegalPageResponse {
  success: boolean;
  data: LegalPage;
}

/**
 * Fetch a specific legal page by slug
 * @param slug - The page slug (privacy, terms, about, shipping, refund, customs)
 */
export async function fetchLegalPage(slug: string): Promise<LegalPage> {
  const response = await apiFetch<LegalPageResponse>(
    `${backendApiBaseUrl}/mobile/v1/legal/${slug}`
  );
  return response.data;
}

/**
 * Fetch list of all available legal pages
 */
export async function fetchLegalPages(): Promise<LegalPageListItem[]> {
  const response = await apiFetch<LegalPagesResponse>(
    `${backendApiBaseUrl}/mobile/v1/legal`
  );
  return response.data.pages;
}
