export type NotificationItem = {
  id: string;
  type?: string | null;
  title?: string | null;
  body?: string | null;
  actionUrl?: string | null;
  actionLabel?: string | null;
  readAt?: string | null;
  createdAt?: string | null;
};

export type NotificationMeta = {
  currentPage: number;
  lastPage: number;
  perPage: number;
  total: number;
  unreadCount: number;
};
