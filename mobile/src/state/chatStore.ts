import { create } from 'zustand';
import { nanoid } from 'nanoid/non-secure';

export type ChatMessage = {
  id: string;
  from: 'user' | 'agent' | 'system';
  text: string;
  ts: number;
};

type ChatState = {
  status: 'idle' | 'connecting' | 'connected';
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
  setConnected: (agentType: 'ai' | 'human') => void;
  setSessionId: (id: string | null) => void;
  addMessage: (m: Omit<ChatMessage, 'id' | 'ts'>) => void;
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
  setConnected(agentType) {
    set({ status: 'connected', agentType });
  },
  setSessionId(id) {
    set({ sessionId: id });
  },
  addMessage(payload) {
    const msg: ChatMessage = { id: nanoid(), ts: Date.now(), ...payload } as ChatMessage;
    set((s: any) => ({ messages: [...s.messages, msg] }));
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
      agentType: null,
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
