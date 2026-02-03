import { useState } from 'react';
import { router } from 'expo-router';
import { AuthScreen } from '@/src/components/auth/AuthScreen';
import { RecoveryCodeContent } from '@/src/components/auth/RecoveryCodeContent';
import { MaximumAttemptsDialog } from '@/src/overlays/MaximumAttemptsDialog';
import { routes } from '@/src/navigation/routes';

export default function PasswordCodeScreen() {
  const [showMaximumAttempts, setShowMaximumAttempts] = useState(false);

  return (
    <AuthScreen variant="recovery">
      <RecoveryCodeContent
        onSubmit={() => router.push(routes.newPassword)}
        onSendAgain={() => setShowMaximumAttempts(true)}
        onCancel={() => router.push(routes.login)}
      />
      <MaximumAttemptsDialog
        visible={showMaximumAttempts}
        onConfirm={() => setShowMaximumAttempts(false)}
      />
    </AuthScreen>
  );
}
