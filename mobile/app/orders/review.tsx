import { Feather } from '@expo/vector-icons';
import { router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Pressable, ScrollView, StyleSheet, Text, TextInput, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
import { createProductReview } from '@/src/api/reviews';
import { useToast } from '@/src/overlays/ToastProvider';
import { KeyboardAvoidingView, Platform } from 'react-native';
export default function ReviewScreen() {
  const params = useLocalSearchParams();
  const slug = typeof params.slug === 'string' ? params.slug : '';
  const orderItemId = typeof params.order_item_id === 'string' ? Number(params.order_item_id) : null;
  const { show } = useToast();
  const [status, setStatus] = useState<'idle' | 'done'>('idle');
  const [rating, setRating] = useState(0);
  const [body, setBody] = useState('');
  const [title, setTitle] = useState('');
  const [submitting, setSubmitting] = useState(false);

  if (status === 'done') {
    return (
      <SafeAreaView style={styles.doneContainer}>
        <View style={styles.doneCard}>
          <View style={styles.doneIconWrap}>
            <Feather name="check" size={28} color={theme.colors.inkDark} />
          </View>
          <Text style={styles.doneTitle}>Review submitted</Text>
          <Text style={styles.doneBody}>Thanks for sharing your feedback!</Text>
          <Pressable style={styles.donePrimaryButton} onPress={() => router.replace('/orders')}>
            <Text style={styles.donePrimaryText}>Back to activity</Text>
          </Pressable>
          <Pressable style={styles.doneSecondaryButton} onPress={() => router.push('/feedback/rate')}>
            <Text style={styles.doneSecondaryText}>Rate our service</Text>
          </Pressable>
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.container}>
      <KeyboardAvoidingView
        style={styles.keyboard}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        keyboardVerticalOffset={Platform.OS === 'ios' ? theme.moderateScale(20) : 0}
      >
        <ScrollView
          style={styles.scroll}
          contentContainerStyle={styles.content}
          showsVerticalScrollIndicator={false}
          keyboardShouldPersistTaps="handled"
          keyboardDismissMode="interactive"
          automaticallyAdjustKeyboardInsets
        >
          <View style={styles.headerRow}>
            <Pressable style={styles.iconButton} onPress={() => router.back()}>
              <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
            </Pressable>
            <Text style={styles.title}>Review</Text>
            <Pressable style={styles.iconButton} onPress={() => router.push('/(tabs)/home')}>
              <Feather name="x" size={16} color={theme.colors.inkDark} />
            </Pressable>
          </View>

          {!slug || !orderItemId ? (
            <View style={styles.noticeCard}>
              <Text style={styles.noticeTitle}>Select an order item to review</Text>
              <Text style={styles.noticeBody}>
                Please open this screen from an order item so we can attach your review.
              </Text>
            </View>
          ) : null}

          <Text style={styles.subtitle}>How was your experience?</Text>
          <View style={styles.starsRow}>
            {[1, 2, 3, 4, 5].map((item) => (
              <Pressable key={`star-${item}`} onPress={() => setRating(item)}>
                <Feather
                  name="star"
                  size={24}
                  color={item <= rating ? theme.colors.sun : theme.colors.inkDark}
                />
              </Pressable>
            ))}
          </View>

          <TextInput
            style={styles.input}
            placeholder="Title (optional)"
            placeholderTextColor="#c7c7c7"
            value={title}
            onChangeText={setTitle}
          />

          <TextInput
            style={styles.input}
            placeholder="Write your review"
            placeholderTextColor="#c7c7c7"
            multiline
            numberOfLines={5}
            textAlignVertical="top"
            value={body}
            onChangeText={setBody}
          />

          <Pressable
            style={styles.primaryButton}
            onPress={async () => {
              if (!slug || !orderItemId || Number.isNaN(orderItemId)) {
                show({ type: 'error', message: 'Missing order item for this review.' });
                return;
              }
              if (rating < 1) {
                show({ type: 'error', message: 'Please select a rating.' });
                return;
              }
              if (body.trim().length < 2) {
                show({ type: 'error', message: 'Please write a short review.' });
                return;
              }
              try {
                setSubmitting(true);
                await createProductReview(slug, {
                  order_item_id: orderItemId,
                  rating,
                  title: title.trim() || undefined,
                  body: body.trim(),
                });
                setStatus('done');
              } catch (err: any) {
                show({ type: 'error', message: err?.message ?? 'Unable to submit review.' });
              } finally {
                setSubmitting(false);
              }
            }}
            disabled={submitting}
          >
            <Text style={styles.primaryText}>{submitting ? 'Submitting...' : 'Submit'}</Text>
          </Pressable>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.white,
  },
  keyboard: {
    flex: 1,
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
    marginBottom: 16,
  },
  title: {
    fontSize: 20,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  iconButton: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: theme.colors.sand,
    alignItems: 'center',
    justifyContent: 'center',
  },
  subtitle: {
    fontSize: 14,
    color: theme.colors.inkDark,
  },
  starsRow: {
    marginTop: 12,
    flexDirection: 'row',
    gap: 8,
  },
  input: {
    marginTop: 16,
    minHeight: 120,
    borderRadius: 18,
    backgroundColor: theme.colors.sand,
    paddingHorizontal: 16,
    paddingVertical: 12,
    fontSize: 14,
    color: theme.colors.inkDark,
  },
  noticeCard: {
    marginBottom: 14,
    padding: 14,
    borderRadius: 16,
    backgroundColor: theme.colors.sand,
  },
  noticeTitle: {
    fontSize: 13,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  noticeBody: {
    marginTop: 6,
    fontSize: 12,
    color: theme.colors.mutedDark,
  },
  primaryButton: {
    marginTop: 24,
    backgroundColor: theme.colors.sun,
    borderRadius: 24,
    paddingVertical: 14,
    alignItems: 'center',
  },
  primaryText: {
    fontSize: 14,
    color: theme.colors.gray200,
    fontWeight: '700',
  },
  doneContainer: {
    flex: 1,
    backgroundColor: theme.colors.white,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 24,
  },
  doneCard: {
    width: '100%',
    borderRadius: 24,
    backgroundColor: theme.colors.sand,
    paddingVertical: 32,
    paddingHorizontal: 24,
    alignItems: 'center',
  },
  doneIconWrap: {
    width: 54,
    height: 54,
    borderRadius: 27,
    backgroundColor: theme.colors.sand,
    alignItems: 'center',
    justifyContent: 'center',
  },
  doneTitle: {
    marginTop: 16,
    fontSize: 16,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
  doneBody: {
    marginTop: 8,
    fontSize: 13,
    color: theme.colors.inkDark,
    textAlign: 'center',
  },
  donePrimaryButton: {
    marginTop: 18,
    backgroundColor: theme.colors.sun,
    paddingVertical: 12,
    paddingHorizontal: 24,
    borderRadius: 24,
  },
  donePrimaryText: {
    fontSize: 14,
    color: theme.colors.gray200,
    fontWeight: '700',
  },
  doneSecondaryButton: {
    marginTop: 10,
    backgroundColor: theme.colors.sand,
    paddingVertical: 12,
    paddingHorizontal: 24,
    borderRadius: 24,
  },
  doneSecondaryText: {
    fontSize: 13,
    fontWeight: '700',
    color: theme.colors.inkDark,
  },
});
