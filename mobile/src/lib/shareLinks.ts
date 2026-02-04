import * as Linking from 'expo-linking';
import { publicSiteUrl } from '@/src/api/config';

export const getProductShareUrl = (slug: string) => {
  const safeSlug = slug.trim();
  if (!safeSlug) return Linking.createURL('/');
  if (publicSiteUrl) {
    return `${publicSiteUrl}/products/${encodeURIComponent(safeSlug)}`;
  }
  return Linking.createURL(`/products/${safeSlug}`);
};
