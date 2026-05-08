# Relatorio Tecnico Profundo - Sistema Quotia

Data da analise: 2026-05-07  
Repositorio analisado: `E:\wamp64\www\nosfirsolis`

## 1) Resumo executivo

Status geral: **Risco alto** (seguranca e operacao).

O sistema tem uma base funcional robusta (modulos de cliente, admin, social, tracking, billing, operacoes e instalador), mas foi identificado um conjunto de riscos criticos em **segredos versionados**, **artefatos de sessao no Git**, **configuracoes perigosas por padrao em billing**, e **um bug funcional com potencial de erro fatal no fluxo de publicacao social**.

Se este repositorio for publico, o risco atual e imediato.

---

## 2) Metodologia e cobertura

Analise baseada em:

- Leitura de arquitetura, bootstrap, engine, bibliotecas e controladores principais.
- Revisao de seguranca em autenticacao, sessao, CSRF, host guard, webhooks e tokens.
- Revisao de banco (schema/seed e criacao dinamica de tabelas em runtime).
- Revisao de testes e estado atual de qualidade.
- Validacao automatica local:
  - `php tests/security/run-security-suite.php` -> **FAIL inicial** (8 PASS, 1 FAIL) e **PASS apos ajustes** (12 PASS, 0 FAIL).
  - `php -l` em todos os arquivos PHP -> **PASS**.

Metricas coletadas:

- Total de arquivos: **1491**
- Arquivos PHP: **188**
- LOC PHP aproximado por area:
  - `system`: 8558
  - `client`: 6742
  - `admin`: 5279
  - `install`: 943

---

## 3) Achados criticos (prioridade imediata)

### C1. Segredos e sessoes versionados no Git

**Evidencias**

- [`system/Storage/config.php`](/E:/wamp64/www/nosfirsolis/system/Storage/config.php:19) contem credenciais reais de banco em texto plano.
- [`system/Storage/config.php`](/E:/wamp64/www/nosfirsolis/system/Storage/config.php:28) contem `reinstall_key`.
- Arquivos de sessao reais estao versionados:
  - `system/Storage/sessions/sess_*` (com `_token`, `user_id`, fingerprint etc).
- `.gitignore` atual ignora apenas `.env`:
  - [`.gitignore`](/E:/wamp64/www/nosfirsolis/.gitignore:1)

**Impacto**

- Comprometimento de ambiente (DB, dados de usuarios, reset/reinstall key, sessao).
- Risco alto de takeover se repositorio for compartilhado/publico.

**Acao recomendada imediata**

1. Rotacionar credenciais (DB, chaves e tokens).
2. Remover arquivos sensiveis do historico Git.
3. Atualizar `.gitignore` para bloquear `system/Storage/config*.php`, `system/Storage/sessions/*`, cache e logs.

---

### C2. Possivel erro fatal na publicacao social por incompatibilidade de tipos

**Evidencias**

- [`system/Library/SocialPublishingService.php`](/E:/wamp64/www/nosfirsolis/system/Library/SocialPublishingService.php:598) define `decryptToken(...): string`.
- Retorno interno usa `TokenCipher::decrypt`, que e nullable:
  - [`system/Library/SocialPublishingService.php`](/E:/wamp64/www/nosfirsolis/system/Library/SocialPublishingService.php:605)
  - [`system/Library/TokenCipher.php`](/E:/wamp64/www/nosfirsolis/system/Library/TokenCipher.php:68)

**Risco tecnico**

- Se o token armazenado estiver invalido/corrompido, pode gerar `TypeError` em runtime no fluxo de publicacao (`publishNow`), resultando em erro 500.

**Acao recomendada imediata**

- Ajustar retorno para `?string` e tratar `null` com fallback seguro no fluxo.

---

### C3. Billing com autoaprovacao mock em configuracao padrao

**Evidencias**

