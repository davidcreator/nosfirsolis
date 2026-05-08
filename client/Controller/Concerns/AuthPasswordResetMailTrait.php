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
        if (!function_exists('mail')) {
            return false;
        }

        $appName = (string) $this->config->get('app.name', 'Solis');
        $host = $this->effectiveRequestHost();
        $emailDomain = $this->hostForEmailDomain($host);

        $fromEmail = (string) $this->config->get('security.auth.password_reset_from_email', 'no-reply@' . $emailDomain);
        $fromName = (string) $this->config->get('security.auth.password_reset_from_name', $appName);
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

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $this->formatEmailHeader($fromName, $fromEmail),
            'Reply-To: ' . $fromEmail,
        ];

        return (bool) @mail($toEmail, $subject, $body, implode("\r\n", $headers));
    }

    private function formatEmailHeader(string $name, string $email): string
    {
        $safeEmail = preg_replace('/[\r\n]+/', '', trim($email)) ?? '';
        $safeName = trim(preg_replace('/[\r\n]+/', '', $name) ?? '');

        if ($safeName === '') {
            return $safeEmail;
        }

        return sprintf('"%s" <%s>', addslashes($safeName), $safeEmail);
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

    private function hostForEmailDomain(string $host): string
    {
        $host = trim($host);
        if ($host === '') {
            return 'localhost';
        }

        // Avoid invalid fallback emails like no-reply@::1.
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return 'localhost';
        }

        return $host;
    }
}
