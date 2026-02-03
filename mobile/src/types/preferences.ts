export type Preferences = {
  country: string;
  currency: string;
  size: string;
  language: string;
  notifications: {
    push: boolean;
    email: boolean;
    sms: boolean;
  };
};

export type PreferencesLookups = {
  countries: string[];
  currencies: string[];
  sizes: string[];
  languages: string[];
};
