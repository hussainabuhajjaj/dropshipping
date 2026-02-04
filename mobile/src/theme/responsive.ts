import { Dimensions } from 'react-native';

const guidelineBaseWidth = 375;
const guidelineBaseHeight = 812;

const { width, height } = Dimensions.get('window');

export const screen = {
  width,
  height,
  isCompact: width < 360,
  isLarge: width >= 414,
};

export const scale = (size: number) => (width / guidelineBaseWidth) * size;
export const verticalScale = (size: number) => (height / guidelineBaseHeight) * size;
export const moderateScale = (size: number, factor = 0.5) =>
  size + (scale(size) - size) * factor;
