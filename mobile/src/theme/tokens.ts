import { moderateScale } from './responsive';

export const colors = {
  black: '#000000',
  white: '#ffffff',
  ink: '#1f1f1f',
  inkDark: '#202020',
  muted: '#7a7f8a',
  mutedDark: '#6b707a',
  mutedSoft: '#b6b6b6',
  mutedLight: '#a1a6b3',
  border: '#eef0f4',
  borderSoft: '#f3f4f7',
  gray100: '#f9f9f9',
  gray200: '#f3f3f3',
  gray250: '#f2f2f2',
  gray300: '#f1f1f1',
  primary: '#f5950f',
  primaryDark: '#d97f0d',
  primarySoft: '#fdf1d9',
  primarySoftAlt: '#fbe7c0',
  primarySoftLight: '#fff7e6',
  blueSoft: '#eef3ff',
  blueSoftAlt: '#e2e5ec',
  blueSoftMuted: '#d8def5',
  blueSoftPale: '#eef1fb',
  input: '#f4f5f7',
  inputBorder: '#eef0f4',
  chip: '#f1f2f5',
  chipActive: '#e8f0ff',
  pink: '#ff5b8f',
  pinkSoft: '#ffe6ef',
  rose: '#ff4d6d',
  orange: '#f5950f',
  orangeSoft: '#edba72',
  green: '#00b35d',
  warning: '#f6e16d',
  dangerSoft: '#ffebeb',
  sand: '#f0ecd6',
  sun: '#f6e16d',
  danger: '#f04b4b',
  overlay: 'rgba(15, 15, 15, 0.6)',
  shadow: 'rgba(16, 24, 40, 0.12)',
};

export const spacing = {
  xxs: moderateScale(4),
  xs: moderateScale(8),
  sm: moderateScale(12),
  md: moderateScale(16),
  lg: moderateScale(24),
  xl: moderateScale(32),
  xxl: moderateScale(40),
  xxxl: moderateScale(48),
};

export const radius = {
  sm: moderateScale(10),
  md: moderateScale(14),
  lg: moderateScale(20),
  xl: moderateScale(28),
  pill: 999,
};

export const typography = {
  display: {
    fontSize: moderateScale(34),
    fontWeight: '800' as const,
    lineHeight: moderateScale(40),
  },
  title: {
    fontSize: moderateScale(28),
    fontWeight: '700' as const,
    lineHeight: moderateScale(34),
  },
  heading: {
    fontSize: moderateScale(22),
    fontWeight: '700' as const,
    lineHeight: moderateScale(28),
  },
  body: {
    fontSize: moderateScale(14),
    fontWeight: '400' as const,
    lineHeight: moderateScale(20),
  },
  bodyStrong: {
    fontSize: moderateScale(14),
    fontWeight: '600' as const,
    lineHeight: moderateScale(20),
  },
  caption: {
    fontSize: moderateScale(12),
    fontWeight: '500' as const,
    lineHeight: moderateScale(16),
  },
};

export const shadows = {
  sm: {
    shadowColor: colors.shadow,
    shadowOpacity: 1,
    shadowRadius: 8,
    shadowOffset: { width: 0, height: 4 },
    elevation: 4,
  },
  md: {
    shadowColor: colors.shadow,
    shadowOpacity: 1,
    shadowRadius: 16,
    shadowOffset: { width: 0, height: 8 },
    elevation: 8,
  },
};
