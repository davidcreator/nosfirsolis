# Changelog

Todas as mudancas relevantes de codigo e seguranca registradas neste diretorio de documentacao.

## 2026-05-08

### Fechamento De Producao E Benchmark

- Novo pacote formal de PR para fechamento de producao:
  - `docs/pacote-formal-pr-fechamento-producao-solis-2026-05-08.md`
- Novo modelo de PR preenchido para abertura imediata no GitHub:
  - `docs/pr-formal-fechamento-producao-solis-2026-05-08.md`
- Template institucional de Pull Request normalizado (correcao textual em migracao):
  - `.github/PULL_REQUEST_TEMPLATE.md`
- Novo runbook formal para merge controlado e validacoes pos-merge:
  - `docs/runbook-merge-controlado-producao-solis-2026-05-08.md`
- Novo relatorio formal de validacao pre-merge com `main`:
  - `docs/relatorio-validacao-pre-merge-main-solis-2026-05-08.md`
- Novo checklist operacional de execucao de go-live em producao:
  - `docs/checklist-execucao-go-live-producao-solis-2026-05-08.md`
- Novo relatorio de execucao assistida de go-live local:
  - `docs/relatorio-execucao-assistida-go-live-local-solis-2026-05-08.md`
- Nova ata formal para encerramento de janela de go-live:
  - `docs/ata-encerramento-go-live-producao-solis-2026-05-08.md`
- Novo modelo preenchido de ata para encerramento com decisao `GO`:
  - `docs/ata-encerramento-go-live-producao-solis-2026-05-08-exemplo-go.md`
- Novo modelo preenchido de ata para encerramento com decisao `ROLLBACK`:
  - `docs/ata-encerramento-go-live-producao-solis-2026-05-08-exemplo-rollback.md`
- Novo script de benchmark reutilizavel para endpoints de autenticacao:
  - `tools/performance/run-auth-http-benchmark.php`
- Relatorio tecnico consolidado com benchmark pos-refatoracao:
  - `docs/relatorio-analise-tecnica-benchmark-solis-2026-05-08.md`
- Novo checklist formal de fechamento para producao:
  - `docs/checklist-fechamento-producao-solis-2026-05-08.md`
- Refatoracao do fluxo de recuperacao em traits por responsabilidade:
  - `client/Controller/Concerns/AuthPasswordResetFlowTrait.php` (agregador)
  - `client/Controller/Concerns/AuthPasswordResetRequestTrait.php`
  - `client/Controller/Concerns/AuthPasswordResetTokenTrait.php`
  - `client/Controller/Concerns/AuthEmailRecoveryFlowTrait.php`
- Suite critica atualizada para validar contrato de reset em arquitetura multi-trait:
  - `tests/critical/run-critical-flow-suite.php`
- Contratos de composicao atualizados para novos traits:
  - `tools/architecture/run-service-composition-audit.php`
- Nova homologacao operacional final de producao com evidencias de:
  - quality gates
  - security suite
  - operational audit
  - build `system/` e `prod/system`
  - smoke HTTP do espelho `prod/`
  - matriz de variaveis criticas (`.env.example` e `prod/.env.example`)
  - arquivo: `docs/homologacao-operacional-final-producao-solis-2026-05-08.md`
- Release notes formais de producao publicadas:
  - `docs/release-notes-producao-solis-2026-05-08.md`

### Arquitetura E Qualidade

- Suite critica ampliada com smoke dinamico de banco:
  - `tests/critical/run-critical-flow-suite.php`
  - nova cobertura de runtime para conectividade, tabelas, colunas, enums e indices dos fluxos:
    - reset de senha
    - billing/validacao manual
    - publicacao social
    - calendario (notas e eventos extras)
- Novo gate de maturidade MVCL por orcamento:
  - `tools/architecture/run-mvcl-maturity-budget-audit.php`
  - validacoes de:
    - tamanho de controllers
    - tamanho de models/libraries por classe/trait
    - densidade de service accessors em controllers
    - volume de metodos publicos em controllers
  - excecao controlada de budget para `install/Model/InstallerModel.php` (perfil de instalacao).
