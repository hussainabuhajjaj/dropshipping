import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
import { useOrders } from '@/lib/ordersStore';
import { Skeleton } from '@/src/components/ui/Skeleton';
import { SafeAreaView } from 'react-native-safe-area-context';
import { usePreferences } from '@/src/store/preferencesStore';
import { formatCurrency } from '@/src/lib/formatCurrency';
import type { Order } from '@/src/types/orders';

const statusBadge = (key: string): { label: string; bg: string; fg: string } => {
    switch (key) {
        case 'received':         return { label: 'Received',         bg: theme.colors.blueSoft,    fg: '#3a5bd9' };
        case 'processing':       return { label: 'Processing',       bg: theme.colors.primarySoft, fg: theme.colors.primaryDark };
        case 'dispatched':       return { label: 'Dispatched',       bg: '#e6f7ee',                fg: theme.colors.green };
        case 'in_transit':       return { label: 'In Transit',       bg: '#e6f7ee',                fg: theme.colors.green };
        case 'out_for_delivery': return { label: 'Out for Delivery', bg: '#e6f7ee',                fg: theme.colors.green };
        case 'delivered':        return { label: 'Delivered',        bg: '#e6f7ee',                fg: theme.colors.green };
        case 'issue_detected':   return { label: 'Issue',            bg: theme.colors.dangerSoft,  fg: theme.colors.danger };
        case 'refunded':         return { label: 'Refunded',         bg: theme.colors.dangerSoft,  fg: theme.colors.danger };
        default:                 return { label: 'Processing',       bg: theme.colors.primarySoft, fg: theme.colors.primaryDark };
    }
};

export default function HistoryScreen() {
    const { orders, loading, error, refreshOrders } = useOrders();
    const { state } = usePreferences();

    return (
        <SafeAreaView style={styles.container}>
            <ScrollView style={styles.scroll} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
                <View style={styles.headerRow}>
                    <Pressable style={styles.iconButton} onPress={() => router.back()}>
                        <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
                    </Pressable>
                    <Text style={styles.title}>Order History</Text>
                    <Pressable style={styles.iconButton} onPress={refreshOrders}>
                        <Feather name="refresh-cw" size={15} color={theme.colors.inkDark} />
                    </Pressable>
                </View>

                {!loading && orders.length > 0 && (
                    <Text style={styles.countLabel}>{orders.length} order{orders.length !== 1 ? 's' : ''}</Text>
                )}

                <View style={styles.list}>
                    {loading
                        ? [0, 1, 2, 3].map((i) => (
                            <View key={`sk-${i}`} style={styles.card}>
                                <View style={styles.cardSkeleton}>
                                    <Skeleton width="45%" height={13} />
                                    <Skeleton width="30%" height={10} style={styles.skeletonGap} />
                                </View>
                                <View style={styles.cardRight}>
                                    <Skeleton width={72} height={22} radius={11} />
                                    <Skeleton width={48} height={11} style={styles.skeletonGap} />
                                </View>
                            </View>
                        ))
                        : orders.map((order: Order) => {
                            const badge = statusBadge(order.statusKey ?? 'processing');
                            return (
                                <Pressable
                                    key={order.number}
                                    style={styles.card}
                                    onPress={() => router.push(`/orders/${order.number}`)}
                                >
                                    <View style={styles.cardLeft}>
                                        <Text style={styles.cardNumber}>#{order.number}</Text>
                                        <Text style={styles.cardDate}>{order.placedAt ?? '—'}</Text>
                                    </View>
                                    <View style={styles.cardRight}>
                                        <View style={[styles.badge, { backgroundColor: badge.bg }]}>
                                            <Text style={[styles.badgeText, { color: badge.fg }]}>{badge.label}</Text>
                                        </View>
                                        <Text style={styles.cardTotal}>
                                            {formatCurrency(order.total, state.currency, state.currency)}
                                        </Text>
                                    </View>
                                    <Feather name="chevron-right" size={15} color={theme.colors.mutedLight} style={styles.chevron} />
                                </Pressable>
                            );
                        })}

                    {!loading && orders.length === 0 && (
                        <View style={styles.emptyCard}>
                            <Feather name="clock" size={32} color={theme.colors.mutedSoft} />
                            <Text style={styles.emptyTitle}>No orders yet</Text>
                            <Text style={styles.emptyBody}>{error ?? 'Your past orders will show up here.'}</Text>
                        </View>
                    )}
                </View>
            </ScrollView>
        </SafeAreaView>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: theme.colors.white },
    scroll: { flex: 1 },
    content: { paddingHorizontal: 20, paddingTop: 12, paddingBottom: 40 },
    headerRow: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
        marginBottom: 8,
    },
    title: { fontSize: 20, fontWeight: '700', color: theme.colors.inkDark },
    iconButton: {
        width: 36,
        height: 36,
        borderRadius: 18,
        backgroundColor: theme.colors.sand,
        alignItems: 'center',
        justifyContent: 'center',
    },
    countLabel: {
        fontSize: 12,
        color: theme.colors.muted,
        marginBottom: 14,
    },
    list: { gap: 10 },
    card: {
        paddingHorizontal: 14,
        paddingVertical: 12,
        borderRadius: 18,
        backgroundColor: theme.colors.white,
        borderWidth: 1,
        borderColor: theme.colors.sand,
        flexDirection: 'row',
        alignItems: 'center',
    },
    cardLeft: { flex: 1 },
    cardNumber: { fontSize: 13, fontWeight: '700', color: theme.colors.inkDark },
    cardDate: { marginTop: 3, fontSize: 11, color: theme.colors.muted },
    cardRight: { alignItems: 'flex-end', gap: 4 },
    badge: { paddingHorizontal: 8, paddingVertical: 3, borderRadius: 20 },
    badgeText: { fontSize: 10, fontWeight: '600' },
    cardTotal: { fontSize: 11, fontWeight: '600', color: theme.colors.mutedDark },
    chevron: { marginLeft: 8 },
    cardSkeleton: { flex: 1 },
    skeletonGap: { marginTop: 6 },
    emptyCard: { alignItems: 'center', paddingVertical: 40, paddingHorizontal: 16, borderRadius: 20, borderWidth: 1, borderColor: theme.colors.sand, gap: 8 },
    emptyTitle: { fontSize: 14, fontWeight: '700', color: theme.colors.inkDark },
    emptyBody: { fontSize: 12, color: theme.colors.mutedDark, textAlign: 'center' },
});
