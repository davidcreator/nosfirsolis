<?php

namespace System\Library;

class TokenCipher
{
    private const PAYLOAD_PREFIX_V2 = 'v2:';
    private string $secretKey;
    private array $legacySecretKeys = [];

    public function __construct(array $securityConfig = [], array $appConfig = [])
    {
        $fallback = (string) ($securityConfig['reinstall_key'] ?? '');
        if ($fallback === '') {
            $fallback = (string) ($appConfig['session_name'] ?? 'nosfirsolis-session-key');
        }
        $fallbackKey = hash('sha256', $fallback, true);

        $configured = (string) ($securityConfig['token_cipher_key'] ?? '');
        if ($configured !== '') {
            $this->secretKey = hash('sha256', $configured, true);
        } else {
            $this->secretKey = $fallbackKey;
        }

        $previousKeys = $this->normalizePreviousKeys($securityConfig['token_cipher_key_previous'] ?? []);
        foreach ($previousKeys as $previousKey) {
            $hashed = hash('sha256', $previousKey, true);
            $this->addLegacyKey($hashed);
        }

        if ($configured !== '') {
            // Keep compatibility for tokens encrypted before token_cipher_key was configured.
            $this->addLegacyKey($fallbackKey);
        }
    }

    public function encrypt(?string $plainText): ?string
    {
        if ($plainText === null || trim($plainText) === '') {
            return null;
        }

        if (!extension_loaded('openssl')) {
            return null;
        }

        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt(
            $plainText,
            'AES-256-GCM',
            $this->secretKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16
        );

        if ($cipher === false || strlen($tag) !== 16) {
            return null;
        }

        return self::PAYLOAD_PREFIX_V2 . base64_encode($iv . $tag . $cipher);
    }

    public function decrypt(?string $payload): ?string
    {
        if ($payload === null || trim($payload) === '') {
            return null;
        }

        if (!extension_loaded('openssl')) {
            return null;
        }

        $payload = trim($payload);
        if (str_starts_with($payload, self::PAYLOAD_PREFIX_V2)) {
            $encoded = substr($payload, strlen(self::PAYLOAD_PREFIX_V2));
            $raw = base64_decode($encoded, true);
            if ($raw === false || strlen($raw) <= 28) {
                return null;
            }

            $iv = substr($raw, 0, 12);
            $tag = substr($raw, 12, 16);
            $cipher = substr($raw, 28);
            if ($cipher === '') {
                return null;
            }

            foreach ($this->decryptionKeys() as $key) {
                $plain = openssl_decrypt(
                    $cipher,
                    'AES-256-GCM',
                    $key,
                    OPENSSL_RAW_DATA,
                    $iv,
                    $tag,
                    ''
                );

                if ($plain !== false) {
                    return $plain;
                }
            }

            return null;
        }

        // Backward compatibility with legacy AES-256-CBC payloads (base64(iv + cipher)).
        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) <= 16) {
            return null;
        }

        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        if ($cipher === '') {
            return null;
        }

        foreach ($this->decryptionKeys() as $key) {
            $plain = openssl_decrypt(
                $cipher,
                'AES-256-CBC',
                $key,
                OPENSSL_RAW_DATA,
                $iv
            );

            if ($plain !== false) {
                return $plain;
            }
        }

        return null;
    }

    private function decryptionKeys(): array
    {
        return array_merge([$this->secretKey], $this->legacySecretKeys);
    }

    private function normalizePreviousKeys(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (!is_array($value)) {
            return [];
        }

        $keys = [];
        foreach ($value as $entry) {
            if (!is_string($entry)) {
                continue;
            }

            $clean = trim($entry);
            if ($clean !== '') {
                $keys[] = $clean;
            }
        }

        return $keys;
    }

    private function addLegacyKey(string $key): void
    {
        if ($key === '' || hash_equals($this->secretKey, $key)) {
            return;
        }

        foreach ($this->legacySecretKeys as $existing) {
            if (hash_equals($existing, $key)) {
                return;
            }
        }

        $this->legacySecretKeys[] = $key;
    }
}
