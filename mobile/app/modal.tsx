import { StatusBar } from 'expo-status-bar';
import { router, useLocalSearchParams } from 'expo-router';
import { useEffect, useState } from 'react';
import { Feather } from '@expo/vector-icons';
import { Pressable, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { SafeAreaView } from 'react-native-safe-area-context';
import { fetchProduct, fetchProducts } from '@/src/api/catalog';
import { ProductDetailScreen as ProductDetailContent } from '@/src/screens/products/ProductDetailScreen';
import { theme } from '@/src/theme';
import { useToast } from '@/src/overlays/ToastProvider';
import type { Product } from '@/src/types/storefront';

export default function ModalScreen() {
  const params = useLocalSearchParams();
  const slug = typeof params.slug === 'string' ? params.slug : '';
  const [product, setProduct] = useState<Product | null>(null);
  const [related, setRelated] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);
  const { show } = useToast();
  const handleClose = () => {
    if (router.canGoBack()) {
      router.back();
      return;
    }
    if (product?.slug) {
      router.replace(`/products/${product.slug}`);
      return;
    }
    router.replace('/(tabs)/home');
  };

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

  if (loading) {
    return (
      <SafeAreaView style={styles.container}>
        <ProductDetailContent product={null} loading mode="modal" onClose={handleClose} />
        <StatusBar style="auto" />
      </SafeAreaView>
    );
  }

  if (!product) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.emptyWrap}>
          <Text style={styles.emptyTitle}>Product not found</Text>
          <Pressable style={styles.closeButton} onPress={handleClose} accessibilityRole="button">
            <Feather name="x" size={16} color={theme.colors.inkDark} />
            <Text style={styles.closeText}>Close</Text>
          </Pressable>
        </View>
        <StatusBar style="auto" />
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.container}>
      <ProductDetailContent
        product={product}
        loading={false}
        mode="modal"
        onClose={handleClose}
        related={related}
      />
      <StatusBar style="auto" />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.white,
  },
  emptyWrap: {
    flex: 1,
    backgroundColor: theme.colors.white,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: theme.moderateScale(24),
    gap: theme.moderateScale(14),
  },
  emptyTitle: {
    fontSize: theme.moderateScale(16),
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  closeButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.moderateScale(8),
    backgroundColor: theme.colors.sand,
    borderRadius: theme.moderateScale(18),
    paddingHorizontal: theme.moderateScale(14),
    paddingVertical: theme.moderateScale(10),
  },
  closeText: {
    fontSize: theme.moderateScale(13),
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
});

