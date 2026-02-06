export type AnnouncementItem = {
  id: string;
  locale?: string | null;
  title: string;
  body: string;
  image?: string | null;
  actionHref?: string | null;
  createdAt?: string | null;
};

export type AnnouncementMeta = {
  currentPage: number;
  lastPage: number;
  perPage: number;
  total: number;
};

