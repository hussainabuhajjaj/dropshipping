import { colors, radius, shadows, spacing, typography } from './tokens';
import { moderateScale, scale, screen, verticalScale } from './responsive';

export const theme = {
  colors,
  radius,
  shadows,
  spacing,
  typography,
  scale,
  verticalScale,
  moderateScale,
  screen,
};

export type Theme = typeof theme;
