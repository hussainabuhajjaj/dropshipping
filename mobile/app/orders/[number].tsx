import { Feather } from '@expo/vector-icons';
import { router, useLocalSearchParams } from 'expo-router';
import { useEffect, useState } from 'react';
import { Image, Pressable, ScrollView, StyleSheet, Text, View } from '@/src/utils/responsiveStyleSheet';
import { SafeAreaView } from 'react-native-safe-area-context';
import { fetchOrderDetail } from '@/src/api/orders';
import type { Order } from '@/src/types/orders';
import { useOrders } from '@/lib/ordersStore';
import { usePreferences } from '@/src/store/preferencesStore';
import { formatCurrency } from '@/src/lib/formatCurrency';
import { theme } from '@/src/theme';
import { Skeleton } from '@/src/components/ui/Skeleton';

const statusBadge = (key: string): { label: string; bg: string; fg: string } => {
    switch (key) {
        case 'received':         return { label: 'Received',         bg: theme.colors.blueSoft,    fg: '#3a5bd9' };
        case 'processing':       return { label: 'Processing',       bg: theme.colors.primarySoft, fg: theme.colors.primaryDark };
        case 'dispatched':       return { label: 'Dispatched',       bg: '#e6f7ee',                fg: theme.colors.green };
        case 'in_transit':       return { label: 'In Transit',       bg: '#e6f7ee',                fg: theme.colors.green };
        case 'out_for_delivery': return { label: 'Out for Delivery', bg: '#e6f7ee',                fg: theme.colors.green };
        case 'delivered':        return { label: 'Delivered',        bg: '#e6f7ee',                fg: theme.colors.green };
        case 'issue_detected':   return { label: 'Issue Detected',   bg: theme.colors.dangerSoft,  fg: theme.colors.danger };
        case 'refunded':         return { label: 'Refunded',         bg: theme.colors.dangerSoft,  fg: theme.colors.danger };
        default:                 return { label: 'Processing',       bg: theme.colors.primarySoft, fg: theme.colors.primaryDark };
    }
};

const STATUS_STEPS = ['received', 'processing', 'dispatched', 'in_transit', 'out_for_delivery', 'delivered'];

function StatusTimeline({ statusKey }: { statusKey: string }) {
    const currentIndex = STATUS_STEPS.indexOf(statusKey);
    const isTerminal = statusKey === 'issue_detected' || statusKey === 'refunded';

    if (isTerminal) return null;

    return (
        <View style={timelineStyles.container}>
            {STATUS_STEPS.map((step, index) => {
                const done = index <= currentIndex;
                const active = index === currentIndex;
                const labels: Record<string, string> = {
                    received: 'Received',
                    processing: 'Processing',
                    dispatched: 'Dispatched',
                    in_transit: 'In Transit',
                    out_for_delivery: 'Out for Delivery',
                    delivered: 'Delivered',
                };
                return (
                    <View key={step} style={timelineStyles.step}>
                        <View style={timelineStyles.dotCol}>
                            <View style={[
                                timelineStyles.dot,
                                done ? timelineStyles.dotDone : timelineStyles.dotPending,
                                active ? timelineStyles.dotActive : null,
                            ]}>
                                {done && !active && (
                                    <Feather name="check" size={9} color={theme.colors.white} />
                                )}
                            </View>
                            {index < STATUS_STEPS.length - 1 && (
                                <View style={[timelineStyles.line, done && index < currentIndex ? timelineStyles.lineDone : null]} />
                            )}
                        </View>
                        <Text style={[timelineStyles.label, active ? timelineStyles.labelActive : done ? timelineStyles.labelDone : null]}>
                            {labels[step]}
                        </Text>
                    </View>
                );
            })}
        </View>
    );
}

const timelineStyles = StyleSheet.create({
    container: { paddingVertical: 4 },
    step: { flexDirection: 'row', alignItems: 'flex-start', minHeight: 36 },
    dotCol: { alignItems: 'center', width: 24, marginRight: 12 },
    dot: {
        width: 20,
        height: 20,
        borderRadius: 10,
        alignItems: 'center',
        justifyContent: 'center',
    },
    dotDone: { backgroundColor: theme.colors.green },
    dotPending: { backgroundColor: theme.colors.gray200, borderWidth: 1.5, borderColor: theme.colors.border },
    dotActive: { backgroundColor: theme.colors.primary },
    line: { width: 2, flex: 1, backgroundColor: theme.colors.border, minHeight: 16 },
    lineDone: { backgroundColor: theme.colors.green },
    label: { fontSize: 12, color: theme.colors.muted, paddingTop: 2, flex: 1 },
    labelDone: { color: theme.colors.inkDark },
    labelActive: { fontWeight: '700', color: theme.colors.inkDark },
});