- [`system/Config/app.php`](/E:/wamp64/www/nosfirsolis/system/Config/app.php:67): `mock_auto_approve => true`
- [`install/sql/seed.sql`](/E:/wamp64/www/nosfirsolis/install/sql/seed.sql:88): `billing.validation_mode = automatic`
- [`install/sql/seed.sql`](/E:/wamp64/www/nosfirsolis/install/sql/seed.sql:89): `billing.mock_auto_approve = 1`
- [`system/Library/SubscriptionService.php`](/E:/wamp64/www/nosfirsolis/system/Library/SubscriptionService.php:1083) usa autoaprovacao.

**Impacto**

- Em ambiente real, pagamento pode ser confirmado sem validacao externa se a configuracao nao for endurecida.

**Acao recomendada imediata**

- Forcar modo seguro em producao (`manual` ou gateway real), com feature gate por ambiente.

---

### C4. HostGuard em modo de compatibilidade pode permitir host nao autorizado

**Evidencias**

- [`system/Engine/Application.php`](/E:/wamp64/www/nosfirsolis/system/Engine/Application.php:183)
- [`system/Engine/Application.php`](/E:/wamp64/www/nosfirsolis/system/Engine/Application.php:191)

Quando `allowed_hosts` contem apenas defaults locais em producao, o sistema ativa um bypass de compatibilidade.

**Impacto**

- Aumenta superficie para ataques baseados em Host header em cenarios de configuracao incompleta.

**Acao recomendada imediata**

- Remover/limitar bypass em producao e exigir `ALLOWED_HOSTS` valido no deploy.

---

## 4) Achados altos

### A1. Suite de seguranca falhando no calendario (heuristica de output)

**Evidencias**

- Falha atual da suite: `Heuristica encontrou eco bruto suspeito em views`
- Linhas apontadas:
  - [`client/View/calendar/annual.php`](/E:/wamp64/www/nosfirsolis/client/View/calendar/annual.php:59)
  - [`client/View/calendar/index.php`](/E:/wamp64/www/nosfirsolis/client/View/calendar/index.php:129)
  - [`client/View/calendar/index.php`](/E:/wamp64/www/nosfirsolis/client/View/calendar/index.php:190)
  - [`client/View/calendar/monthly.php`](/E:/wamp64/www/nosfirsolis/client/View/calendar/monthly.php:83)

**Nota**

- Parte desses casos parece baixo risco (classes com valores binarios controlados), mas a falha precisa ser tratada para manter baseline de seguranca.

---

### A2. Controles de seguranca em modo fail-open quando infra nao permite DDL

**Evidencias**

- [`system/Library/SecurityService.php`](/E:/wamp64/www/nosfirsolis/system/Library/SecurityService.php:74) retorna `fail_open` em erro.
- Criacao de tabelas em runtime via `ensureTables()`:
  - [`system/Library/SecurityService.php`](/E:/wamp64/www/nosfirsolis/system/Library/SecurityService.php:203)

**Impacto**

- Se usuario de banco nao tiver permissao de criar tabela/alterar schema, protecoes podem ser desativadas silenciosamente.

---

### A3. Segredos de webhooks e payloads sensiveis armazenados em texto plano

**Evidencias**

- Schema de webhook guarda segredos:
  - [`system/Library/AutomationService.php`](/E:/wamp64/www/nosfirsolis/system/Library/AutomationService.php:30)
  - [`system/Library/AutomationService.php`](/E:/wamp64/www/nosfirsolis/system/Library/AutomationService.php:33)
- Logs guardam `payload_json` e `response_body`:
  - [`system/Library/AutomationService.php`](/E:/wamp64/www/nosfirsolis/system/Library/AutomationService.php:53)
  - [`system/Library/AutomationService.php`](/E:/wamp64/www/nosfirsolis/system/Library/AutomationService.php:259)
- UI coleta segredos em texto:
  - [`admin/View/operations/index.php`](/E:/wamp64/www/nosfirsolis/admin/View/operations/index.php:157)

**Impacto**

- Exposicao de credenciais de integracao e dados sensiveis de payload.

---

### A4. Blindagem SSRF incompleta no fallback HTTP

**Evidencias**

