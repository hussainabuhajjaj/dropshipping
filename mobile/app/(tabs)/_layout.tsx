import React from 'react';
import FontAwesome from '@expo/vector-icons/FontAwesome';
import { Tabs } from 'expo-router';

import { useClientOnlyValue } from '@/components/useClientOnlyValue';
import { useCart } from '@/lib/cartStore';
import { theme } from '@/src/theme';
import { useTranslations } from '@/src/i18n/TranslationsProvider';
// You can explore the built-in icon families and icons on the web at https://icons.expo.fyi/
function TabBarIcon(props: {
  name: React.ComponentProps<typeof FontAwesome>['name'];
  color: string;
}) {
  return <FontAwesome size={28} style={{ marginBottom: -3 }} {...props} />;
}

export default function TabLayout() {
  const { items } = useCart();
  const cartCount = items.reduce((sum, item) => sum + item.quantity, 0);
  const { t } = useTranslations();

  return (
    <Tabs
      screenOptions={{
        tabBarActiveTintColor: theme.colors.primary,
        tabBarInactiveTintColor: theme.colors.mutedLight,
        headerShown: useClientOnlyValue(false, true),
        tabBarStyle: {
          borderTopColor: theme.colors.border,
          backgroundColor: theme.colors.white,
        },
      }}>
      <Tabs.Screen
        name="home"
        options={{
          title: t('Home', 'Home'),
          tabBarIcon: ({ color }) => <TabBarIcon name="home" color={color} />,
          headerShown: false,
        }}
      />
      <Tabs.Screen
        name="categories"
        options={{
          title: t('Categories', 'Categories'),
          tabBarIcon: ({ color }) => <TabBarIcon name="th-large" color={color} />,
          headerShown: false,
        }}
      />
      <Tabs.Screen
        name="search"
        options={{
          title: t('Search', 'Search'),
          tabBarIcon: ({ color }) => <TabBarIcon name="search" color={color} />,
        }}
      />
      <Tabs.Screen
        name="cart"
        options={{
          title: t('Cart', 'Cart'),
          tabBarIcon: ({ color }) => <TabBarIcon name="shopping-bag" color={color} />,
          tabBarBadge: cartCount ? cartCount : undefined,
        }}
      />
      <Tabs.Screen
        name="account"
        options={{
          title: t('Account', 'Account'),
          tabBarIcon: ({ color }) => <TabBarIcon name="user" color={color} />,
        }}
      />
    </Tabs>
  );
}