export default function OrderDetailScreen() {
    const params = useLocalSearchParams();
    const number = typeof params.number === 'string' ? params.number : '';
    const { getOrderByNumber, updateOrder } = useOrders();
    const { state } = usePreferences();
    const [order, setOrder] = useState<Order | null>(() => getOrderByNumber(number) ?? null);
    const [loading, setLoading] = useState(() => !getOrderByNumber(number));
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        let active = true;
        if (!number) { setLoading(false); setError('Order not found.'); return; }

        const local = getOrderByNumber(number);
        if (local) {
            setOrder(local);
            if (local.items.length > 0) {
                setError(null); setLoading(false);
                return () => { active = false; };
            }
        }

        setLoading(true); setError(null);
        fetchOrderDetail(number)
            .then((payload) => { if (!active) return; setOrder(payload); updateOrder(number, payload); })
            .catch((err: any) => { if (!active) return; setError(err?.message ?? 'Unable to load order.'); })
            .finally(() => { if (active) setLoading(false); });

        return () => { active = false; };
    }, [number, getOrderByNumber, updateOrder]);

    const HeaderBar = ({ title }: { title: string }) => (
        <View style={styles.headerRow}>
            <Pressable style={styles.iconButton} onPress={() => router.back()}>
                <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
            </Pressable>
            <Text style={styles.title}>{title}</Text>
            <Pressable style={styles.iconButton} onPress={() => router.push('/(tabs)/home')}>
                <Feather name="x" size={16} color={theme.colors.inkDark} />
            </Pressable>
        </View>
    );

    if (loading) {
        return (
            <SafeAreaView style={styles.safeArea}>
                <View style={styles.padded}>
                    <HeaderBar title="Order" />
                    <View style={styles.skeletonStack}>
                        <View style={styles.card}>
                            <Skeleton width="35%" height={11} />
                            <Skeleton width="55%" height={18} style={styles.skGap} />
                            <Skeleton width="70%" height={11} style={styles.skGap} />
                        </View>
                        <View style={styles.card}>
                            <Skeleton width="25%" height={11} />
                            {[0, 1, 2].map((i) => (
                                <View key={i} style={[styles.skRow, { marginTop: 14 }]}>
                                    <Skeleton width={20} height={20} radius={10} />
                                    <Skeleton width="60%" height={11} style={{ marginLeft: 12 }} />
                                </View>
                            ))}
                        </View>
                        <View style={styles.card}>
                            <Skeleton width="20%" height={11} />
                            {[0, 1].map((i) => (
                                <View key={i} style={[styles.skRow, { marginTop: 14 }]}>
                                    <Skeleton width={48} height={48} radius={12} />
                                    <View style={{ flex: 1, marginLeft: 12 }}>
                                        <Skeleton width="75%" height={11} />
                                        <Skeleton width="35%" height={10} style={styles.skGap} />
                                    </View>
                                    <Skeleton width={44} height={11} />
                                </View>
                            ))}
                        </View>
                    </View>
                </View>
            </SafeAreaView>
        );
    }

    if (!order) {
        return (
            <SafeAreaView style={styles.safeArea}>
                <View style={styles.padded}>
                    <HeaderBar title="Order" />
                    <View style={styles.errorCard}>
                        <Feather name="alert-circle" size={28} color={theme.colors.danger} />
                        <Text style={styles.errorTitle}>Order not found</Text>
                        <Text style={styles.errorBody}>{error ?? 'We could not locate that order.'}</Text>
                    </View>
                </View>
            </SafeAreaView>
        );
    }

    const badge = statusBadge(order.statusKey ?? 'processing');

    return (
        <SafeAreaView style={styles.safeArea}>
            <ScrollView style={styles.safeArea} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
                <View style={styles.padded}>
                    <HeaderBar title={`Order #${number}`} />

                    {/* Status hero card */}
                    <View style={[styles.card, styles.statusCard]}>
                        <View style={styles.statusTop}>
                            <View>
                                <Text style={styles.cardLabel}>Order status</Text>
                                <Text style={styles.statusValue}>{order.status}</Text>
                            </View>
                            <View style={[styles.badge, { backgroundColor: badge.bg }]}>
                                <Text style={[styles.badgeText, { color: badge.fg }]}>{badge.label}</Text>
                            </View>
                        </View>
                        {order.statusExplanation ? (
                            <Text style={styles.statusExplanation}>{order.statusExplanation}</Text>
                        ) : null}
                        <Text style={styles.placedAt}>Placed {order.placedAt ?? '—'}</Text>
                    </View>

                    {/* Progress timeline */}
                    <View style={styles.card}>
                        <Text style={styles.cardLabel}>Shipment progress</Text>
                        <View style={styles.timelineWrap}>
                            <StatusTimeline statusKey={order.statusKey ?? 'processing'} />
                        </View>
                        {order.tracking.length > 0 && (
                            <Pressable
                                style={styles.trackLink}
                                onPress={() => router.push(`/orders/track?number=${encodeURIComponent(order.number)}`)}
                            >
                                <Feather name="map-pin" size={12} color={theme.colors.primary} />
                                <Text style={styles.trackLinkText}>View full tracking</Text>
                                <Feather name="chevron-right" size={12} color={theme.colors.primary} />
                            </Pressable>
                        )}
                    </View>

                    {/* Items */}
                    {order.items.length > 0 && (
                        <View style={styles.card}>
                            <Text style={styles.cardLabel}>Items ({order.items.length})</Text>
                            <View style={styles.itemList}>
                                {order.items.map((item) => (
                                    <View key={item.id} style={styles.itemRow}>
                                        {item.image ? (
                                            <Image source={{ uri: item.image }} style={styles.itemImage} />
                                        ) : (
                                            <View style={styles.itemImageFallback}>
                                                <Text style={styles.itemImageInitial}>{item.name.slice(0, 1).toUpperCase()}</Text>
                                            </View>
                                        )}
                                        <View style={styles.itemInfo}>
                                            <Text style={styles.itemName} numberOfLines={2}>{item.name}</Text>
                                            <Text style={styles.itemMeta}>Qty {item.quantity}</Text>
                                        </View>
                                        <Text style={styles.itemPrice}>
                                            {formatCurrency(item.price * item.quantity, state.currency, state.currency)}
                                        </Text>
                                    </View>
                                ))}
                            </View>
                        </View>
                    )}

                    {/* Order total summary */}
                    <View style={[styles.card, styles.totalCard]}>
                        <View style={styles.totalRow}>
                            <Text style={styles.totalLabel}>Order total</Text>
                            <Text style={styles.totalValue}>
                                {formatCurrency(order.total, state.currency, state.currency)}
                            </Text>
                        </View>
                    </View>
                </View>
            </ScrollView>
        </SafeAreaView>
    );
}

