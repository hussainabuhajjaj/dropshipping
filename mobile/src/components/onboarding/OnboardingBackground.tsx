import { StyleSheet } from 'react-native';
import { useMemo } from 'react';
import Svg, { G, Path } from 'react-native-svg';
import { theme } from '@/src/theme';

type Variant = 'hello' | 'ready';

type OnboardingBackgroundProps = {
  variant?: Variant;
};

const s = theme.scale;
const vs = theme.verticalScale;

const primaryColor = theme.colors.primary;
const accentColor = theme.colors.sun;

const variants: Record<Variant, { scale: number; offsetY: number }> = {
  hello: { scale: 1.05, offsetY: vs(-40) },
  ready: { scale: 1, offsetY: vs(-60) },
};

export function OnboardingBackground({ variant = 'hello' }: OnboardingBackgroundProps) {
  const { scale, offsetY } = variants[variant];

  const dimensions = useMemo(
    () => ({
      width: s(540 * scale),
      height: vs(360 * scale),
    }),
    [scale],
  );

  return (
    <Svg
      pointerEvents="none"
      style={[StyleSheet.absoluteFill, { top: offsetY }]}
      width={dimensions.width}
      height={dimensions.height}
      viewBox="0 0 540 360"
    >
      <G transform="translate(-5.94051813122806 40.56845452763139)">
        <Path
          d="M337 -369.9C434 -320.2 508 -211.4 497.9 -111.1C487.9 -10.8 393.8 81.1 320 162C246.2 242.8 192.7 312.6 123.8 335.8C55 359 -29.3 335.5 -96.3 297C-163.4 258.6 -213.2 205.2 -287.7 136.8C-362.2 68.3 -461.3 -15.2 -482.5 -118.1C-503.7 -221 -447.1 -343.2 -353.7 -393.5C-260.3 -443.9 -130.2 -422.5 -5.1 -416.4C120 -410.3 240 -419.7 337 -369.9"
          fill={primaryColor}
          opacity={0.85}
        />
      </G>
      <G transform="translate(527.6753183923494 -21.38952641460523)">
        <Path
          d="M358.8 -380.8C457.3 -345 524.1 -224.6 505.9 -119.9C487.7 -15.3 384.6 73.6 317.2 176.3C249.8 279 218.1 395.6 150 426.8C81.9 457.9 -22.7 403.8 -133.7 364.6C-244.7 325.4 -362.3 301.2 -426.7 227.8C-491.2 154.5 -502.6 31.9 -455.2 -53C-407.8 -138 -301.5 -185.3 -215.8 -223.4C-130.2 -261.5 -65.1 -290.2 32.5 -329C130.2 -367.8 260.3 -416.6 358.8 -380.8"
          fill={accentColor}
          opacity={0.26}
        />
      </G>
    </Svg>
  );
}
