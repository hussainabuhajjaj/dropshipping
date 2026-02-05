import React from 'react';
import { Text as RNText, type TextProps } from 'react-native';
import { useTranslationsOptional } from '@/src/i18n/TranslationsProvider';

type Props = TextProps & {
  noTranslate?: boolean;
};

const translateChildren = (
  child: React.ReactNode,
  t: (key: string, fallback?: string) => string,
  noTranslate?: boolean
): React.ReactNode => {
  if (noTranslate) return child;
  if (typeof child === 'string') {
    return t(child, child);
  }
  if (Array.isArray(child)) {
    return child.map((item, index) => (
      <React.Fragment key={index}>
        {translateChildren(item, t, noTranslate)}
      </React.Fragment>
    ));
  }
  return child;
};

export function Text(props: Props) {
  const { children, noTranslate, ...rest } = props;
  const { t } = useTranslationsOptional();
  return <RNText {...rest}>{translateChildren(children, t, noTranslate)}</RNText>;
}

export default Text;
