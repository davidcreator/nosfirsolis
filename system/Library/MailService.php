<?php

namespace System\Library;

use System\Engine\Registry;

class MailService
{
    public function __construct(private readonly Registry $registry)
    {
    }

    public function sendText(
        string $toEmail,
        string $subject,
        string $body,
        string $fromEmail = '',
        string $fromName = '',
        ?string $toName = null
    ): bool {
        $toEmail = $this->sanitizeEmail($toEmail);
        if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $subject = $this->sanitizeHeaderText($subject);
        if ($subject === '') {
            return false;
        }

        $driver = strtolower(trim((string) $this->config('security.mail.driver', 'php_mail')));
        $fallback = $this->toBool($this->config('security.mail.fallback_to_php_mail', true));

        $resolvedFromEmail = $this->resolveFromEmail($fromEmail);
        $resolvedFromName = $this->resolveFromName($fromName);
        $resolvedToName = $this->sanitizeHeaderText((string) ($toName ?? ''));

        if ($driver === 'smtp') {
            $sent = $this->sendViaSmtp(
                $toEmail,
                $resolvedToName,
                $subject,
                $body,
                $resolvedFromEmail,
                $resolvedFromName
            );

            if ($sent) {
                return true;
            }

            if (!$fallback) {
                return false;
            }
        }

        return $this->sendViaPhpMail(
            $toEmail,
            $subject,
            $body,
            $resolvedFromEmail,
            $resolvedFromName
        );
    }

    private function sendViaPhpMail(
        string $toEmail,
        string $subject,
        string $body,
        string $fromEmail,
        string $fromName
    ): bool {
        if (!function_exists('mail')) {
            return false;
        }

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $this->formatMailboxHeader($fromName, $fromEmail),
            'Reply-To: ' . $fromEmail,
        ];

