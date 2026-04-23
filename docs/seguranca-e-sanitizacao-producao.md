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
- Fluxos mutaveis sensiveis nao usam links GET (delete/logout sensiveis).
- Acoes mutaveis em controllers exigem POST + CSRF.
- Formularios POST em views incluem `csrf_field()`.
- `TokenCipher` usa payload autenticado (`v2`) e mantem compatibilidade legada.
- Heuristica de sanitizacao para echos brutos suspeitos em views.
- Alertas de configuracao de producao.

## Resultado atual da validacao

Status atual:

- `PASS`
- Sem falhas bloqueantes.
- Sem alertas pendentes na suite automatizada.

## Endurecimentos aplicados

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

- Router agora valida por reflection e bloqueia invocacao de metodos nao publicos via rota.

### 3) Criptografia de tokens sensiveis

- `TokenCipher` atualizado para payload autenticado (`AES-256-GCM`, prefixo `v2:`).
- Compatibilidade mantida para payload legado (`AES-256-CBC`).

### 4) Protecoes operacionais adicionais

- Sessao com `session.use_strict_mode`, `session.use_only_cookies` e `HttpOnly` reforcados.
- IP real em seguranca considera headers encaminhados apenas quando `REMOTE_ADDR` e proxy confiavel (`security.trusted_proxies`).
- Webhooks com bloqueio padrao de endpoints privados (mitigacao de SSRF), com opt-in por config.
- Headers basicos de seguranca adicionados em `.htaccess`.
- Bloqueio de acesso HTTP direto para caminhos sensiveis (`system/`, `system/Storage/`, `config.php`, `.env*`, `install/sql/`).
- Validacao de host permitido (`ALLOWED_HOSTS` / `security.allowed_hosts`) com resposta `400` para host invalido.

### 5) Compatibilidade de feature flags com esquema legado

- `FeatureFlagService` passou a calcular hierarquia apenas quando a estrategia da flag exige (`min_hierarchy`).
- Quando `user_groups.hierarchy_level` nao existe (base antiga), o servico aplica fallback seguro e evita erro fatal.

## Configuracao recomendada para operacao continua

1. Rotacionar `security.token_cipher_key` com janela planejada sempre que houver incidente ou troca de ambiente.
2. Atualizar `security.trusted_proxies` com os IPs/CIDRs reais do proxy reverso/CDN em producao.
3. Definir `ALLOWED_HOSTS` somente com dominios oficiais do ambiente (incluindo homologacao, quando aplicavel).
4. Manter `security.automation.allow_private_webhook_endpoints=false` por padrao (habilitar apenas com justificativa tecnica).
5. Reexecutar a suite apos qualquer mudanca de seguranca:
   - `php tests/security/run-security-suite.php`

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
- `TRUSTED_PROXIES`: proxies confiaveis separados por virgula.
- `ALLOWED_HOSTS`: hosts permitidos para atendimento HTTP (mitiga Host Header Injection).
- `AUTOMATION_ALLOW_PRIVATE_WEBHOOK_ENDPOINTS`: `0` ou `1`.

Referencia de template:

- `.env.example`

Guia de deploy por servidor web:

- `docs/deploy-producao-apache-nginx-env.md`

## Checklist rapido de liberacao

- [x] Suite de seguranca sem falhas
- [x] `token_cipher_key` preenchido
- [x] Proxies confiaveis configurados
- [x] `allowed_hosts` configurado
- [x] Webhooks privados bloqueados (ou excecao justificada)
- [ ] Validacao manual de login/logout, exclusoes e fluxos sensiveis