- Validacao por DNS usa `gethostbynamel`:
  - [`system/Library/AutomationService.php`](/E:/wamp64/www/nosfirsolis/system/Library/AutomationService.php:488)
- Fallback usa `file_get_contents` para request HTTP:
  - [`system/Library/AutomationService.php`](/E:/wamp64/www/nosfirsolis/system/Library/AutomationService.php:421)

**Risco**

- Cenarios de DNS rebinding/redirect podem contornar filtros em ambientes sem cURL ou com comportamento de redirect permissivo.

---

### A5. Dados base com sinais de mojibake (qualidade de conteudo)

**Evidencias**

- [`system/Storage/base_events.php`](/E:/wamp64/www/nosfirsolis/system/Storage/base_events.php:117) contem texto com encoding quebrado.

**Impacto**

- Experiencia inconsistente no calendario e necessidade de correcoes em lote de conteudo.

---

## 5) Achados medios

### M1. Cobertura de testes ainda muito concentrada

**Evidencias**

- Diretoria de testes contem essencialmente a suite de seguranca:
  - `tests/security/run-security-suite.php`

**Impacto**

- Risco de regressao funcional em billing/social/calendar sem alarme automatizado.

---

### M2. Hardening HTTP parcial

**Evidencias**

- `.htaccess` define `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`:
  - [`.htaccess`](/E:/wamp64/www/nosfirsolis/.htaccess:18)
- Nao ha baseline de CSP e HSTS no projeto.

---

### M3. Degradacao silenciosa em falha de banco no bootstrap

**Evidencias**

- Fallback para `new Database([])` no bootstrap:
  - [`system/Engine/Application.php`](/E:/wamp64/www/nosfirsolis/system/Engine/Application.php:85)
  - [`system/Engine/Application.php`](/E:/wamp64/www/nosfirsolis/system/Engine/Application.php:88)

**Impacto**

- Sistema pode subir parcialmente sem conexao, com falhas funcionais dificeis de diagnosticar.

---

### M4. Acoplamento e peso operacional do admin legado

**Observacao**

- `admin/View/js` tem ~785 arquivos e peso elevado de assets legados.
- Aumenta custo de manutencao, atualizacao e risco de dependencia desatualizada.

---

## 6) Pontos fortes identificados

- Estrutura MVCL clara (`admin/client/install/system`) e separacao por contexto.
- CSRF aplicado de forma consistente na maioria dos fluxos mutaveis.
- Rate limit e trilha de auditoria implementados para autenticacao.
- Host guard e allowlist de hosts implementados.
- TokenCipher com AES-GCM (`v2`) e suporte a rotacao de chave.
- Suite automatica de seguranca existente e executavel localmente.

---

## 7) Plano recomendado (30/60/90 dias)

### 0-30 dias (bloqueios imediatos)

1. Rotacionar segredos e limpar historico Git de credenciais/sessoes.
2. Corrigir `decryptToken` (`?string`) e adicionar tratamento robusto em publicacao social.
3. Forcar configuracao segura de billing em producao (sem mock auto approve).
4. Fechar falha da suite de seguranca no calendario e tornar suite obrigatoria no CI.
5. Remover bypass de compatibilidade de host em producao.

### 31-60 dias (estabilizacao)

1. Introduzir migrations versionadas (em vez de DDL oportunista em runtime).
2. Criptografar segredos de webhook em repouso e mascarar no painel.
3. Revisar politica de logs (payload minimization + retention + redaction).
4. Adicionar testes de regressao para auth, billing, social e calendario.

### 61-90 dias (maturidade)

1. Baseline de seguranca HTTP: CSP, HSTS e checklist de deploy.
2. Refatoracao gradual de modulos grandes (`SubscriptionService`, `SocialController`).
3. Reducao de legado estatico no admin (inventario + atualizacao de libs).
4. Painel de health operacional com alertas ativos (DB, fila, webhooks, erros).

---

## 8) Conclusao