        return (bool) @mail($toEmail, $subject, $body, implode("\r\n", $headers));
    }

    private function sendViaSmtp(
        string $toEmail,
        string $toName,
        string $subject,
        string $body,
        string $fromEmail,
        string $fromName
    ): bool {
        $host = trim((string) $this->config('security.mail.smtp.host', ''));
        if ($host === '') {
            error_log('[Solis] MailService SMTP host vazio.');
            return false;
        }

        $port = (int) $this->config('security.mail.smtp.port', 587);
        $port = max(1, min(65535, $port));

        $encryption = strtolower(trim((string) $this->config('security.mail.smtp.encryption', 'tls')));
        if (!in_array($encryption, ['none', 'tls', 'ssl'], true)) {
            $encryption = 'tls';
        }

        $timeoutSeconds = (int) $this->config('security.mail.smtp.timeout_seconds', 15);
        $timeoutSeconds = max(5, min(60, $timeoutSeconds));

        $verifyPeer = $this->toBool($this->config('security.mail.smtp.verify_peer', true));
        $smtpAuth = $this->toBool($this->config('security.mail.smtp.auth', true));
        $username = (string) $this->config('security.mail.smtp.username', '');
        $password = (string) $this->config('security.mail.smtp.password', '');

        $transportHost = $encryption === 'ssl' ? 'ssl://' . $host : $host;
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => $verifyPeer,
                'verify_peer_name' => $verifyPeer,
                'allow_self_signed' => !$verifyPeer,
            ],
        ]);

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            $transportHost . ':' . $port,
            $errno,
            $errstr,
            $timeoutSeconds,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!is_resource($socket)) {
            error_log('[Solis] MailService SMTP falhou ao conectar: ' . $errstr . ' (' . $errno . ')');
            return false;
        }

        stream_set_timeout($socket, $timeoutSeconds);

        try {
            if (!$this->smtpExpect($socket, [220])) {
                throw new \RuntimeException('Greeting SMTP invalido.');
            }

            $heloHost = $this->smtpHeloHost();
            if (!$this->smtpCommand($socket, 'EHLO ' . $heloHost, [250])) {
                if (
                    !$this->smtpCommand($socket, 'HELO ' . $heloHost, [250])
                ) {
                    throw new \RuntimeException('EHLO/HELO rejeitado.');
                }
            }

            if ($encryption === 'tls') {
                if (!$this->smtpCommand($socket, 'STARTTLS', [220])) {
                    throw new \RuntimeException('STARTTLS rejeitado.');
                }

                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('Falha ao iniciar criptografia TLS.');
                }

                if (!$this->smtpCommand($socket, 'EHLO ' . $heloHost, [250])) {
                    throw new \RuntimeException('EHLO pos-TLS rejeitado.');
                }
            }

            if ($smtpAuth && $username !== '') {
                if (!$this->smtpCommand($socket, 'AUTH LOGIN', [334])) {
                    throw new \RuntimeException('AUTH LOGIN rejeitado.');
                }

                if (!$this->smtpCommand($socket, base64_encode($username), [334])) {
                    throw new \RuntimeException('Usuario SMTP rejeitado.');
                }

                if (!$this->smtpCommand($socket, base64_encode($password), [235])) {
                    throw new \RuntimeException('Senha SMTP rejeitada.');
                }
            }

            if (!$this->smtpCommand($socket, 'MAIL FROM:<' . $fromEmail . '>', [250])) {
                throw new \RuntimeException('MAIL FROM rejeitado.');
            }

            if (!$this->smtpCommand($socket, 'RCPT TO:<' . $toEmail . '>', [250, 251])) {
                throw new \RuntimeException('RCPT TO rejeitado.');
            }

            if (!$this->smtpCommand($socket, 'DATA', [354])) {
                throw new \RuntimeException('DATA rejeitado.');
            }

            $headers = [
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'From: ' . $this->formatMailboxHeader($fromName, $fromEmail),
                'To: ' . $this->formatMailboxHeader($toName, $toEmail),
                'Subject: ' . $this->encodeHeaderUtf8($subject),
                'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
            ];

            $data = implode("\r\n", $headers) . "\r\n\r\n" . $this->smtpEscapeBody($body) . "\r\n.\r\n";
            fwrite($socket, $data);

            if (!$this->smtpExpect($socket, [250])) {
                throw new \RuntimeException('Mensagem rejeitada pelo servidor SMTP.');
            }

            $this->smtpCommand($socket, 'QUIT', [221, 250]);

            return true;
        } catch (\Throwable $exception) {
            error_log('[Solis] MailService SMTP erro: ' . $exception->getMessage());
            return false;
        } finally {
            if (is_resource($socket)) {
                fclose($socket);
            }
        }
    }

    private function smtpHeloHost(): string
    {
        $host = trim((string) ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        if ($host === '' || !preg_match('/^[a-z0-9\.\-]+$/i', $host)) {
            return 'localhost';
        }

        return strtolower($host);
    }

    private function smtpCommand($socket, string $command, array $okCodes): bool
    {
        fwrite($socket, $command . "\r\n");
        return $this->smtpExpect($socket, $okCodes);
    }

    private function smtpExpect($socket, array $okCodes): bool
    {
        $response = $this->smtpReadResponse($socket);
        if ($response === null) {
            return false;
        }

        $code = (int) substr($response, 0, 3);
        return in_array($code, $okCodes, true);
    }

    private function smtpReadResponse($socket): ?string
    {
        $response = '';

        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (strlen($line) < 4) {
                break;
            }

            // Continuation format: 250-...
            if ($line[3] !== '-') {
                break;
            }
        }

        $response = trim($response);
        return $response === '' ? null : $response;
    }

    private function smtpEscapeBody(string $body): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        $lines = explode("\n", $body);

        foreach ($lines as $index => $line) {
            if (str_starts_with($line, '.')) {
                $lines[$index] = '.' . $line;
            }
        }

        return implode("\r\n", $lines);
    }

    private function encodeHeaderUtf8(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('/^[\x20-\x7E]+$/', $value) === 1) {
            return $value;
        }

        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private function formatMailboxHeader(string $name, string $email): string
    {
        $email = $this->sanitizeEmail($email);
        $name = $this->sanitizeHeaderText($name);

        if ($name === '') {
            return $email;
        }

        return sprintf('"%s" <%s>', addslashes($name), $email);
    }

    private function resolveFromEmail(string $preferred): string
    {
        $preferred = $this->sanitizeEmail($preferred);
        if ($preferred !== '' && filter_var($preferred, FILTER_VALIDATE_EMAIL)) {
            return $preferred;
        }

        $configured = $this->sanitizeEmail((string) $this->config('security.mail.from_email', ''));
        if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_EMAIL)) {
            return $configured;
        }

        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost'));
        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $host = 'localhost';
        }

        if (str_contains($host, ':')) {
            $host = explode(':', $host, 2)[0];
        }

        return 'no-reply@' . strtolower(trim($host));
    }

    private function resolveFromName(string $preferred): string
    {
        $preferred = $this->sanitizeHeaderText($preferred);
        if ($preferred !== '') {
            return $preferred;
        }

        return $this->sanitizeHeaderText((string) $this->config('security.mail.from_name', 'Solis'));
    }

    private function sanitizeEmail(string $value): string
    {
        return trim((string) preg_replace('/[\r\n]+/', '', $value));
    }

    private function sanitizeHeaderText(string $value): string
    {
        $value = trim((string) preg_replace('/[\r\n]+/', ' ', $value));
        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    private function config(string $key, mixed $default = null): mixed
    {
        $config = $this->registry->get('config');
        if (!is_object($config) || !method_exists($config, 'get')) {
            return $default;
        }

        return $config->get($key, $default);
    }
}
