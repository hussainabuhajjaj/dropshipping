import { type ServerChatMessage, useChatStore } from '@/src/state/chatStore';
import { backendApiBaseUrl } from '@/src/api/config';
import { type ApiError } from '@/src/api/http';
import { apiFetch, apiPost, apiPostForm } from '@/src/api/http';
import { connectToSupportRealtime, disconnectSupportRealtime } from '@/src/services/chatRealtime';

type ChatApiPayload = {
  session_id?: string;
  status?: string;
  agent_type?: 'ai' | 'human';
  welcome?: string;
  reply?: string;
  ack?: string;
  messages?: ServerChatMessage[];
  next_after_id?: number;
};

export type ChatAttachmentInput = {
  uri: string;
  name: string;
  type: string;
  caption?: string;
};

function unwrapPayload(response: any): ChatApiPayload {
  if (response && typeof response === 'object' && 'data' in response && response.data && typeof response.data === 'object') {
    return response.data as ChatApiPayload;
  }

  return (response ?? {}) as ChatApiPayload;
}

function chatErrorMessage(error: unknown): string {
  const apiError = error as ApiError | undefined;
  if (apiError?.status === 401) {
    return 'Please sign in to use support chat.';
  }

  if (typeof apiError?.message === 'string' && apiError.message.trim() !== '') {
    return apiError.message;
  }

  return 'Unable to contact support right now. Please try again.';
}

async function backendPost(path: string, body: unknown) {
  const base = backendApiBaseUrl.replace(/\/$/, '');
  const url = `${base}/${path.replace(/^\//, '')}`;
  return apiPost<any>(url, body);
}

async function backendPostForm(path: string, body: FormData) {
  const base = backendApiBaseUrl.replace(/\/$/, '');
  const url = `${base}/${path.replace(/^\//, '')}`;
  return apiPostForm<any>(url, body);
}

async function backendGet(path: string, params: Record<string, string | number | undefined>) {
  const base = backendApiBaseUrl.replace(/\/$/, '');
  const url = new URL(`${base}/${path.replace(/^\//, '')}`);
  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return;
    url.searchParams.set(key, String(value));
  });

  return apiFetch<any>(url.toString());
}

function lastServerMessageId(messages: Array<{ id: string }>): number | undefined {
  let maxId = 0;
  for (const message of messages) {
    if (!message.id.startsWith('srv-')) continue;
    const parsed = Number(message.id.replace('srv-', ''));
    if (Number.isFinite(parsed) && parsed > maxId) {
      maxId = parsed;
    }
  }

  return maxId > 0 ? maxId : undefined;
}

export async function startChat(agent: 'ai' | 'human' | 'auto' = 'auto') {
  const store = useChatStore.getState();
  store.startConnecting(agent);

  try {
    const raw = await backendPost('/mobile/v1/chat/start', { agent });
    const resp = unwrapPayload(raw);
    const { session_id, agent_type } = resp || {};
    if (session_id) store.setSessionId(session_id);
    if (session_id) {
      await connectToSupportRealtime(session_id, (message) => {
        useChatStore.getState().mergeServerMessages([message]);
      });
    }
    const chosen = agent_type || (agent === 'auto' ? 'ai' : agent);
    store.setConnected(chosen as 'ai' | 'human');
    const messages = Array.isArray(resp?.messages) ? (resp.messages as ServerChatMessage[]) : [];
    if (messages.length) {
      store.mergeServerMessages(messages);
    } else {
      store.addMessage({
        from: 'agent',
        text:
          resp?.welcome ||
          (chosen === 'ai'
            ? 'Hi, I am your assistant. How can I help?'
            : 'Hi, I will connect you with a human agent shortly.'),
      });
    }
    return chosen;
  } catch (e) {
    disconnectSupportRealtime();
    store.setIdle();
    store.addMessage({ from: 'agent', text: chatErrorMessage(e) });
    return null;
  }
}

export async function sendMessage(text: string) {
  const store = useChatStore.getState();

  if (!store.agentType) {
    await startChat('auto');
  }

  const { agentType, sessionId } = useChatStore.getState();
  if (!agentType || !sessionId) return;

  if (agentType === 'ai') {
    try {
      const raw = await backendPost('/mobile/v1/chat/respond', { session_id: sessionId, input: text });
      const resp = unwrapPayload(raw);
      const nextAgent = resp?.agent_type as 'ai' | 'human' | undefined;
      if (nextAgent) {
        store.setConnected(nextAgent);
      }

      const messages = Array.isArray(resp?.messages) ? (resp.messages as ServerChatMessage[]) : [];
      if (messages.length) {
        store.mergeServerMessages(messages);
      } else {
        const reply = resp?.reply || 'Support will follow up shortly.';
        store.addMessage({ from: 'agent', text: String(reply) });
      }
    } catch (e) {
      store.addMessage({ from: 'agent', text: 'AI service error: please try again later.' });
    }
    return;
  }

  try {
    const raw = await backendPost('/mobile/v1/chat/forward', { session_id: sessionId, message: text });
    const resp = unwrapPayload(raw);
    const messages = Array.isArray(resp?.messages) ? (resp.messages as ServerChatMessage[]) : [];
    if (messages.length) {
      store.mergeServerMessages(messages);
    } else {
      store.addMessage({ from: 'agent', text: String(resp?.ack ?? 'Message sent to support.') });
    }
  } catch (e) {
    store.addMessage({ from: 'agent', text: 'Unable to forward to human agent right now.' });
  }
}

export async function pollMessages() {
  const store = useChatStore.getState();
  const sessionId = store.sessionId;
  if (!sessionId) return;

  const afterId = lastServerMessageId(store.messages);

  try {
    const raw = await backendGet('/mobile/v1/chat/messages', {
      session_id: sessionId,
      after_id: afterId,
      limit: 50,
    });
    const resp = unwrapPayload(raw);
    const messages = Array.isArray(resp?.messages) ? (resp.messages as ServerChatMessage[]) : [];
    if (messages.length) {
      store.mergeServerMessages(messages);
    }

    const nextAgent = resp?.agent_type as 'ai' | 'human' | undefined;
    if (nextAgent) {
      store.setConnected(nextAgent);
    }
  } catch {
    // silent polling failure
  }
}

export async function connectRealtime(): Promise<boolean> {
  const { sessionId } = useChatStore.getState();
  if (!sessionId) {
    return false;
  }

  return connectToSupportRealtime(sessionId, (message) => {
    useChatStore.getState().mergeServerMessages([message]);
  });
}

export function disconnectRealtime(): void {
  disconnectSupportRealtime();
}

export async function sendAttachment(input: ChatAttachmentInput) {
  const store = useChatStore.getState();

  if (!store.agentType) {
    await startChat('human');
  }

  const { sessionId } = useChatStore.getState();
  if (!sessionId) return;

  const formData = new FormData();
  formData.append('session_id', sessionId);
  if (input.caption && input.caption.trim() !== '') {
    formData.append('message', input.caption.trim());
  }
  formData.append('file', {
    uri: input.uri,
    name: input.name,
    type: input.type,
  } as any);

  try {
    const raw = await backendPostForm('/mobile/v1/chat/attachment', formData);
    const resp = unwrapPayload(raw);
    const messages = Array.isArray(resp?.messages) ? (resp.messages as ServerChatMessage[]) : [];
    if (messages.length) {
      store.mergeServerMessages(messages);
    }
    store.setConnected('human');
  } catch (e) {
    store.addMessage({ from: 'agent', text: chatErrorMessage(e) });
  }
}