- Pipeline de qualidade integrado com novo gate:
  - `tools/quality/run-quality-gates.php`
  - adicao de `mvcl_maturity` (`bit=32`).

### Testes E Validacao

- `php tests/critical/run-critical-flow-suite.php`: `PASS` (5 `PASS`, 0 `WARN`, 0 `FAIL`).
- `php tools/architecture/run-mvcl-maturity-budget-audit.php`: `PASS` (4 `PASS`, 0 `WARN`, 0 `FAIL`).
- `php tools/quality/run-quality-gates.php --exit-mode=bitmap`: `PASS` com `Checks=6`, `Passes=6`, `Failures=0`, `FailureMask=0`, `ExitCode=0`.

## 2026-05-07

### Seguranca

- Hardening de versionamento:
  - `.gitignore` atualizado para bloquear `system/Storage/config*.php`, sessoes, cache, logs e exports.
  - placeholders `.gitignore` adicionados em `system/Storage/sessions`, `cache`, `logs` e `exports`.
  - arquivos sensiveis removidos do indice Git com `git rm --cached` (mantidos localmente).
- Correcao de risco de `TypeError` em publicacao social:
  - `SocialPublishingService::decryptToken()` ajustado para `?string` com tratamento seguro.
- Hardening de billing:
  - `integrations.billing.mock_auto_approve=false`.
  - seed inicial ajustado para `billing.validation_mode=manual` e `billing.mock_auto_approve=0`.
  - `SubscriptionService` bloqueia autoaprovacao mock em `production|prod|live`.
- HostGuard endurecido:
  - modo de compatibilidade legado condicionado a `security.host_guard_compatibility_mode` (default seguro: `false`/`0`).
  - suporte via ambiente com `HOST_GUARD_COMPATIBILITY_MODE`.
- Suite de seguranca ampliada:
  - validacao de `allowed_hosts` em producao.
  - alerta para `host_guard_compatibility_mode=true` em producao.
  - validacao de arquivos sensiveis versionados no Git.

### Ferramentas E Operacao

- Novo script operacional:
  - `tools/security/rewrite-sensitive-history.ps1`
  - cria clone espelho isolado, executa `git filter-repo`, valida limpeza e faz push opcional.
- Novo runbook:
  - `docs/limpeza-historico-segredos-git.md`
- Novo pacote de operacao pos-rewrite:
  - `docs/comunicado-reescrita-historico-2026-05-07.md`
  - `docs/checklist-rotacao-segredos-2026-05-07.md`
  - `docs/sincronizacao-local-pos-rewrite-2026-05-07.md`
- Novo comunicado operacional pronto para envio:
  - `docs/comunicado-operacional-slack-whatsapp-2026-05-07.md`
  - inclui janela sugerida e comandos separados por perfil (`dev`, `qa`, `infra`).
- Novo arquivo de disparo rapido:
  - `docs/mensagens-prontas-disparo-2026-05-07.md`
  - inclui versoes prontas para WhatsApp, Slack e e-mail.
- Novo pacote para comunicacao de alta velocidade:
  - `docs/mensagens-ultra-curta-e-executiva-2026-05-07.md`
  - inclui versoes ultra curta (1 linha) e executiva (diretoria/parceiros).
- Novo pacote formal consolidado:
  - `docs/mensagem-final-formal-2026-05-07.md`
  - inclui modelos formais para WhatsApp, Slack, nota oficial e e-mail executivo.
- Novo registro institucional:
  - `docs/ata-incidente-seguranca-2026-05-07.md`
  - inclui descricao formal do evento, evidencias, riscos residuais e campos de aprovacao.
- Nova versao para assinatura:
  - `docs/ata-incidente-seguranca-assinatura-2026-05-07.md`
  - inclui cabecalho institucional, termo de ciencia e blocos formais de assinatura.
- Nova versao curta para governanca executiva:
  - `docs/ata-curta-diretoria-2026-05-07.md`
  - formato de 1 pagina com sumario, deliberacao e assinaturas da diretoria.
