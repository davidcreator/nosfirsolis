# Seguranca e Sanitizacao para Producao

## Objetivo

Consolidar validacoes de seguranca e sanitizacao do NosfirSolis com foco em prontidao para producao.

## Bateria de testes automatizada

Arquivo principal:

- `tests/security/run-security-suite.php`

Execucao:

```bash
php tests/security/run-security-suite.php
```

Cobertura da suite:

- Router executa apenas metodos publicos de controller.
- Redirecionamentos aplicam sanitizacao contra CRLF/protocol-relative e esquemas inseguros.
- Fluxos mutaveis sensiveis nao usam links GET (delete/logout sensiveis).
- Acoes mutaveis em controllers exigem POST + CSRF.
- Formularios POST em views incluem `csrf_field()`.
- `TokenCipher` usa payload autenticado (`v2`) e mantem compatibilidade legada.
- `AuthController` nao executa DDL de `password_resets` em runtime.
- `Auth` protege mutacao de schema por `security.runtime_schema_mutations`.
- Componentes com DDL runtime possuem guard por `security.runtime_schema_mutations`.
- Landing principal aplica HostGuard de forma consistente.
- Landing principal aplica headers de seguranca configuraveis.
- Landing principal considera HTTPS atras de proxy confiavel para scheme/HSTS.
- Sessao considera HTTPS atras de proxy confiavel para cookie `Secure`.
- Heuristica de sanitizacao para echos brutos suspeitos em views.
- Alertas de configuracao de producao.
- Alerta para `host_guard_compatibility_mode` em producao.
- Validacao de `allowed_hosts` em contexto de producao.
- Validacao de arquivos sensiveis de storage versionados no Git.

## Resultado Atual Da Validacao

Ultima execucao local: **2026-05-07**.

- Status geral: `PASS`
- `PASS`: 24
- `FAIL`: 0
- `WARN`: 0

Historico: a falha anterior da heuristica de echo bruto em views do calendario foi corrigida e revalidada.

## Endurecimentos Aplicados

### 1) CSRF e metodo HTTP em acoes sensiveis

- Exclusoes administrativas migradas para POST com CSRF:
  - campanhas
  - canais
  - feriados
  - comemorativas
  - sugestoes
- Exclusao de evento extra no calendario migrada para POST + CSRF.
- Logout de admin e cliente migrado para POST + CSRF.

### 2) Hardening do roteador

- Router valida por reflection e bloqueia invocacao de metodos nao publicos via rota.

### 3) Criptografia de tokens sensiveis

- `TokenCipher` usa payload autenticado (`AES-256-GCM`, prefixo `v2:`).
- Compatibilidade mantida para payload legado (`AES-256-CBC`).

### 4) Protecoes operacionais adicionais

- Sessao com `session.use_strict_mode`, `session.use_only_cookies` e `HttpOnly` reforcados.
- IP real em seguranca considera headers encaminhados apenas quando `REMOTE_ADDR` e proxy confiavel (`security.trusted_proxies`).
- Webhooks com bloqueio padrao de endpoints privados (mitigacao de SSRF), com opt-in por config.
- Headers basicos de seguranca adicionados em `.htaccess`.
- Bloqueio de acesso HTTP direto para caminhos sensiveis (`system/`, `system/Storage/`, `config.php`, `.env*`, `install/sql/`).
- Validacao de host permitido (`ALLOWED_HOSTS` / `security.allowed_hosts`) com resposta `400` para host invalido.
- `security.host_guard_compatibility_mode` controlado por config e variavel de ambiente, com default seguro (`false`/`0`).

### 5) Compatibilidade de feature flags com esquema legado

- `FeatureFlagService` calcula hierarquia apenas quando a estrategia da flag exige (`min_hierarchy`).
- Quando `user_groups.hierarchy_level` nao existe (base antiga), o servico aplica fallback seguro e evita erro fatal.

### 6) Enforcements adicionais de baseline em producao

- `config.php` e bootstrap da aplicacao passam a forcar baseline segura em producao para:
  - `security.host_guard_compatibility_mode=false`
  - `security.automation.allow_private_webhook_endpoints=false`
  - `security.auth.fail_open_on_security_error=false`
  - `security.runtime_schema_mutations=false`
- Objetivo: impedir override inseguro por variavel de ambiente ou config de runtime local.

### 7) Hardening de sessao atras de proxy

- `Session` passou a considerar `HTTP_X_FORWARDED_PROTO`/`HTTP_FORWARDED` quando `REMOTE_ADDR` pertence a `security.trusted_proxies`.
- Em cenarios de TLS offload, o cookie de sessao passa a manter `Secure=true` de forma correta.

