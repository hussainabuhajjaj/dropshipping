import { Stack, useLocalSearchParams } from 'expo-router';
import { useEffect, useState } from 'react';
import { GestureHandlerRootView } from 'react-native-gesture-handler';
import { StyleSheet } from 'react-native';
import { fetchProduct, fetchProducts } from '@/src/api/catalog';
import { useToast } from '@/src/overlays/ToastProvider';
import { ProductDetailScreen as ProductDetailContent } from '@/src/screens/products/ProductDetailScreen';
import type { Product } from '@/src/types/storefront';

export default function ProductDetailRoute() {
  const params = useLocalSearchParams();
  const slug = typeof params.slug === 'string' ? params.slug : '';
  const [product, setProduct] = useState<Product | null>(null);
  const [related, setRelated] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);
  const { show } = useToast();

  useEffect(() => {
    let active = true;
    if (!slug) {
      setLoading(false);
      return;
    }

    setLoading(true);
    setRelated([]);
    fetchProduct(slug)
      .then((result) => {
        if (active) {
          setProduct(result);
          const category = result.category ?? '';
          if (category) {
            fetchProducts({ category })
              .then(({ items }) => {
                if (!active) return;
                setRelated(items.filter((item) => item.id !== result.id).slice(0, 6));
              })
              .catch(() => {
                if (active) setRelated([]);
              });
          } else {
            fetchProducts()
              .then(({ items }) => {
                if (!active) return;
                setRelated(items.filter((item) => item.id !== result.id).slice(0, 6));
              })
              .catch(() => {
                if (active) setRelated([]);
              });
          }
        }
      })
      .catch((err: any) => {
        show({ type: 'error', message: err?.message ?? 'Unable to load product.' });
      })
      .finally(() => {
        if (active) {
          setLoading(false);
        }
      });

    return () => {
      active = false;
    };
  }, [slug, show]);

  return (
    <GestureHandlerRootView style={styles.root}>
      <Stack.Screen options={{ headerShown: false }} />
      <ProductDetailContent product={product} loading={loading} mode="full" related={related} slug={slug} />
    </GestureHandlerRootView>
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
  },
});
