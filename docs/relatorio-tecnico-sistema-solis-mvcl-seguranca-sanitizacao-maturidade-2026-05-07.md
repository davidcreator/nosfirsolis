# Relatório Técnico do Sistema Solis
## Arquitetura, Conformidade MVCL, Segurança, Sanitização e Maturidade

- Data da análise: 07/05/2026
- Escopo técnico: base de código do sistema Solis no repositório local
- Ambiente observado: estrutura PHP 8.1+, arquitetura por áreas (`admin`, `client`, `install`)

## 1. Resumo Executivo

O sistema Solis apresenta base arquitetural consistente com MVCL por áreas, com boa separação de rotas, controladores, modelos e views, além de controles de segurança importantes já ativos (CSRF, sessão endurecida, controle de permissões, criptografia autenticada para tokens e validação de host em runtime dos módulos principais).

Entretanto, há pontos estruturais que reduzem a maturidade global:

1. Desvios de MVCL em controladores específicos com lógica de persistência e DDL em tempo de execução.
2. Estratégia de segurança com comportamento *fail-open* em cenários de falha de banco em componentes críticos.
3. Falta de camada central de validação/sanitização de entrada (a validação é majoritariamente distribuída por controller).
4. Maturidade de qualidade ainda limitada por baixa cobertura de testes automatizados (há suíte de segurança, porém escopo restrito).

Classificação consolidada de maturidade técnica atual: **Intermediária (Nível 3 de 5)**.

## 2. Escopo e Método

Esta análise foi realizada por inspeção estática e validações locais no código-fonte, com foco em:

1. Arquitetura e aderência MVCL.
2. Segurança de aplicação e superfície de ataque.
3. Sanitização de entrada/saída.
4. Indicadores de maturidade técnica e operacional.

Execução de validação automatizada:

- `php tests/security/run-security-suite.php`
- Resultado: **PASS (12/12)**, sem falhas.

Observação de escopo: este relatório cobre o sistema na camada de aplicação/código; não substitui pentest de infraestrutura, SAST/DAST completos ou revisão de configurações externas de servidor/rede.

## 3. Diagnóstico de Arquitetura e Estrutura

### 3.1 Organização macro

A estrutura principal está adequada ao padrão por áreas:

- `admin/Controller`, `admin/Model`, `admin/View`
- `client/Controller`, `client/Model`, `client/View`
- `install/Controller`, `install/Model`, `install/View`
- núcleo em `system/Engine` e serviços em `system/Library`

Contagem observada:

- Controllers: admin `12`, client `9`, install `1`
- Models: admin `19`, client `4`
- Núcleo: `system/Engine` com `13` classes PHP

### 3.2 Fluxo arquitetural

O fluxo principal está bem definido:

1. Entrada por front-controller de área (`admin/index.php`, `client/index.php`, `install/index.php`).
2. Bootstrap em `system/Engine/Application.php`.
3. Resolução de rota e despacho em `system/Engine/Router.php`.
4. Renderização de templates/layout em `system/Engine/View.php`.

Pontos positivos:

- Resolução dinâmica de controller/action com verificação de método público por reflection.
- Injeção central de serviços no `Registry`.
- Separação entre núcleo de framework (`Engine`) e serviços de domínio (`Library`).

## 4. Conformidade com Padrão MVCL

### 4.1 Aderência observada

A base segue MVCL de forma **predominante**:

1. `Loader` resolve models por área.
2. Controllers base concentram autenticação/permissão e render.
3. Views operam majoritariamente com saída escapada via helper `e()`.
4. Serviços de domínio estão em `system/Library`.

### 4.2 Desvios relevantes

Foram identificados desvios importantes de camada:

1. `client/Controller/AuthController.php` com forte acoplamento a SQL/transações e DDL (`CREATE TABLE IF NOT EXISTS password_resets`).
2. `admin/Controller/UsersController.php` com acesso direto a banco no controller (`$this->db->fetch(...)`).
3. DDL em runtime distribuída em múltiplos serviços/models (ex.: `SecurityService`, `SubscriptionService`, `PlannerModel`, `SocialModel`, `FeatureFlagService`, `ObservabilityService`, `JobMonitorService`, `AutomationService`).

