export type Address = {
  id: string;
  name: string | null;
  phone: string | null;
  line1: string;
  line2: string | null;
  city: string | null;
  state: string | null;
  postalCode: string | null;
  country: string | null;
  type: string | null;
  isDefault: boolean;
  createdAt?: string | null;
  updatedAt?: string | null;
};
