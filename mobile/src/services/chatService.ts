import { type ServerChatMessage, useChatStore } from '@/src/state/chatStore';
import { backendApiBaseUrl } from '@/src/api/config';
import { apiFetch, apiPost } from '@/src/api/http';

async function backendPost(path: string, body: unknown) {
  const base = backendApiBaseUrl.replace(/\/$/, '');
  const url = `${base}/${path.replace(/^\//, '')}`;
  return apiPost<any>(url, body);
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
    const resp = await backendPost('/mobile/v1/chat/start', { agent });
    const { session_id, agent_type } = resp || {};
    if (session_id) store.setSessionId(session_id);
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
    const chosen = agent === 'auto' ? 'human' : agent;
    store.setConnected(chosen as 'ai' | 'human');
    store.addMessage({ from: 'agent', text: 'Unable to contact support right now. Please try again.' });
    return chosen;
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
      const resp = await backendPost('/mobile/v1/chat/respond', { session_id: sessionId, input: text });
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
    const resp = await backendPost('/mobile/v1/chat/forward', { session_id: sessionId, message: text });
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
    const resp = await backendGet('/mobile/v1/chat/messages', {
      session_id: sessionId,
      after_id: afterId,
      limit: 50,
    });
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