const styles = StyleSheet.create({
    safeArea: { flex: 1, backgroundColor: theme.colors.white },
    padded: { paddingHorizontal: 20, paddingTop: 12 },
    content: { paddingBottom: 40 },
    headerRow: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
        marginBottom: 16,
    },
    iconButton: {
        width: 36, height: 36, borderRadius: 18,
        backgroundColor: theme.colors.sand,
        alignItems: 'center', justifyContent: 'center',
    },
    title: { fontSize: 17, fontWeight: '700', color: theme.colors.inkDark },
    card: {
        backgroundColor: theme.colors.white,
        borderRadius: 20,
        borderWidth: 1,
        borderColor: theme.colors.sand,
        padding: 16,
        marginBottom: 12,
    },
    cardLabel: { fontSize: 11, color: theme.colors.muted, fontWeight: '600', textTransform: 'uppercase', letterSpacing: 0.5 },
    statusCard: { backgroundColor: theme.colors.sand },
    statusTop: { flexDirection: 'row', alignItems: 'flex-start', justifyContent: 'space-between', marginBottom: 8 },
    statusValue: { fontSize: 18, fontWeight: '700', color: theme.colors.inkDark, marginTop: 4 },
    badge: { paddingHorizontal: 10, paddingVertical: 4, borderRadius: 20, alignSelf: 'flex-start' },
    badgeText: { fontSize: 11, fontWeight: '700' },
    statusExplanation: { fontSize: 12, color: theme.colors.mutedDark, lineHeight: 18, marginBottom: 8 },
    placedAt: { fontSize: 11, color: theme.colors.muted },
    timelineWrap: { marginTop: 14 },
    trackLink: {
        marginTop: 14,
        flexDirection: 'row',
        alignItems: 'center',
        gap: 4,
        alignSelf: 'flex-start',
        paddingHorizontal: 12,
        paddingVertical: 7,
        borderRadius: 20,
        backgroundColor: theme.colors.sun,
    },
    trackLinkText: { fontSize: 12, fontWeight: '600', color: theme.colors.inkDark },
    itemList: { marginTop: 12, gap: 14 },
    itemRow: { flexDirection: 'row', alignItems: 'center', gap: 12 },
    itemImage: { width: 48, height: 48, borderRadius: 12 },
    itemImageFallback: {
        width: 48, height: 48, borderRadius: 12,
        backgroundColor: theme.colors.sand,
        alignItems: 'center', justifyContent: 'center',
    },
    itemImageInitial: { fontSize: 16, fontWeight: '700', color: theme.colors.inkDark },
    itemInfo: { flex: 1 },
    itemName: { fontSize: 12, fontWeight: '600', color: theme.colors.inkDark },
    itemMeta: { fontSize: 11, color: theme.colors.muted, marginTop: 2 },
    itemPrice: { fontSize: 12, fontWeight: '700', color: theme.colors.inkDark },
    totalCard: { backgroundColor: theme.colors.sand },
    totalRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
    totalLabel: { fontSize: 14, fontWeight: '600', color: theme.colors.inkDark },
    totalValue: { fontSize: 16, fontWeight: '700', color: theme.colors.inkDark },
    skeletonStack: { gap: 12 },
    skGap: { marginTop: 8 },
    skRow: { flexDirection: 'row', alignItems: 'center' },
    errorCard: { alignItems: 'center', paddingVertical: 40, paddingHorizontal: 16, borderRadius: 20, borderWidth: 1, borderColor: theme.colors.sand, gap: 8 },
    errorTitle: { fontSize: 15, fontWeight: '700', color: theme.colors.inkDark },
    errorBody: { fontSize: 12, color: theme.colors.mutedDark, textAlign: 'center' },
});
