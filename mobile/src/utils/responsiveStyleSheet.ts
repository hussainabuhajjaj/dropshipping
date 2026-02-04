import { StyleSheet as RNStyleSheet } from 'react-native';
import { moderateScale } from '@/src/theme/responsive';

const SCALE_KEYS = new Set([
  'width',
  'height',
  'minWidth',
  'maxWidth',
  'minHeight',
  'maxHeight',
  'margin',
  'marginTop',
  'marginBottom',
  'marginLeft',
  'marginRight',
  'marginHorizontal',
  'marginVertical',
  'padding',
  'paddingTop',
  'paddingBottom',
  'paddingLeft',
  'paddingRight',
  'paddingHorizontal',
  'paddingVertical',
  'top',
  'bottom',
  'left',
  'right',
  'gap',
  'rowGap',
  'columnGap',
  'borderRadius',
  'borderWidth',
  'borderTopWidth',
  'borderBottomWidth',
  'borderLeftWidth',
  'borderRightWidth',
  'fontSize',
  'lineHeight',
  'letterSpacing',
  'shadowRadius',
  'shadowOpacity',
  'shadowOffset',
]);

const scaleValue = (key: string, value: unknown) => {
  if (typeof value !== 'number') {
    return value;
  }

  return SCALE_KEYS.has(key) ? moderateScale(value) : value;
};

const scaleStyle = (style: unknown): unknown => {
  if (!style || typeof style !== 'object') {
    return style;
  }

  if (Array.isArray(style)) {
    return style.map(scaleStyle);
  }

  const next: Record<string, unknown> = {};
  Object.entries(style).forEach(([key, value]) => {
    if (key === 'shadowOffset' && value && typeof value === 'object' && !Array.isArray(value)) {
      const offset = value as { width?: number; height?: number };
      next[key] = {
        width: typeof offset.width === 'number' ? moderateScale(offset.width) : offset.width,
        height: typeof offset.height === 'number' ? moderateScale(offset.height) : offset.height,
      };
      return;
    }

    next[key] = scaleValue(key, value);
  });

  return next;
};

const create = <T extends RNStyleSheet.NamedStyles<T> | RNStyleSheet.NamedStyles<any>>(
  styles: T,
) => {
  const scaled: Record<string, unknown> = {};
  Object.entries(styles).forEach(([key, style]) => {
    scaled[key] = scaleStyle(style);
  });

  return RNStyleSheet.create(scaled as T);
};

export const StyleSheet = { ...RNStyleSheet, create };
export * from 'react-native';