Conclusão de aderência MVCL:

- **Parcialmente aderente**, com boa base estrutural, porém com débitos de separação de responsabilidades em pontos críticos.

## 5. Segurança da Aplicação

### 5.1 Controles implementados (pontos fortes)

1. **CSRF**: helpers `csrf_token`, `csrf_field`, `verify_csrf` e uso consistente em formulários POST.
2. **Sessão**: `use_strict_mode`, `httponly`, `use_only_cookies`, `SameSite=Lax`, `secure` condicionado a HTTPS, rotação de ID de sessão.
3. **Autenticação/autorização**: `password_verify`, checagem de permissões por área e auditoria de eventos sensíveis.
4. **Integridade de sessão**: fingerprint de sessão + TTL configurável.
5. **Criptografia de tokens**: `TokenCipher` com `AES-256-GCM` (payload `v2`) e compatibilidade legada com rotação de chave.
6. **Proteção de host**: `HostGuard` aplicado no bootstrap da aplicação por área.
7. **Suíte local de segurança**: validações automatizadas específicas do projeto com resultado atual `PASS`.

### 5.2 Riscos e fragilidades

#### Risco 1: comportamento fail-open em trilhas críticas de segurança

Em `SecurityService`, falhas de banco/exceções retornam autorização para tentativa de login (`allowed => true`) e silenciam falhas de persistência de auditoria.

Impacto:

- Redução de efetividade de bloqueio de brute force em cenários degradados.
- Perda de rastreabilidade em incidentes.

Classificação: **Alto**.

#### Risco 2: DDL em tempo de execução espalhada

Criação/alteração de schema em runtime aparece em vários componentes, inclusive autenticação/segurança.

Impacto:

- Comportamento imprevisível entre ambientes.
- Risco operacional em produção sob carga.
- Acoplamento forte entre código de negócio e evolução de banco.

Classificação: **Alto** (arquitetural/operacional).

#### Risco 3: composição de host no `index.php` raiz fora do fluxo de HostGuard

No `index.php` raiz, a construção de URLs/meta usa `HTTP_HOST` diretamente para landing/redirect inicial, sem usar a mesma normalização efetiva do `Application::runtimeHost`.

Impacto:

- Inconsistência de política de host entre entrypoints.
- Superfície para problemas de host header handling no front root.

Classificação: **Médio**.

#### Risco 4: cabeçalhos HTTP de segurança parciais

