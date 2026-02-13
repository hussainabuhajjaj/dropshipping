import { getAuthToken } from '@/src/api/authToken';
import { supportChatRealtime } from '@/src/api/config';
import type { ServerChatMessage } from '@/src/state/chatStore';
import { NativeModules } from 'react-native';

type PusherModule = {
  default?: any;
};

type RealtimeCallback = (message: ServerChatMessage) => void;

let pusher: any = null;
let channel: any = null;
let connectedConversationUuid: string | null = null;
let messageCallback: RealtimeCallback | null = null;

const isSubscribed = (value: any): boolean => Boolean(value?.subscribed);

const loadPusherModule = (): PusherModule | null => {
  const nativeModules = NativeModules as Record<string, unknown> | undefined;
  if (!nativeModules?.RNCNetInfo) {
    console.warn(
      'Support chat realtime disabled: @react-native-community/netinfo native module is missing. Rebuild the development client to enable realtime.'
    );

    return null;
  }

  try {
    // eslint-disable-next-line @typescript-eslint/no-var-requires
    return require('pusher-js/react-native') as PusherModule;
  } catch (error) {
    console.warn('Support chat realtime disabled: failed to load pusher react-native module.', error);

    return null;
  }
};

const normalizeMessage = (value: any): ServerChatMessage | null => {
  if (!value || typeof value !== 'object') {
    return null;
  }

  if (!Number.isFinite(Number(value.id))) {
    return null;
  }

  return {
    id: Number(value.id),
    sender_type: String(value.sender_type ?? 'system') as ServerChatMessage['sender_type'],
    body: String(value.body ?? ''),
    created_at: value.created_at ? String(value.created_at) : null,
    read_at: value.read_at ? String(value.read_at) : null,
    message_type: value.message_type ? String(value.message_type) : 'text',
    metadata: value.metadata && typeof value.metadata === 'object' ? value.metadata as Record<string, unknown> : null,
  };
};

export async function connectToSupportRealtime(
  conversationUuid: string,
  onMessage: RealtimeCallback
): Promise<boolean> {
  if (!supportChatRealtime.enabled || !supportChatRealtime.appKey) {
    return false;
  }

  const token = getAuthToken();
  if (!token) {
    return false;
  }

  const module = loadPusherModule();
  const Pusher = module?.default ?? (module as any);
  if (!Pusher) {
    return false;
  }

  if (connectedConversationUuid === conversationUuid && channel && isSubscribed(channel)) {
    messageCallback = onMessage;

    return true;
  }

  disconnectSupportRealtime();

  const pusherOptions: Record<string, unknown> = {
    cluster: supportChatRealtime.cluster || undefined,
    wsPort: supportChatRealtime.wsPort,
    wssPort: supportChatRealtime.wssPort,
    forceTLS: supportChatRealtime.forceTLS,
    enabledTransports: ['ws', 'wss'],
    authEndpoint: supportChatRealtime.authEndpoint,
    authTransport: 'ajax',
    auth: {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    },
  };

  if (supportChatRealtime.host) {
    pusherOptions.wsHost = supportChatRealtime.host;
  }

  pusher = new Pusher(supportChatRealtime.appKey, pusherOptions);

  messageCallback = onMessage;
  connectedConversationUuid = conversationUuid;
  channel = pusher.subscribe(`private-support.customer.${conversationUuid}`);
  channel.bind('support.message.created', (event: any) => {
    const message = normalizeMessage(event?.message);
    if (!message || !messageCallback) {
      return;
    }

    messageCallback(message);
  });

  return await new Promise<boolean>((resolve) => {
    let done = false;
    const finish = (value: boolean) => {
      if (done) return;
      done = true;
      if (!value) {
        disconnectSupportRealtime();
      }
      resolve(value);
    };

    channel.bind('pusher:subscription_error', (error: unknown) => {
      console.warn('Support chat realtime subscription error', error);
      finish(false);
    });
    channel.bind('pusher:subscription_succeeded', () => finish(true));
    pusher.connection.bind('error', (error: unknown) => {
      console.warn('Support chat realtime connection error', error);
    });
    setTimeout(() => finish(false), 4000);
  });
}

export function disconnectSupportRealtime(): void {
  if (channel && connectedConversationUuid && pusher) {
    pusher.unsubscribe(`private-support.customer.${connectedConversationUuid}`);
  }

  if (pusher) {
    pusher.disconnect();
  }

  channel = null;
  pusher = null;
  connectedConversationUuid = null;
  messageCallback = null;
}
