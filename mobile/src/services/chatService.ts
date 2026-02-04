import { useChatStore } from '@/src/state/chatStore';
import { backendApiBaseUrl } from '@/src/api/config';
import { apiPost } from '@/src/api/http';

async function backendPost(path: string, body: unknown) {
  const base = backendApiBaseUrl.replace(/\/$/, '');
  const url = `${base}/${path.replace(/^\//, '')}`;
  return apiPost<any>(url, body);
}

export async function startChat(agent: 'ai' | 'human' | 'auto' = 'auto') {
  const store = useChatStore.getState();
  store.startConnecting(agent);

  // Call backend to start a chat session; backend manages Deepseek keys and routing.
  try {
    const resp = await backendPost('/api/chat/start', { agent: agent });
    const { session_id, agent_type } = resp || {};
    if (session_id) store.setSessionId(session_id);
    const chosen = agent_type || (agent === 'auto' ? 'ai' : agent);
    store.setConnected(chosen as 'ai' | 'human');
    store.addMessage({ from: 'agent', text: resp?.welcome || (chosen === 'ai' ? 'Hi — I am your assistant. How can I help?' : 'Hi — I will connect you with a human agent shortly.') });
    return chosen;
  } catch (e) {
    // fallback to local behaviour if backend fails
    const chosen = agent === 'auto' ? 'human' : agent;
    store.setConnected(chosen as 'ai' | 'human');
    store.addMessage({ from: 'agent', text: 'Unable to contact backend — please try again later.' });
    return chosen;
  }
}

export async function sendMessage(text: string) {
  const store = useChatStore.getState();

  if (!store.agentType) {
    await startChat('auto');
  }

  useChatStore.getState().addMessage({ from: 'user', text });

  const { agentType, sessionId } = useChatStore.getState();
  if (!agentType) return;

  if (agentType === 'ai') {
    try {
      const resp = await backendPost('/api/chat/respond', { session_id: sessionId, input: text });
      const reply = resp?.reply || resp?.output || 'Sorry, no reply.';
      store.addMessage({ from: 'agent', text: String(reply) });
    } catch (e) {
      store.addMessage({ from: 'agent', text: 'AI service error: please try again later.' });
    }
    return;
  }

  // human agent: forward to backend or acknowledge
  try {
    await backendPost('/api/chat/forward', { session_id: sessionId, message: text });
    store.addMessage({ from: 'agent', text: 'Human agent: message forwarded to support.' });
  } catch (e) {
    store.addMessage({ from: 'agent', text: 'Unable to forward to human agent right now.' });
  }
}