### 8) Hardening de HTTPS na landing e em runtime

- `config.php` ganhou helper central (`nosfir_request_is_https`) com suporte a IP/CIDR para proxies confiaveis.
- `index.php` e `Application` passaram a usar deteccao HTTPS proxy-aware para schema/HSTS.
- Reduz falso negativo de HTTPS em topologias com reverse proxy/CDN.

### 9) Secrets hygiene para runtime storage config

- Instalador passou a gravar `DB_*` no `.env` e a manter senha vazia no `system/Storage/config.php`.
- Novo utilitario para ambientes legados:
  - `php tools/security/harden-runtime-storage-config.php`
  - migra `DB_*` para `.env`, remove senha explicita do runtime config e sincroniza ambiente com `APP_ENV`.

## Auditoria operacional (novo)

Comando:

```bash
php tools/security/run-operational-audit.php
```

Validacoes do auditor:

- baseline de seguranca efetiva por ambiente;
- guardrails de producao (com aviso quando houve override inseguro);
- postura de CSP/HSTS;
- conectividade de banco;
- presenca de tabelas centrais e operacionais;
- coluna `user_groups.hierarchy_level`;
- leitura de grants para sinalizar excesso de privilegios DDL.

Resultado local mais recente (2026-05-07):

- `PASS`: 17
- `WARN`: 0
- `FAIL`: 0
- Status: `PASS`

## Configuracao Recomendada Para Operacao Continua

1. Rotacionar `security.token_cipher_key` com janela planejada sempre que houver incidente ou troca de ambiente.
2. Atualizar `security.trusted_proxies` com os IPs/CIDRs reais do proxy reverso/CDN em producao.
3. Definir `ALLOWED_HOSTS` somente com dominios oficiais do ambiente (incluindo homologacao, quando aplicavel).
4. Manter `security.automation.allow_private_webhook_endpoints=false` por padrao (habilitar apenas com justificativa tecnica).
5. Manter `HOST_GUARD_COMPATIBILITY_MODE=0` em producao (usar `1` apenas em contingencia legada temporaria).
6. Reexecutar a suite apos qualquer mudanca de seguranca:
   - `php tests/security/run-security-suite.php`
7. Executar auditoria operacional antes de liberar ambiente:
   - `php tools/security/run-operational-audit.php`

Comando util para gerar nova chave:

- `php tools/security/generate-token-cipher-key.php`

### Rotacao sem indisponibilidade

1. Gerar nova chave e definir em `TOKEN_CIPHER_KEY`.
2. Mover a chave antiga para `TOKEN_CIPHER_KEY_PREVIOUS`.
3. Fazer deploy e validar login/publicacao/conexoes sociais.
4. Reexecutar `php tests/security/run-security-suite.php`.
5. Apos estabilizacao, limpar `TOKEN_CIPHER_KEY_PREVIOUS`.

### Variaveis de ambiente suportadas

No arquivo `.env` (raiz do projeto):

- `TOKEN_CIPHER_KEY`: chave de criptografia dos tokens sensiveis.
- `TOKEN_CIPHER_KEY_PREVIOUS`: chave(s) antiga(s) para decrypt temporario durante rotacao.
- `APP_ENV`: ambiente (`development`/`production`).
- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`: credenciais de banco por ambiente.
- `TRUSTED_PROXIES`: proxies confiaveis separados por virgula.
- `ALLOWED_HOSTS`: hosts permitidos para atendimento HTTP (mitiga Host Header Injection).
- `HOST_GUARD_COMPATIBILITY_MODE`: `0` ou `1` (recomendado `0`).
- `AUTOMATION_ALLOW_PRIVATE_WEBHOOK_ENDPOINTS`: `0` ou `1`.
- `SECURITY_RUNTIME_SCHEMA_MUTATIONS`: `0` ou `1` (recomendado `0`).
- `AUTH_FAIL_OPEN_ON_SECURITY_ERROR`: `0` ou `1` (recomendado `0`).
- `CSP_ALLOW_UNSAFE_EVAL`: `0` ou `1` (recomendado `0`, usar apenas por compatibilidade legada).

Referencia de template:

- `.env.example`

Guia de deploy por servidor web:

- `docs/deploy-producao-apache-nginx-env.md`

## Checklist Rapido De Liberacao

- [x] Suite de seguranca sem falhas
- [x] Auditoria operacional sem falhas
- [x] `token_cipher_key` preenchido
- [x] Proxies confiaveis configurados
- [x] `allowed_hosts` configurado
- [x] Webhooks privados bloqueados (ou excecao justificada)
- [ ] Validacao manual de login/logout, exclusoes e fluxos sensiveis
- [x] Revalidar heuristica de echos em views do calendario
