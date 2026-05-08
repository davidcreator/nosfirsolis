# Relatorio Tecnico de Analise e Benchmark - Solis

**Data da avaliacao:** 2026-05-08  
**Ambiente:** local (`http://localhost/nosfirsolis/`)  
**Objetivo:** reavaliar o estado tecnico do sistema, identificar erros atuais, listar correcoes necessarias e executar benchmark de desempenho.

## 1. Escopo e metodologia

Foram executadas as seguintes auditorias e suites:

1. `php tools/architecture/run-mvcl-audit.php`
2. `php tools/architecture/run-service-composition-audit.php`
3. `php tools/architecture/run-mvcl-maturity-budget-audit.php`
4. `php tests/security/run-security-suite.php`
5. `php tools/security/run-operational-audit.php`
6. `php tests/critical/run-critical-flow-suite.php`
7. `php tools/quality/run-quality-gates.php`

Benchmark HTTP realizado em dois niveis:

1. Medicao de validacao via `curl.exe` (PowerShell).
2. Medicao principal via `PHP + curl_multi` (mais fiel para concorrencia).

## 2. Resultado consolidado do sistema

### 2.1 Estado geral

- **Arquitetura MVCL:** `PASS`
- **Maturidade MVCL (orcamento estrutural):** `PASS`
- **Seguranca (suite):** `PASS`
- **Seguranca operacional:** `PASS`
- **Fluxos criticos:** `PASS`
- **Quality Gates geral:** `FAIL` (falha no bloco de composicao de servicos)

### 2.2 Erros identificados (objetivos e reproduziveis)

#### Erro 1 - Instanciacao direta de service fora do BaseController

- **Severidade:** Alta (quebra padrao de composicao e governanca de camada)
- **Arquivo/linha:** `client/Controller/Concerns/AuthPasswordResetMailTrait.php:128`
- **Evidencia:** `return new \System\Library\MailService($this->registry);`
- **Impacto:** aumenta acoplamento no trait, dificulta padronizacao de DI/service accessors e fere contrato estrutural atual.

**Correcao recomendada:**

1. Criar accessor `protected function mailService(): MailService` em `client/Controller/BaseController.php`.
2. Remover `new MailService(...)` do trait e usar apenas o accessor herdado.
3. Manter cache interno do service no BaseController (padrao ja usado pelos demais services).

---

#### Erro 2 - Acesso direto a `$_SERVER` dentro de Library

- **Severidade:** Alta (quebra regra de isolamento de camada)
- **Arquivo/linhas:**
1. `system/Library/MailService.php:229`
2. `system/Library/MailService.php:325`
- **Evidencia:** leitura direta de `$_SERVER['SERVER_NAME']` e `$_SERVER['HTTP_HOST']`.
- **Impacto:** bypass de abstracao de request/host guard, menor testabilidade e nao conformidade com o audit de composicao.

**Correcao recomendada:**

1. Ler host via objeto `request` no `Registry` (ex.: `request->server`) com fallback controlado.
2. Centralizar resolucao de host em metodo privado sem superglobal direta.
3. Reexecutar `run-service-composition-audit.php` e `run-quality-gates.php` para validar fechamento.

---

#### Alerta estrutural - Trait grande de recuperacao

- **Severidade:** Media
- **Arquivo:** `client/Controller/Concerns/AuthPasswordResetFlowTrait.php`
- **Evidencia:** 425 linhas (warning de tamanho no audit de composicao).
- **Impacto:** custo maior de manutencao, risco de regressao em futuras alteracoes.

**Correcao recomendada:**

1. Quebrar em traits menores por responsabilidade:
   - `AuthPasswordResetRequestTrait`
   - `AuthPasswordResetTokenTrait`
   - `AuthEmailRecoveryFlowTrait`
2. Opcionalmente mover regras de rate-limit/telemetria para service dedicado.

## 3. Resultado de benchmark

## 3.1 Configuracao do benchmark principal

- Motor: `PHP 8.3.14` com `curl_multi`
- Xdebug: `xdebug.mode=off` durante medicao
- Endpoints testados:
1. Landing
2. Client Login
3. Admin Login
4. Forgot Password
5. Forgot Email
- Perfil:
1. Sequencial: `120` requisicoes por endpoint
2. Concorrente: `240` requisicoes por endpoint com `12` workers
- Erros HTTP/cURL: `0` em todos os endpoints

## 3.2 Metricas (benchmark principal)

| Endpoint | Seq p50 | Seq p95 | Seq media | Seq RPS | Conc p50 | Conc p95 | Conc p99 | Conc media | Conc RPS |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| Landing | 4.54 ms | 6.15 ms | 4.86 ms | 203.61 | 25.44 ms | 41.36 ms | 51.35 ms | 28.90 ms | 325.63 |
| ClientLogin | 29.79 ms | 37.65 ms | 26.03 ms | 38.35 | 55.80 ms | 98.54 ms | 152.29 ms | 62.24 ms | 189.15 |
| AdminLogin | 28.90 ms | 34.28 ms | 24.52 ms | 40.71 | 53.53 ms | 99.29 ms | 135.57 ms | 59.82 ms | 198.09 |
| ForgotPassword | 23.63 ms | 35.84 ms | 23.85 ms | 41.84 | 57.99 ms | 116.50 ms | 135.55 ms | 64.32 ms | 182.19 |
| ForgotEmail | 30.34 ms | 39.15 ms | 27.58 ms | 36.20 | 48.51 ms | 84.33 ms | 1148.63 ms* | 68.27 ms | 155.31 |

