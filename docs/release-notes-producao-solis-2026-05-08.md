# Release Notes - Solis (Producao)

**Data:** 2026-05-08  
**Branch de consolidacao:** `feat/critical-flow-maturity-gate-clean`  
**Tipo de release:** hardening arquitetural, seguranca, qualidade e prontidao operacional.

## 1. Resumo executivo

Esta release consolida a fase de fechamento tecnico do Solis, com foco em:

1. eliminacao de falhas de composicao de servicos
2. reforco de isolamento de camadas (sem acesso direto a `$_SERVER` em library)
3. evolucao do fluxo de recuperacao em arquitetura modular por traits
4. institucionalizacao de benchmark e homologacao operacional final

Resultado consolidado de qualidade:

- `Quality Gates`: `PASS` (6/6)
- `Service Composition`: `PASS`
- `Security Suite`: `PASS`
- `Operational Security Audit`: `PASS`
- `Critical Flow Suite`: `PASS`

## 2. Mudancas principais da release

### 2.1 Arquitetura e composicao

1. Correção de instanciacao direta de `MailService` fora do `BaseController`.
2. Introducao de accessor dedicado e cache no controller base para service de mail.
3. Remocao de acesso direto a `$_SERVER` em `MailService` com resolucao via `request`/`Registry`.

### 2.2 Fluxo de recuperacao (auth)

Decomposicao do fluxo em traits por responsabilidade:

1. `AuthPasswordResetRequestTrait`
2. `AuthPasswordResetTokenTrait`
3. `AuthEmailRecoveryFlowTrait`
4. `AuthPasswordResetFlowTrait` como agregador
5. `AuthRequestMetadataTrait` para metadados de request

### 2.3 Qualidade e testes

1. Contratos de composicao atualizados para a nova arquitetura multi-trait.
2. Suite critica ajustada para validar o contrato de reset no novo arranjo sem perda de cobertura.
3. Benchmark reutilizavel versionado para regressao de autenticacao HTTP.

### 2.4 Operacao e producao

1. Checklist formal de fechamento para producao publicado.
2. Homologacao operacional final documentada com evidencias de build, smoke HTTP e auditorias.

## 3. Evidencias da release

### 3.1 Comandos de qualidade

1. `php tools/quality/run-quality-gates.php` -> `PASS`
2. `php tests/security/run-security-suite.php` -> `PASS`
3. `php tools/security/run-operational-audit.php` -> `PASS`
4. `php tests/critical/run-critical-flow-suite.php` -> `PASS`

### 3.2 Build

1. `composer --working-dir=system build` -> sucesso
2. `composer --working-dir=prod/system build` -> sucesso

### 3.3 Benchmark pos-refatoracao

Comando:

- `php -d xdebug.mode=off tools/performance/run-auth-http-benchmark.php`

Resumo:

1. `0` erros HTTP/cURL
2. p95 concorrente entre `43.69 ms` e `132.62 ms`
3. RPS concorrente entre `193.38` e `380.28`

## 4. Checklist de deploy (go-live)

1. Garantir `APP_ENV=production`.
2. Definir `TOKEN_CIPHER_KEY` forte e validar rotacao (`TOKEN_CIPHER_KEY_PREVIOUS`).
3. Configurar `ALLOWED_HOSTS` para dominios oficiais.
4. Configurar SMTP real (`MAIL_DRIVER=smtp`) com credenciais seguras.
5. Executar migracoes com `DB_MIGRATION_*` quando aplicavel.
6. Validar TLS, proxy e headers no ambiente destino.
7. Publicar pacote em `prod/` conforme runbook.

## 5. Plano de rollback

1. Reverter para o tag/commit estavel anterior no ambiente de deploy.
2. Restaurar build anterior de `prod/`.
3. Revalidar health checks (`/`, `/client/auth/login`, `/admin/auth/login`).
4. Reexecutar auditorias rapidas de seguranca e gates essenciais.

## 6. Decisao de release

**Status:** aprovado para fase final de deploy assistido em producao, condicionado ao preenchimento seguro das variaveis de ambiente reais e validacoes de infraestrutura no destino.
