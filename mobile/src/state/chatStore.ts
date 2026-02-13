import { create } from 'zustand';
import { nanoid } from 'nanoid/non-secure';

export type ChatMessage = {
  id: string;
  from: 'user' | 'agent' | 'system';
  text: string;
  ts: number;
  serverId?: number | null;
  readAt?: number | null;
  messageType?: 'text' | 'image' | 'file';
  metadata?: Record<string, unknown> | null;
};

export type ServerChatMessage = {
  id: number;
  sender_type: 'customer' | 'agent' | 'ai' | 'system';
  body: string;
  created_at?: string | null;
  read_at?: string | null;
  message_type?: string | null;
  metadata?: Record<string, unknown> | null;
};

type ChatState = {
  status: 'idle' | 'connecting' | 'connected';
  realtimeConnected: boolean;
  realtimeMode: 'realtime' | 'polling';
  agentType?: 'ai' | 'human' | null;
  sessionId?: string | null;
  messages: ChatMessage[];
  prompt: string;
  topic: string;
  tag: string;
  draftMessage: string;
  orderNumber: string;
  hasImage: boolean;
  hasFile: boolean;
  startConnecting: (agent?: 'ai' | 'human' | 'auto') => void;
  setIdle: () => void;
  setConnected: (agentType: 'ai' | 'human') => void;
  setRealtimeStatus: (connected: boolean, mode?: 'realtime' | 'polling') => void;
  setSessionId: (id: string | null) => void;
  addMessage: (m: Omit<ChatMessage, 'id' | 'ts'>) => void;
  mergeServerMessages: (messages: ServerChatMessage[]) => void;
  setPrompt: (prompt: string) => void;
  setTopic: (topic: string) => void;
  setTag: (tag: string) => void;
  setDraftMessage: (message: string) => void;
  setOrderNumber: (number: string) => void;
  setHasImage: (value: boolean) => void;
  setHasFile: (value: boolean) => void;
  clear: () => void;
};

export const useChatStore = create<ChatState>((set, get) => ({
  status: 'idle',
  realtimeConnected: false,
  realtimeMode: 'polling',
  agentType: null,
  messages: [],
  prompt: '',
  topic: '',
  tag: '',
  draftMessage: '',
  orderNumber: '',
  hasImage: false,
  hasFile: false,
  startConnecting(agent = 'auto') {
    set({ status: 'connecting' });
    // actual connection is implemented in chatService
  },
  setIdle() {
    set({ status: 'idle', agentType: null, sessionId: null, realtimeConnected: false, realtimeMode: 'polling' });
  },
  setConnected(agentType) {
    set({ status: 'connected', agentType });
  },
  setRealtimeStatus(connected, mode = connected ? 'realtime' : 'polling') {
    set({ realtimeConnected: connected, realtimeMode: mode });
  },
  setSessionId(id) {
    set({ sessionId: id });
  },
  addMessage(payload) {
    const msg: ChatMessage = { id: nanoid(), ts: Date.now(), ...payload } as ChatMessage;
    set((s: any) => ({ messages: [...s.messages, msg] }));
  },
  mergeServerMessages(serverMessages) {
    const mapped = serverMessages.map((message) => {
      const from: ChatMessage['from'] =
        message.sender_type === 'customer'
          ? 'user'
          : message.sender_type === 'system'
            ? 'system'
            : 'agent';

      return {
        id: `srv-${message.id}`,
        from,
        text: String(message.body ?? ''),
        ts: message.created_at ? Date.parse(message.created_at) || Date.now() : Date.now(),
        serverId: Number.isFinite(Number(message.id)) ? Number(message.id) : null,
        readAt: message.read_at ? Date.parse(message.read_at) || Date.now() : null,
        messageType: message.message_type === 'image' ? 'image' : message.message_type === 'file' ? 'file' : 'text',
        metadata: message.metadata && typeof message.metadata === 'object' ? message.metadata : null,
      } as ChatMessage;
    });

    set((state) => {
      const nextById = new Map(state.messages.map((message) => [message.id, message]));

      for (const message of mapped) {
        const existing = nextById.get(message.id);
        nextById.set(message.id, existing ? { ...existing, ...message } : message);
      }

      const next = [...nextById.values()].sort((left, right) => left.ts - right.ts);

      return { messages: next };
    });
  },
  setPrompt(prompt) {
    set({ prompt });
  },
  setTopic(topic) {
    set({ topic });
  },
  setTag(tag) {
    set({ tag });
  },
  setDraftMessage(message) {
    set({ draftMessage: message });
  },
  setOrderNumber(number) {
    set({ orderNumber: number });
  },
  setHasImage(value) {
    set({ hasImage: value });
  },
  setHasFile(value) {
    set({ hasFile: value });
  },
  clear() {
    set({
      messages: [],
      status: 'idle',
      realtimeConnected: false,
      realtimeMode: 'polling',
      agentType: null,
      sessionId: null,
      prompt: '',
      topic: '',
      tag: '',
      draftMessage: '',
      orderNumber: '',
      hasImage: false,
      hasFile: false,
    });
  },
}));
