import { useEffect, useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { theme } from '@/constants/theme';

const formatTime = (seconds: number) => {
  const hrs = Math.floor(seconds / 3600);
  const mins = Math.floor((seconds % 3600) / 60);
  const secs = seconds % 60;
  return [hrs, mins, secs].map((part) => String(part).padStart(2, '0')).join(':');
};

export const CountdownChip = ({
  label = 'Ends in',
  initialSeconds = 7200,
}: {
  label?: string;
  initialSeconds?: number;
}) => {
  const [secondsLeft, setSecondsLeft] = useState(initialSeconds);

  useEffect(() => {
    const timer = setInterval(() => {
      setSecondsLeft((prev) => (prev > 0 ? prev - 1 : 0));
    }, 1000);

    return () => clearInterval(timer);
  }, []);

  return (
    <View style={styles.chip}>
      <Text style={styles.label}>{label}</Text>
      <Text style={styles.timer}>{formatTime(secondsLeft)}</Text>
    </View>
  );
};

const styles = StyleSheet.create({
  chip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: theme.radius.pill,
    backgroundColor: theme.colors.surface,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  label: {
    fontSize: 11,
    fontWeight: '600',
    color: theme.colors.muted,
  },
  timer: {
    fontSize: 11,
    fontWeight: '700',
    color: theme.colors.brandCoral,
  },
});
