import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useEffect, useMemo, useRef, useState } from 'react';
import { ActivityIndicator, FlatList, Image, Linking, Pressable, StyleSheet, Text, TextInput, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
import { type ChatMessage, useChatStore } from '@/src/state/chatStore';
import * as chatSvc from '@/src/services/chatService';
import { Keyboard, KeyboardAvoidingView, Platform } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useAuth } from '@/lib/authStore';
import * as ImagePicker from 'expo-image-picker';

const ISSUE_OPTIONS = [
  'Track my order',
  'Payment problem',
  'Return or refund',
  'Account access',
];

export default function ChatConversationScreen() {
  const { messages, status, agentType, sessionId, realtimeConnected, realtimeMode } = useChatStore();
  const { status: authStatus } = useAuth();
  const [input, setInput] = useState('');
  const [selectedIssue, setSelectedIssue] = useState('');
  const [keyboardHeight, setKeyboardHeight] = useState(0);
  const [uploadingAttachment, setUploadingAttachment] = useState(false);
  const listRef = useRef<FlatList<ChatMessage> | null>(null);
  const insets = useSafeAreaInsets();
  const inputBarHeight = theme.moderateScale(72);

  useEffect(() => {
    if (authStatus !== 'authed') return;
    if (status !== 'idle') return;
    chatSvc.startChat('auto').catch(() => {});
  }, [status, authStatus]);

  useEffect(() => {
    if (!sessionId) return;

    let cancelled = false;
    let timer: ReturnType<typeof setInterval> | null = null;

    const run = async () => {
      await chatSvc.pollMessages().catch(() => {});
      const realtimeConnected = await chatSvc.connectRealtime().catch(() => false);
      if (cancelled) return;

      timer = setInterval(() => {
        chatSvc.pollMessages().catch(() => {});
      }, realtimeConnected ? 15000 : 5000);
    };

    run().catch(() => {});

    return () => {
      cancelled = true;
      if (timer) {
        clearInterval(timer);
      }
      chatSvc.disconnectRealtime();
    };
  }, [sessionId]);

  useEffect(() => {
    listRef.current?.scrollToEnd?.({ animated: true });
  }, [messages.length]);

  useEffect(() => {
    const onShow = (event: any) => {
      if (Platform.OS !== 'android') return;
      const height = Math.max(0, (event?.endCoordinates?.height ?? 0) - insets.bottom);
      setKeyboardHeight(height);
    };
    const onHide = () => {
      if (Platform.OS !== 'android') return;
      setKeyboardHeight(0);
    };

    const showSub = Keyboard.addListener('keyboardDidShow', onShow);
    const hideSub = Keyboard.addListener('keyboardDidHide', onHide);

    return () => {
      showSub.remove();
      hideSub.remove();
    };
  }, [insets.bottom]);

  const statusLabel = useMemo(() => {
    if (authStatus === 'loading') return 'Preparing chat...';
    if (authStatus === 'guest') return 'Sign in required';
    if (status === 'connecting') return 'Connecting...';
    if (agentType === 'human') return 'Support agent';
    if (agentType === 'ai') return 'AI assistant';
    return 'Support';
  }, [status, agentType, authStatus]);

  const transportLabel = useMemo(() => {
    if (authStatus !== 'authed') return '';
    if (status === 'connecting') return 'Transport: connecting';
    if (realtimeConnected) return 'Transport: realtime';
    if (realtimeMode === 'polling') return 'Transport: polling';
    return 'Transport: offline';
  }, [authStatus, status, realtimeConnected, realtimeMode]);

  const handleSend = () => {
    if (authStatus !== 'authed' || uploadingAttachment) return;
    const value = input.trim();
    if (!value) return;
    setInput('');
    setSelectedIssue('');
    chatSvc.sendMessage(value).catch(() => {});
  };

  const handleIssueSelect = (issue: string) => {
    if (authStatus !== 'authed') return;
    setSelectedIssue(issue);
    setInput('');
    chatSvc.sendMessage(issue).finally(() => {
      setSelectedIssue('');
    });
  };

  const openAttachmentLink = async (url: string) => {
    try {
      await Linking.openURL(url);
    } catch {
      // noop
    }
  };

  const handleAttachment = async () => {
    if (authStatus !== 'authed' || uploadingAttachment) return;

    const permission = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!permission.granted) {
      return;
    }

    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ['images'],
      quality: 0.85,
      allowsEditing: false,
    });

    if (result.canceled || !result.assets?.length) {
      return;
    }

    const asset = result.assets[0];
    if (!asset?.uri) {
      return;
    }

    setUploadingAttachment(true);
    try {
      await chatSvc.sendAttachment({
        uri: asset.uri,
        name: asset.fileName ?? `support-image-${Date.now()}.jpg`,
        type: asset.mimeType ?? 'image/jpeg',
        caption: input.trim() || undefined,
      });
      if (input.trim() !== '') {
        setInput('');
      }
    } finally {
      setUploadingAttachment(false);
    }
  };

  return (
    <KeyboardAvoidingView
      style={styles.container}
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
      keyboardVerticalOffset={Platform.OS === 'ios' ? theme.moderateScale(12) : 0}
    >
      <FlatList
        ref={listRef}
        data={messages}
        keyExtractor={(message) => message.id}
        style={styles.scroll}
        contentContainerStyle={[
          styles.content,
          {
            paddingTop: theme.moderateScale(10) + insets.top,
            paddingBottom:
              inputBarHeight + insets.bottom + keyboardHeight + theme.moderateScale(12),
          },
        ]}
        showsVerticalScrollIndicator={false}
        keyboardShouldPersistTaps="handled"
        keyboardDismissMode="interactive"
        automaticallyAdjustKeyboardInsets
        ListHeaderComponent={
          <View>
            <View style={styles.headerRow}>
              <Pressable style={styles.iconButton} onPress={() => router.back()}>
                <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
              </Pressable>
              <View>
                <Text style={styles.title}>Live Chat</Text>
                <Text style={styles.status}>{statusLabel}</Text>
                {transportLabel ? <Text style={styles.status}>{transportLabel}</Text> : null}
              </View>
              <Pressable style={styles.iconButton} onPress={() => router.push('/support')}>
                <Feather name="x" size={16} color={theme.colors.inkDark} />
              </Pressable>
            </View>

            {status === 'connecting' && messages.length === 0 ? (
              <View style={styles.loadingCard}>
                <ActivityIndicator size="small" color={theme.colors.inkDark} />
                <Text style={styles.loadingText}>Connecting you with support...</Text>
              </View>
            ) : null}
            {authStatus === 'guest' ? (
              <View style={styles.loadingCard}>
                <Text style={styles.loadingText}>Please sign in to start support chat.</Text>
              </View>
            ) : null}

            <View style={styles.issueSection}>
              <Text style={styles.issueTitle}>Choose an issue</Text>
              <View style={styles.issueList}>
                {ISSUE_OPTIONS.map((issue) => {
                  const isActive = issue === selectedIssue;
                  return (
                    <Pressable
                      key={issue}
                      style={[styles.issueChip, isActive ? styles.issueChipActive : null]}
                      onPress={() => handleIssueSelect(issue)}
                    >
                      <Text style={[styles.issueText, isActive ? styles.issueTextActive : null]}>{issue}</Text>
                    </Pressable>
                  );
                })}
              </View>
            </View>
          </View>
        }
        ItemSeparatorComponent={() => <View style={styles.messageSpacer} />}
        renderItem={({ item: message }) => (
          <View
            style={[
              styles.messageBubble,
              message.from === 'agent' ? styles.agentBubble : styles.userBubble,
            ]}
          >
            {message.messageType === 'image' && typeof message.metadata?.attachment_url === 'string' ? (
              <Pressable onPress={() => openAttachmentLink(String(message.metadata?.attachment_url))}>
                <Image source={{ uri: String(message.metadata?.attachment_url) }} style={styles.attachmentImage} />
              </Pressable>
            ) : null}
            {message.messageType === 'file' && typeof message.metadata?.attachment_url === 'string' ? (
              <Pressable
                style={styles.fileRow}
                onPress={() => openAttachmentLink(String(message.metadata?.attachment_url))}
              >
                <Feather
                  name="paperclip"
                  size={14}
                  color={message.from === 'agent' ? theme.colors.inkDark : theme.colors.white}
                />
                <Text style={message.from === 'agent' ? styles.agentText : styles.userText}>
                  {String(message.metadata?.attachment_name ?? 'Open attachment')}
                </Text>
              </Pressable>
            ) : null}
            <Text style={message.from === 'agent' ? styles.agentText : styles.userText}>{message.text}</Text>
            {message.from === 'user' ? (
              <Text style={styles.userMeta}>
                {message.readAt ? 'Seen' : message.serverId ? 'Delivered' : 'Sending'}
              </Text>
            ) : null}
          </View>
        )}
      />

      <View
        style={[
          styles.inputRow,
          {
            minHeight: inputBarHeight,
            paddingBottom: theme.moderateScale(12) + insets.bottom,
            marginBottom: Platform.OS === 'android' ? keyboardHeight : 0,
          },
        ]}
      >
        <TextInput
          value={input}
          onChangeText={setInput}
          style={styles.input}
          placeholder="Type a message"
          placeholderTextColor="#b6b6b6"
          editable={authStatus === 'authed' && !uploadingAttachment}
        />
        <Pressable
          style={[styles.attachButton, authStatus !== 'authed' || uploadingAttachment ? styles.sendButtonDisabled : null]}
          onPress={handleAttachment}
        >
          {uploadingAttachment ? (
            <ActivityIndicator size="small" color={theme.colors.inkDark} />
          ) : (
            <Feather name="paperclip" size={14} color={theme.colors.inkDark} />
          )}
        </Pressable>
        <Pressable style={[styles.sendButton, authStatus !== 'authed' || uploadingAttachment ? styles.sendButtonDisabled : null]} onPress={handleSend}>
          <Feather name="send" size={14} color={theme.colors.inkDark} />
        </Pressable>
      </View>
    </KeyboardAvoidingView>
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
    paddingTop: 10,
    paddingBottom: 20,
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 20,
  },
  title: {
    fontSize: 16,
    fontWeight: '700',
    color: theme.colors.inkDark,
    textAlign: 'center',
  },
  status: {
    fontSize: 11,
    color: theme.colors.mutedDark,
    textAlign: 'center',
    marginTop: 2,
  },
  iconButton: {
    width: 34,
    height: 34,
    borderRadius: 17,
    backgroundColor: theme.colors.sand,
    alignItems: 'center',
    justifyContent: 'center',
  },
  loadingCard: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    padding: 14,
    borderRadius: 16,
    backgroundColor: theme.colors.sand,
    marginBottom: 18,
  },
  loadingText: {
    fontSize: 12,
    color: theme.colors.inkDark,
    fontWeight: '600',
  },
  issueSection: {
    marginBottom: 18,
  },
  issueTitle: {
    fontSize: 12,
    fontWeight: '600',
    color: theme.colors.mutedDark,
    marginBottom: 10,
  },
  issueList: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
  },
  issueChip: {
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 18,
    backgroundColor: theme.colors.sand,
  },
  issueChipActive: {
    backgroundColor: theme.colors.sun,
  },
  issueText: {
    fontSize: 12,
    fontWeight: '600',
    color: theme.colors.inkDark,
  },
  issueTextActive: {
    color: theme.colors.white,
  },
  messageSpacer: {
    height: 12,
  },
  messageBubble: {
    maxWidth: '78%',
    paddingHorizontal: 14,
    paddingVertical: 12,
    borderRadius: 18,
  },
  agentBubble: {
    alignSelf: 'flex-start',
    backgroundColor: theme.colors.sand,
    borderTopLeftRadius: 6,
  },
  userBubble: {
    alignSelf: 'flex-end',
    backgroundColor: theme.colors.sun,
    borderTopRightRadius: 6,
  },
  agentText: {
    fontSize: 13,
    color: theme.colors.inkDark,
  },
  userText: {
    fontSize: 13,
    color: theme.colors.white,
  },
  userMeta: {
    marginTop: 6,
    fontSize: 10,
    color: 'rgba(255,255,255,0.85)',
    textAlign: 'right',
    fontWeight: '600',
  },
  attachmentImage: {
    width: 150,
    height: 150,
    borderRadius: 12,
    marginBottom: 8,
    backgroundColor: '#f2f2f2',
  },
  fileRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: 8,
  },
  inputRow: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderTopWidth: 1,
    borderTopColor: theme.colors.sand,
    backgroundColor: theme.colors.white,
  },
  input: {
    flex: 1,
    fontSize: 13,
    color: theme.colors.inkDark,
    backgroundColor: theme.colors.sand,
    borderRadius: 20,
    paddingHorizontal: 14,
    paddingVertical: 10,
  },
  attachButton: {
    marginLeft: 10,
    width: 34,
    height: 34,
    borderRadius: 17,
    backgroundColor: theme.colors.sand,
    alignItems: 'center',
    justifyContent: 'center',
  },
  sendButton: {
    marginLeft: 10,
    width: 34,
    height: 34,
    borderRadius: 17,
    backgroundColor: theme.colors.sun,
    alignItems: 'center',
    justifyContent: 'center',
  },
  sendButtonDisabled: {
    opacity: 0.4,
  },
});