- Reescrita de historico publicada no remoto:
  - `origin/main` alterada de `93cee3a` para `57d9e6d` via `push --force --mirror`.

### Testes E Validacao

- `php tests/security/run-security-suite.php` executado em `2026-05-07`.
- Resultado: `PASS` (12 `PASS`, 0 `FAIL`, 0 `WARN`).

## 2026-05-05

### Documentacao

- `README.md` da raiz atualizado com:
  - escopo funcional atual (auth, billing cliente/admin, operacoes)
  - links de documentacao complementar fora de `docs/`
  - snapshot datado da suite de seguranca
- `docs/README.md` reorganizado e sincronizado com o estado atual do repositorio.
- `docs/produto-nosfir-solis.md` revisado para refletir billing e governanca operacional ja implementados.
- `docs/modulo-cliente.md` ampliado com fluxos de auth/recuperacao de senha e modulo de faturamento.
- `docs/modulo-admin.md` ampliado com governanca de billing, filtros salvos e gestao de plano/recursos por usuario.
- `docs/banco-de-dados.md` atualizado com dominio de assinaturas/billing (`subscription_*`, `billing_*`, `user_feature_overrides`) e relacionamentos/enums correspondentes.
- `docs/arquitetura-mvcl.md` e `docs/instalacao-configuracao-operacao.md` sincronizados com pipeline/configs atuais (incluindo billing).
- `docs/seguranca-e-sanitizacao-producao.md` atualizado com resultado real da execucao local mais recente.

### Testes e Validacao

- `php tests/security/run-security-suite.php` executado em `2026-05-05`.
- Resultado: `FAIL` (8 `PASS`, 1 `FAIL`, 0 `WARN`).
- Falha atual registrada: heuristica de echo bruto em views do calendario (`annual`, `index`, `monthly`).

## 2026-04-28

### Configuracao e Deploy

- Padronizacao de ambiente com `APP_ENV` (`development`/`production`) no bootstrap.
- Instalador atualizado para receber ambiente e hosts permitidos e gravar `.env` automaticamente.
- Runtime de instalacao agora persiste `app.environment` e `security.allowed_hosts` em `system/Storage/config.php`.
- `index.php` passou a reconhecer instalacao ativa via `system/Storage/config.php`, reduzindo dependencia de sobrescrever `config.php` raiz em reinstalacoes.
- Merge de configuracao aprimorado para listas (`array_is_list`) com substituicao completa (evita mistura de `allowed_hosts` entre defaults e runtime).

## 2026-04-17

### Corrigido

- Fatal em `FeatureFlagService` ao resolver flags de estrategia `permission` sem necessidade de consultar hierarquia de grupos.
- Compatibilidade com esquema legado sem coluna `user_groups.hierarchy_level`:
  - validacao de existencia da coluna
  - fallback seguro para evitar `PDOException` em runtime

### Seguranca

- Bloqueio de acesso HTTP direto a arquivos e caminhos internos via `.htaccess`:
  - `system/`
  - `system/Storage/`
  - `config.php`
  - `.env` e `.env.example`
  - `install/sql/`
- Hardening contra Host Header Injection:
  - nova camada `HostGuard`
  - validacao por allowlist (`ALLOWED_HOSTS` / `security.allowed_hosts`)
  - resposta `400` para host nao permitido
- Padronizacao de geracao de URLs absolutas para usar host efetivo validado.

### Configuracao e Deploy

- Inclusao de `ALLOWED_HOSTS` em:
  - `config.php` (merge de ambiente)
  - `system/Config/app.php` (default)
  - `.env.example`
  - exemplos de deploy Apache/Nginx/PHP-FPM
  - guias de seguranca e operacao

### Testes e Validacao

- `php tests/security/run-security-suite.php`: `PASS` (9/9)
- Lint PHP completo: `PASS`
- Validacoes HTTP locais:
  - hosts permitidos (`localhost`, `127.0.0.1`, `nosfirsolis.example.com`) retornando `200`
  - host forjado fora da allowlist retornando `400`
