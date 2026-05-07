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
- Redirect sanitization for CRLF, protocol-relative and non-http(s) targets.
- No mutating GET links for delete/logout-sensitive flows.
- Mutating controller actions enforce POST + CSRF guard.
- Every POST form in views includes `csrf_field()`.
- `TokenCipher` uses authenticated payload (`v2`) and keeps legacy decrypt compatibility.
- `AuthController` avoids runtime DDL for `password_resets`.
- `Auth` runtime DDL is guarded by `security.runtime_schema_mutations`.
- Components with runtime DDL include `security.runtime_schema_mutations` guards.
- Production config warnings (`token_cipher_key`, `trusted_proxies`, private webhook endpoints, HostGuard compatibility mode, allowed_hosts).
- Landing HostGuard and security headers coverage.
- Landing HTTPS detection behind trusted proxies (scheme/HSTS coherence).
- Session secure-cookie behavior behind trusted proxies.
- Application environment overrides for DB_* and APP_ENV.
- Sensitive storage files are not tracked in Git (`system/Storage/config*.php` and `system/Storage/sessions/sess_*`).
- Heuristic for suspicious raw array echoes in views.

Operational readiness audit (config + DB):

```bash
php tools/security/run-operational-audit.php
```
