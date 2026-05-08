<?php

namespace Client\Controller\Concerns;

trait AuthPasswordResetMailTrait
{
    private function buildPasswordResetLink(string $token): string
    {
        return $this->absoluteRouteUrl('auth/resetpassword') . '?token=' . urlencode($token);
    }

    private function sendPasswordRecoveryEmail(string $toEmail, string $toName, string $resetLink, string $expiresAt): bool
    {
        $appName = (string) $this->config->get('app.name', 'Solis');
        $fromEmail = (string) $this->config->get('security.auth.password_reset_from_email', '');
        $fromName = (string) $this->config->get('security.auth.password_reset_from_name', '');
        $expiresTs = $this->parseDateToTimestamp($expiresAt);
        $expiryLabel = $expiresTs === null ? $expiresAt : $this->formatDateTime('d/m/Y H:i', $expiresTs);
        $safeName = trim($toName) !== '' ? $toName : $toEmail;

        $subject = $this->t(
            'auth.mail_password_reset_subject',
            'Recuperacao de senha - {app}',
            ['app' => $appName]
        );

        $body = $this->t(
            'auth.mail_password_reset_body',
            "Ola {name},\n\nRecebemos uma solicitacao para redefinir a senha da sua conta em {app}.\n\nUse este link para criar uma nova senha:\n{link}\n\nEste link expira em: {expires_at}\n\nSe voce nao solicitou a redefinicao, ignore este e-mail.\n",
            [
                'name' => $safeName,
                'app' => $appName,
                'link' => $resetLink,
                'expires_at' => $expiryLabel,
            ]
        );

        return $this->mailService()->sendText(
            $toEmail,
            $subject,
            $body,
            $fromEmail,
            $fromName,
            $safeName
        );
    }

    private function sendEmailAccessReminder(string $toEmail, array $accounts): bool
    {
        if ($accounts === []) {
            return false;
        }

        $appName = (string) $this->config->get('app.name', 'Solis');
        $fromEmail = (string) $this->config->get('security.auth.password_reset_from_email', '');
        $fromName = (string) $this->config->get('security.auth.password_reset_from_name', '');
        $resetUrl = $this->absoluteRouteUrl('auth/forgotpassword');

        $subject = $this->t(
            'auth.mail_email_recovery_subject',
            'Lembrete do e-mail de acesso - {app}',
            ['app' => $appName]
        );

        $accountLines = $this->formatAccountsForReminder($accounts);
        $body = $this->t(
            'auth.mail_email_recovery_body',
            "Ola,\n\nRecebemos uma solicitacao para lembrar os e-mails de acesso da sua conta em {app}.\n\nE-mails encontrados:\n{accounts}\n\nSe tambem precisar redefinir a senha, use:\n{reset_url}\n\nSe voce nao solicitou este lembrete, ignore este e-mail.\n",
            [
                'app' => $appName,
                'accounts' => $accountLines,
                'reset_url' => $resetUrl,
            ]
        );

        return $this->mailService()->sendText(
            $toEmail,
            $subject,
            $body,
            $fromEmail,
            $fromName
        );
    }

    private function maskEmail(string $email): string
    {
        $email = trim(strtolower($email));
        if ($email === '' || !str_contains($email, '@')) {
            return '';
        }

        [$localPart, $domain] = explode('@', $email, 2);
        $localLength = strlen($localPart);

        if ($localLength <= 2) {
            $maskedLocal = substr($localPart, 0, 1) . '*';
        } else {
            $maskedLocal = substr($localPart, 0, 2) . str_repeat('*', max(1, $localLength - 2));
        }

        return $maskedLocal . '@' . $domain;
    }

    private function formatAccountsForReminder(array $accounts): string
    {
        $lines = [];

        foreach ($accounts as $account) {
            $email = strtolower(trim((string) ($account['email'] ?? '')));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $name = trim((string) ($account['name'] ?? ''));
            $label = $name !== '' ? $name . ' <' . $email . '>' : $email;
            $lines[] = '- ' . $label;
        }

        if ($lines === []) {
            return '-';
        }

        return implode("\n", array_slice($lines, 0, 10));
    }

}
