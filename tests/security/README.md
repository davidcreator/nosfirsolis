# Security Test Suite

Run:

```bash
php tests/security/run-security-suite.php
```

Environment:

- `config.php` loads `.env` automatically (if present).
- Use `.env.example` as a template.
- `TOKEN_CIPHER_KEY_PREVIOUS` is supported for key rotation without downtime.
- Generate strong key with `php tools/security/generate-token-cipher-key.php`.

What this suite validates:

- Router dispatch only executes public action methods.
- No mutating GET links for delete/logout-sensitive flows.
- Mutating controller actions enforce POST + CSRF guard.
- Every POST form in views includes `csrf_field()`.
- `TokenCipher` uses authenticated payload (`v2`) and keeps legacy decrypt compatibility.
- Production config warnings (`token_cipher_key`, `trusted_proxies`, private webhook endpoints).
- Heuristic for suspicious raw array echoes in views.
