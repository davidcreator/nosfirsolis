# Seguranca E Autenticacao

## Autenticacao Por Area

`System\Library\Auth` agora valida acesso por contexto:

- login em `admin` exige permissoes administrativas
- login em `client` exige permissoes de cliente

Aceita acesso quando o grupo possui:

- `*`
- `{area}.*` (ex.: `admin.*`, `client.*`)
- permissoes especificas iniciando com `{area}.`

## Permissoes De Grupo

Permissoes ficam em `user_groups.permissions_json`.

Seed padrao:

- Administradores -> `["*"]`
- Clientes -> `["client.*"]`

## Protecoes De Sessao

- regeneracao de sessao no login
- fingerprint por area + user_id + IP + user-agent
- TTL de sessao configuravel (`security.auth.session_ttl_minutes`)

Se houver mismatch de fingerprint ou expiracao:

- sessao invalida
- logout forcado
- evento auditado

## Rate Limit De Login

`SecurityService` aplica bloqueio por janela:

- limite por IP
- limite por email
- tempo de bloqueio configuravel

Config em `security.auth`:

- `window_minutes`
- `block_minutes`
- `max_attempts_per_ip`
- `max_attempts_per_user`

## Auditoria

Eventos sao registrados em `security_audit_logs`, incluindo:

- login success
- login blocked
- disconnect social
- logout
- anomalias de sessao

Tentativas de login ficam em `security_login_attempts`.

## CSRF E Metodos

Controllers sensiveis usam:

- validacao de metodo POST
- `verify_csrf` com token em formulario

## Instalador E Reinstalacao

Reinstalacao so e permitida com:

- `security.allow_reinstall = true`
- usuario autenticado com permissao `admin.install.reinstall`
- chave valida `security.reinstall_key` via query ou post

