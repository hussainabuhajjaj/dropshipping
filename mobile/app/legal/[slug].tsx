import { Feather } from '@expo/vector-icons';
import { useLocalSearchParams, router } from 'expo-router';
import { Pressable, ScrollView, StyleSheet, Text, View, ActivityIndicator } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
import { SafeAreaView } from 'react-native-safe-area-context';
import { fetchLegalPage, type LegalPage } from '@/src/api/legal';
import { useState, useEffect } from 'react';

export default function LegalScreen() {
    const params = useLocalSearchParams();
    const slug = typeof params.slug === 'string' ? params.slug : 'legal';

    const [legalData, setLegalData] = useState<LegalPage | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        async function loadLegalContent() {
            try {
                setLoading(true);
                setError(null);
                const data = await fetchLegalPage(slug);
                setLegalData(data);
            } catch (err: any) {
                console.error('Failed to load legal content:', err);
                setError(err?.message || 'Failed to load content');
            } finally {
                setLoading(false);
            }
        }

        loadLegalContent();
    }, [slug]);

    const title = legalData?.title ?? slug.replace('-', ' ');
    const content = legalData?.content ?? '';

    return (
        <SafeAreaView style={styles.container}>
            <ScrollView style={styles.scroll} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
                <View style={styles.headerRow}>
                    <Pressable style={styles.iconButton} onPress={() => router.back()}>
                        <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
                    </Pressable>
                    <Text style={styles.title}>{title}</Text>
                    <Pressable style={styles.iconButton} onPress={() => router.push('/(tabs)/home')}>
                        <Feather name="x" size={16} color={theme.colors.inkDark} />
                    </Pressable>
                </View>

                {loading && (
                    <View style={styles.loadingContainer}>
                        <ActivityIndicator size="large" color={theme.colors.primary} />
                        <Text style={styles.loadingText}>Loading...</Text>
                    </View>
                )}

                {error && !loading && (
                    <View style={styles.errorContainer}>
                        <Feather name="alert-circle" size={48} color={theme.colors.danger} />
                        <Text style={styles.errorText}>{error}</Text>
                        <Pressable style={styles.retryButton} onPress={() => setLoading(true)}>
                            <Text style={styles.retryButtonText}>Retry</Text>
                        </Pressable>
                    </View>
                )}

                {!loading && !error && content && (
                    <Text style={styles.body}>{content.replace(/<[^>]*>/g, '\n')}</Text>
                )}

                {!loading && !error && !content && (
                    <Text style={styles.body}>This content is not yet available.</Text>
                )}
            </ScrollView>
        </SafeAreaView>
    );
}

const styles = StyleSheet.create({
    container: {
        flex: 1,
        backgroundColor: theme.colors.white,
    },
    scroll: {
        flex: 1,
    },
    content: {
        paddingHorizontal: 20,
        paddingTop: 12,
        paddingBottom: 32,
    },
    headerRow: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
        marginBottom: 12,
    },
    iconButton: {
        width: 36,
        height: 36,
        borderRadius: 18,
        backgroundColor: theme.colors.sand,
        alignItems: 'center',
        justifyContent: 'center',
    },
    title: {
        fontSize: 18,
        fontWeight: '700',
        color: theme.colors.inkDark,
        textTransform: 'capitalize',
    },
    body: {
        fontSize: 14,
        color: theme.colors.inkDark,
        marginTop: 16,
        lineHeight: 22,
        letterSpacing: 0.2,
    },
    loadingContainer: {
        flex: 1,
        alignItems: 'center',
        justifyContent: 'center',
        paddingVertical: 60,
    },
    loadingText: {
        fontSize: 14,
        color: theme.colors.muted,
        marginTop: 12,
    },
    errorContainer: {
        flex: 1,
        alignItems: 'center',
        justifyContent: 'center',
        paddingVertical: 60,
        paddingHorizontal: 40,
    },
    errorText: {
        fontSize: 14,
        color: theme.colors.mutedDark,
        textAlign: 'center',
        marginTop: 16,
        marginBottom: 20,
    },
    retryButton: {
        backgroundColor: theme.colors.primary,
        paddingHorizontal: 24,
        paddingVertical: 12,
        borderRadius: 8,
    },
    retryButtonText: {
        fontSize: 14,
        fontWeight: '600',
        color: theme.colors.white,
    },
});
