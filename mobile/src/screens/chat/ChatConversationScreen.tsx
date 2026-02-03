import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useEffect, useMemo, useRef, useState } from 'react';
import { ActivityIndicator, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from '@/src/utils/responsiveStyleSheet';
import { theme } from '@/src/theme';
import { useChatStore } from '@/src/state/chatStore';
import * as chatSvc from '@/src/services/chatService';

const ISSUE_OPTIONS = [
  'Track my order',
  'Payment problem',
  'Return or refund',
  'Account access',
];

export default function ChatConversationScreen() {
  const { messages, status, agentType } = useChatStore();
  const [input, setInput] = useState('');
  const [selectedIssue, setSelectedIssue] = useState('');
  const scrollRef = useRef<ScrollView | null>(null);

  useEffect(() => {
    if (status !== 'idle') return;
    chatSvc.startChat('auto').catch(() => {});
  }, [status]);

  useEffect(() => {
    scrollRef.current?.scrollToEnd?.({ animated: true });
  }, [messages.length]);

  const statusLabel = useMemo(() => {
    if (status === 'connecting') return 'Connecting...';
    if (agentType === 'human') return 'Support agent';
    if (agentType === 'ai') return 'AI assistant';
    return 'Support';
  }, [status, agentType]);

  const handleSend = () => {
    const value = input.trim();
    if (!value) return;
    setInput('');
    setSelectedIssue('');
    chatSvc.sendMessage(value).catch(() => {});
  };

  const handleIssueSelect = (issue: string) => {
    setSelectedIssue(issue);
    setInput('');
    chatSvc.sendMessage(issue).finally(() => {
      setSelectedIssue('');
    });
  };

  return (
    <View style={styles.container}>
      <ScrollView
        ref={scrollRef}
        style={styles.scroll}
        contentContainerStyle={styles.content}
        showsVerticalScrollIndicator={false}
      >
        <View style={styles.headerRow}>
          <Pressable style={styles.iconButton} onPress={() => router.back()}>
            <Feather name="chevron-left" size={18} color={theme.colors.inkDark} />
          </Pressable>
          <View>
            <Text style={styles.title}>Live Chat</Text>
            <Text style={styles.status}>{statusLabel}</Text>
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

        <View style={styles.messageGroup}>
          {messages.map((message) => (
            <View
              key={message.id}
              style={[
                styles.messageBubble,
                message.from === 'agent' ? styles.agentBubble : styles.userBubble,
              ]}
            >
              <Text style={message.from === 'agent' ? styles.agentText : styles.userText}>
                {message.text}
              </Text>
            </View>
          ))}
        </View>
      </ScrollView>

      <View style={styles.inputRow}>
        <TextInput
          value={input}
          onChangeText={setInput}
          style={styles.input}
          placeholder="Type a message"
          placeholderTextColor="#b6b6b6"
        />
        <Pressable style={styles.sendButton} onPress={handleSend}>
          <Feather name="send" size={14} color={theme.colors.inkDark} />
        </Pressable>
      </View>
    </View>
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
  messageGroup: {
    gap: 12,
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
  sendButton: {
    marginLeft: 10,
    width: 34,
    height: 34,
    borderRadius: 17,
    backgroundColor: theme.colors.sun,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