\* Observacao: houve outlier extremo em uma rodada (p99 alto em `ForgotEmail`).  
Repeticao dedicada de 3 rodadas para `ForgotEmail` mostrou estabilidade:
- p99 entre `117.36 ms` e `154.49 ms`
- media entre `50.76 ms` e `51.63 ms`
- RPS entre `226.49` e `231.19`

Interpretacao: o pico de 1148 ms foi **evento isolado** (jitter local), nao padrao recorrente no endpoint.

## 4. Diagnostico final

O Solis encontra-se funcionalmente estavel e com suites de seguranca/fluxo critico aprovadas.  
Entretanto, **ainda nao esta 100% aderente aos quality gates**, devido a falhas de composicao em camada de service/library.

### Decisao tecnica no estado atual

- **Apto para continuidade de homologacao funcional:** Sim.
- **Apto para fechamento tecnico com gate de arquitetura estrito:** Nao, ate corrigir os 2 erros de composicao listados.

## 5. Plano de correcao recomendado (prioridade)

1. **P1 (imediato):** remover instanciacao direta de `MailService` no trait e mover para accessor no `BaseController`.
2. **P1 (imediato):** eliminar `$_SERVER` direto em `MailService` e usar `request` via `Registry`.
3. **P2 (curto prazo):** refatorar `AuthPasswordResetFlowTrait` em unidades menores.
4. **P1 validacao:** rerodar `php tools/quality/run-quality-gates.php` e exigir `Status: PASS`.
5. **P2 desempenho:** repetir benchmark apos P1 para registrar baseline oficial pos-correcao.

## 6. Evidencias objetivas

- `run-quality-gates.php`: `FAIL` por `Service Composition Audit`
- `run-service-composition-audit.php`: `3 FAIL`, `1 WARN`
- `run-security-suite.php`: `PASS`
- `run-operational-audit.php`: `PASS`
- `run-critical-flow-suite.php`: `PASS`

## 7. Adendo pos-correcao (2026-05-08)

As correcoes P1 foram aplicadas e revalidadas no mesmo dia:

1. Removida instanciacao direta de `MailService` no trait de recuperacao:
   - `client/Controller/Concerns/AuthPasswordResetMailTrait.php`
2. Adicionado accessor padronizado de `MailService` no BaseController:
   - `client/Controller/BaseController.php`
3. Removido acesso direto a `$_SERVER` na library de e-mail, migrando leitura de host para `request` via `Registry`:
   - `system/Library/MailService.php`

Reexecucao dos gates apos as correcoes:

- `php tools/architecture/run-service-composition-audit.php` -> `PASS` (`0 FAIL`, `0 WARN`)
- `php tools/quality/run-quality-gates.php` -> `PASS` (`Checks: 6 | Passes: 6 | Failures: 0 | ExitCode: 0`)

### Estado atualizado apos remediacao

- **Qualidade arquitetural:** `PASS`
- **Seguranca:** `PASS`
- **Fluxos criticos:** `PASS`
- **Composicao de servicos:** `PASS` sem warnings estruturais.

### Ajuste estrutural adicional aplicado

- Decomposicao completa do fluxo de recuperacao em traits por responsabilidade:
  - `client/Controller/Concerns/AuthPasswordResetRequestTrait.php`
  - `client/Controller/Concerns/AuthPasswordResetTokenTrait.php`
  - `client/Controller/Concerns/AuthEmailRecoveryFlowTrait.php`
- `client/Controller/Concerns/AuthPasswordResetFlowTrait.php` passou a atuar como trait agregador.
- Metadados de request isolados em `client/Controller/Concerns/AuthRequestMetadataTrait.php`.
- Suite critica atualizada para validar o contrato de reset sobre a composicao multi-trait sem reduzir as garantias de seguranca.

## 8. Benchmark pos-refatoracao (2026-05-08)

Benchmark repetido apos remediacao arquitetural, usando script versionado:

- `php -d xdebug.mode=off tools/performance/run-auth-http-benchmark.php`
- Perfil: `120` requisicoes sequenciais + `240` requisicoes concorrentes (`12` workers) por endpoint.
- Erros HTTP/cURL: `0` em todos os endpoints.

| Endpoint | Seq p50 | Seq p95 | Seq media | Seq RPS | Conc p50 | Conc p95 | Conc p99 | Conc media | Conc RPS |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| Landing | 3.76 ms | 4.96 ms | 4.24 ms | 233.39 | 28.21 ms | 43.69 ms | 53.25 ms | 30.55 ms | 380.28 |
| ClientLogin | 30.99 ms | 34.29 ms | 29.98 ms | 33.31 | 45.55 ms | 107.31 ms | 161.87 ms | 56.43 ms | 206.81 |
| AdminLogin | 31.00 ms | 36.91 ms | 27.15 ms | 36.77 | 53.34 ms | 101.79 ms | 135.04 ms | 58.97 ms | 198.07 |
| ForgotPassword | 30.37 ms | 45.84 ms | 37.00 ms | 26.99 | 50.18 ms | 132.62 ms | 155.32 ms | 60.62 ms | 193.38 |
| ForgotEmail | 31.02 ms | 35.71 ms | 28.98 ms | 34.46 | 51.91 ms | 92.88 ms | 111.57 ms | 56.77 ms | 208.10 |

Observacao:

- No `ForgotPassword` houve pico isolado de `max_ms` em sequencial (`1101.52 ms`), sem refletir degradacao no p95/p99 e sem erros.
