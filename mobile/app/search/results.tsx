import { useEffect } from 'react';
import { useLocalSearchParams } from 'expo-router';
import ProductsScreen from '@/src/screens/products/ProductsScreen';
import { addSearchHistory } from '@/src/lib/searchHistory';

export default function SearchResultsRoute() {
  const params = useLocalSearchParams();
  const query =
    typeof params.q === 'string' ? params.q : typeof params.query === 'string' ? params.query : '';

  useEffect(() => {
    const trimmed = query.trim();
    if (!trimmed) return;
    addSearchHistory(trimmed).catch(() => {});
  }, [query]);

  return <ProductsScreen filterRoute="/products/filters" />;
}

export const options = {
  headerShown: false,
};