Existem `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, porém sem CSP e sem HSTS no `.htaccess`.

Impacto:

- Menor proteção contra XSS residual e downgrade de transporte.

Classificação: **Médio**.

#### Risco 5: validação de entrada não centralizada

A validação/sanitização de entrada existe, mas distribuída e heterogênea entre controllers/models.

Impacto:

- Variação de qualidade de validação por módulo.
- Maior risco de regressão em novos endpoints.

Classificação: **Médio**.

## 6. Sanitização e Tratamento de Dados

### 6.1 Saída (output encoding)

Há base correta de escaping em views via helper `e()` (`htmlspecialchars` com `ENT_QUOTES` e UTF-8).

Métrica amostral nas views:

- `total_short_echo=1201`
- `escaped_e=950`
- `csrf_field=66`
- `unescaped_estimated=251`

Leitura técnica da métrica:

1. Boa taxa de escaping explícito.
2. Parte dos `echo` não escapados é intencional/aceita (ex.: `<?= $content ?? '' ?>`, casts numéricos, atributos condicionais, fragmentos HTML controlados).
3. Ainda há necessidade de padronização para reduzir ambiguidade e risco em manutenção futura.

### 6.2 Entrada (input validation)

Há validações específicas úteis (ex.: `filter_var`, `ctype_digit`, regex de token, saneamento de rota, saneamento de cor hex), porém sem uma camada unificada de DTO/validator.

Conclusão de sanitização:

- **Nível intermediário**, com práticas corretas importantes, mas sem padronização central de validação de entrada.

## 7. Indicadores de Maturidade Técnica

### 7.1 Complexidade e concentração de lógica

Arquivos com alto volume indicam concentração de responsabilidades:

1. `system/Library/SubscriptionService.php` (~2037 linhas).
2. `client/Controller/SocialController.php` (~774 linhas).
3. `admin/Controller/UsersController.php` (~535 linhas).
4. `client/Controller/AuthController.php` (~536 linhas).

Leitura: o sistema evoluiu funcionalmente, porém com concentração que aumenta custo de mudança e risco de regressão.

### 7.2 Testes e governança de qualidade

Situação atual observada:

1. Existe suíte de segurança automatizada útil e funcional.
2. Cobertura de testes automatizados ainda é baixa em escopo geral (`tests/security`).
3. Não foi identificada esteira CI versionada no repositório para qualidade contínua.

Leitura: maturidade de qualidade ainda inicial/intermediária.

### 7.3 Classificação por dimensão (1 a 5)

1. Arquitetura e modularidade: **3.2/5**
2. Conformidade MVCL: **3.0/5**
3. Segurança de aplicação: **3.6/5**
4. Sanitização e validação: **3.1/5**
5. Testabilidade e governança: **2.2/5**
6. Operabilidade e observabilidade: **3.3/5**

Média consolidada: **3.1/5 (Intermediária)**.

## 8. Plano de Evolução Recomendado (Prioritário)

### Fase 1 (0 a 15 dias) - Correções críticas

1. Eliminar *fail-open* em `SecurityService` para políticas de bloqueio de login e trilha de auditoria.
2. Unificar tratamento de host no `index.php` raiz usando a mesma estratégia de host efetivo/permitido do núcleo.
3. Adicionar CSP base e HSTS (com rollout gradual) na camada web.
4. Documentar baseline mínimo de produção para `allowed_hosts`, `trusted_proxies` e chaves de criptografia.

### Fase 2 (15 a 45 dias) - Aderência arquitetural

1. Extrair persistência e DDL de `client/AuthController` para Model/Service dedicado.
2. Remover acesso direto a DB de `admin/UsersController` para camada de model/service.
3. Definir política única de migração de banco (migrations versionadas) e remover DDL em runtime de fluxo de requisição.

### Fase 3 (45 a 90 dias) - Maturidade e escala

1. Introduzir camada central de validação de entrada por caso de uso (DTO/validator).
2. Expandir testes automatizados para autenticação, permissões, fluxo de billing e social.
3. Instituir pipeline CI com gates mínimos (lint, teste, suíte de segurança).
4. Reduzir classes com alta concentração funcional por módulos menores e contratos explícitos.

## 9. Conclusão

O Solis possui uma base técnica sólida para evoluir com segurança, com arquitetura por áreas bem definida e controles relevantes já implementados. O principal risco atual não é ausência de funcionalidades, mas sim **acoplamento estrutural em pontos sensíveis** e **inconsistências de hardening entre entrypoints**.

Com as correções priorizadas neste relatório, o sistema tende a migrar de maturidade **Intermediária (3.1/5)** para **Intermediária-Avançada (3.8+/5)** em um ciclo de 60 a 90 dias, com redução relevante de risco operacional e de segurança.

---

## Anexo A - Evidências técnicas principais (arquivos-chave auditados)

1. `system/Engine/Application.php`
2. `system/Engine/Router.php`
3. `system/Engine/Loader.php`
4. `system/Engine/Session.php`
5. `system/Helper/common.php`
6. `system/Library/Auth.php`
7. `system/Library/SecurityService.php`
8. `system/Library/HostGuard.php`
9. `system/Library/TokenCipher.php`
10. `client/Controller/AuthController.php`
11. `admin/Controller/UsersController.php`
12. `system/Library/SubscriptionService.php`
13. `index.php`
14. `.htaccess`
15. `tests/security/run-security-suite.php`
