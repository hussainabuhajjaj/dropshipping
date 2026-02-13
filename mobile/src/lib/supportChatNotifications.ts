import { router } from 'expo-router';
import { Platform } from 'react-native';
import { useChatStore } from '@/src/state/chatStore';

type NotificationData = Record<string, unknown>;

const loadNotificationsModule = () => {
  try {
    // eslint-disable-next-line @typescript-eslint/no-var-requires
    return require('expo-notifications') as typeof import('expo-notifications');
  } catch {
    return null;
  }
};

const normalizeData = (value: unknown): NotificationData => {
  if (!value || typeof value !== 'object' || Array.isArray(value)) {
    return {};
  }

  return value as NotificationData;
};

const openSupportChatFromData = (rawData: unknown): void => {
  const data = normalizeData(rawData);
  const type = String(data.type ?? '').trim();
  if (!['support_reply', 'support_conversation_alert'].includes(type)) {
    return;
  }

  const conversationUuid = String(data.conversation_uuid ?? data.session_id ?? '').trim();
  if (conversationUuid !== '') {
    useChatStore.getState().setSessionId(conversationUuid);
  }

  router.push('/chat');
};

export const registerSupportChatNotificationHandlers = () => {
  if (Platform.OS === 'web') {
    return () => {};
  }

  const Notifications = loadNotificationsModule();
  if (!Notifications) {
    return () => {};
  }

  let isMounted = true;

  const responseSubscription = Notifications.addNotificationResponseReceivedListener((response) => {
    openSupportChatFromData(response?.notification?.request?.content?.data);
  });

  Notifications.getLastNotificationResponseAsync()
    .then((response) => {
      if (!isMounted || !response) {
        return;
      }

      openSupportChatFromData(response?.notification?.request?.content?.data);
    })
    .catch(() => {});

  return () => {
    isMounted = false;
    responseSubscription.remove();
  };
};