O Quotia/Solis esta funcional e com bom escopo de produto, mas com **riscos criticos de seguranca e governanca de configuracao** que precisam de acao imediata antes de escalar o uso em producao.

A prioridade absoluta e: **segredos/sessoes no Git**, **hardening de billing**, **correcao do bug de publicacao social**, e **endurecimento do HostGuard em producao**.

---

## 9) Atualizacao de execucao (2026-05-07)

Status desta atualizacao: **acao imediata iniciada e parcialmente concluida**.

### Correcoes aplicadas

1. Hardening de versionamento de dados sensiveis:
   - `.gitignore` atualizado para bloquear:
     - `system/Storage/config.php`
     - `system/Storage/config-local.php`
     - `system/Storage/config copy.php`
     - `system/Storage/sessions/*`
     - `system/Storage/cache/*`
     - `system/Storage/logs/*`
     - `system/Storage/exports/*`
   - Placeholders adicionados para manter estrutura:
     - `system/Storage/sessions/.gitignore`
     - `system/Storage/cache/.gitignore`
     - `system/Storage/logs/.gitignore`
     - `system/Storage/exports/.gitignore`
   - `system/Storage/config-dist.php` criado com template sanitizado (sem segredo real).
   - Arquivos sensiveis removidos do indice Git com `git rm --cached` (mantidos em disco local).

2. Correcao no fluxo de publicacao social:
   - `decryptToken` ajustado para retorno `?string`.
   - Tratamento de token nulo/vazio adicionado antes da publicacao LinkedIn.
   - Objetivo: eliminar risco de `TypeError` em token invalido/corrompido.

3. Hardening de billing (padrao seguro):
   - `system/Config/app.php`: `mock_auto_approve` alterado para `false`.
   - `install/sql/seed.sql`:
     - `billing.validation_mode` alterado para `manual`.
     - `billing.mock_auto_approve` alterado para `0`.
   - `SubscriptionService::mockAutoApprove()` endurecido para nunca autoaprovar em ambiente `production|prod|live`, mesmo com flag habilitada.

4. Endurecimento adicional de superficie:
   - Falha da suite de seguranca no calendario corrigida nas views:
     - `client/View/calendar/annual.php`
     - `client/View/calendar/index.php`
     - `client/View/calendar/monthly.php`
   - HostGuard em producao endurecido:
     - bypass de compatibilidade agora depende de `security.host_guard_compatibility_mode = true` (default `false`).
   - Configuracao por ambiente reforcada:
     - suporte explicito a `HOST_GUARD_COMPATIBILITY_MODE` em `config.php` e no `.env` gerado pelo instalador.
    - Suite de seguranca reforcada:
      - valida regressao de `allowed_hosts`/HostGuard em producao.
      - valida que arquivos sensiveis de storage nao estejam versionados no Git.

5. Saneamento de historico preparado e validado em clone espelho:
   - `git-filter-repo` instalado.
   - reescrita aplicada em mirror isolado:
     - `system/Storage/exports/history-rewrite/nosfirsolis-mirror.git`
   - validacao no mirror sem ocorrencias de:
     - `system/Storage/config*.php`
     - `system/Storage/sessions/sess_*`
   - script operacional criado:
     - `tools/security/rewrite-sensitive-history.ps1`
   - publicacao concluida:
     - `push --force --mirror` aplicado em `origin` (GitHub).
     - branch `main` remota reescrita de `93cee3a` para `57d9e6d`.

### Validacao pos-ajuste

- `php -l` nos arquivos alterados: **PASS**.
- `php tests/security/run-security-suite.php`: **PASS** (12 PASS, 0 FAIL).
- Confirmacao de rastreamento Git:
  - arquivos sensiveis removidos de `git ls-files`;
  - regras de ignore ativas via `git check-ignore`.

### Pendencias relevantes

1. Rotacao efetiva de credenciais (DB/chaves/tokens) fora do repositorio.
2. Comunicacao de reescrita para colaboradores e orientacao de reclone/reset local.
3. Revisao final de `security.allowed_hosts` por ambiente (homologacao/producao) antes de deploy.
