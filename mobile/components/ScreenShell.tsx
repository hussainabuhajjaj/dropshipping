import { StyleSheet, Text, View } from 'react-native';
import { theme } from '@/constants/theme';

export const ScreenShell = ({
  title,
  description,
  children,
}: {
  title: string;
  description?: string;
  children?: React.ReactNode;
}) => {
  return (
    <View style={styles.container}>
      <Text style={styles.title}>{title}</Text>
      {description ? <Text style={styles.description}>{description}</Text> : null}
      <View style={styles.body}>{children}</View>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    padding: theme.spacing.lg,
    backgroundColor: theme.colors.background,
  },
  title: {
    fontSize: 22,
    fontWeight: '800',
    color: theme.colors.ink,
  },
  description: {
    marginTop: theme.spacing.xs,
    color: theme.colors.muted,
  },
  body: {
    marginTop: theme.spacing.lg,
  },
});
