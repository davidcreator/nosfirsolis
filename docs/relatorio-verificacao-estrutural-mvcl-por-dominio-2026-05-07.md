# Relatorio Formal de Verificacao Estrutural MVCL por Dominio

Data de referencia: 2026-05-07  
Sistema: Solis  
Escopo: validacao de arquitetura, estrutura de camadas, aderencia MVCL, seguranca estrutural e maturidade tecnica.

## 1. Metodologia de auditoria

Foram executadas validacoes automaticas e verificacoes manuais objetivas:

1. Auditoria MVCL: `php tools/architecture/run-mvcl-audit.php`
2. Suite de seguranca: `php tests/security/run-security-suite.php`
3. Auditoria operacional: `php tools/security/run-operational-audit.php`
4. Varredura de anti-padroes estruturais:
   - SQL direto em controllers
   - acoplamento de apresentacao em models
   - DDL runtime no caminho de requisicao
   - densidade de instanciacao direta de services em controllers
   - tamanho e coesao de classes/traits

## 2. Resultado executivo

- Status MVCL: **PASS** (10/0/0)
- Status seguranca aplicacao: **PASS** (24/0/0)
- Status seguranca operacional: **PASS** (17/0/0)

Conclusao executiva: o sistema esta **estruturalmente aderente ao padrao MVCL**, com pontos de evolucao para aumentar coesao e reduzir acoplamento em componentes extensos.

## 3. Inventario estrutural por dominio

### 3.1 Estrutura por area

- `admin`: 12 controllers PHP, 19 models PHP, 17 views PHP, 300 views Twig
- `client`: 13 controllers PHP, 5 models PHP, 17 views PHP
- `install`: 1 controller PHP, 1 model PHP, 3 views PHP

### 3.2 Achados de conformidade MVCL

1. Controllers sem SQL direto relevante: `controller_sql_direct_hits=0`
2. Models sem acoplamento de apresentacao/response: `model_presentation_coupling_hits=0`
3. DDL runtime removido do request path em `system|client|admin`: `runtime_ddl_hits_php=0`

## 4. Achados tecnicos (priorizados)

### 4.1 Prioridade media - componentes extensos (risco de coesao)

Bibliotecas e traits com alta densidade funcional:

- `system/Library/SubscriptionService.php` (1140 linhas)
- `system/Library/SubscriptionServiceOperationsTrait.php` (1135 linhas)
- `system/Library/SocialPublishingService.php` (680 linhas)
- `system/Library/AutomationService.php` (531 linhas)
- `system/Library/FeatureFlagService.php` (484 linhas)
- `system/Library/CampaignTrackingService.php` (471 linhas)
- `system/Library/JobMonitorService.php` (463 linhas)
- `system/Library/SecurityService.php` (460 linhas)
- `client/Model/PlannerModel.php` (788 linhas)
- `client/Controller/Concerns/SocialConnectionFlowTrait.php` (491 linhas)

Impacto: aumento de custo de manutencao, maior risco de regressao por mudanca e baixa testabilidade unitaria.

### 4.2 Prioridade media - acoplamento por instanciacao direta de services

Arquivos com maior volume de `new ...Service(...)`:

- `admin/Controller/OperationsController.php` (14)
- `client/Controller/Concerns/SocialPublishingActionsTrait.php` (12)
- `client/Controller/TrackingController.php` (10)
- `admin/Controller/BillingController.php` (9)
- `client/Controller/PlansController.php` (5)
- `client/Controller/Concerns/SocialConnectionFlowTrait.php` (5)

Impacto: acoplamento elevado em controller/trait, menor reuso e dificuldade para testes isolados.

### 4.3 Prioridade baixa - uso de superglobal em pontos de infraestrutura

Uso identificado de `$_SERVER` em pontos de contexto HTTP/host/IP:

- `install/Model/InstallerModel.php`
- `system/Library/Auth.php`
- `system/Library/CampaignTrackingService.php`
- `system/Library/SecurityService.php`

Classificacao: aceitavel por natureza tecnica (ambiente/request), sem indicio de violacao MVCL critica.

## 5. Sanitizacao e seguranca de saida (views)

Indicadores em views PHP:

- `views_php_files=37`
- `short_echo_total=1407`
- `escaped_e_total=1039`
- `raw_var_echo_total=128`
- `csrf_field_total=66`

Leitura tecnica:

- predominio de saida escapada;
- ha pontos de saida direta (`<?= $... ?>`) que merecem revisao dirigida por risco, com foco inicial em:
  - `admin/View/users/index.php`
  - `client/View/billing/index.php`
  - `admin/View/suggestions/form.php`
  - `admin/View/holidays/form.php`

## 6. Maturidade tecnica atual (escala 1-5)

- Arquitetura e separacao de camadas: **4.4/5**
- Aderencia MVCL operacional: **4.5/5**
- Coesao de componentes: **3.7/5**
- Acoplamento entre camadas: **3.8/5**
- Testabilidade automatizada: **2.6/5**

Media consolidada: **3.8/5 (intermediaria-avancada)**.

## 7. Plano recomendado para estrutura MVCL solida

1. Fatiar `SubscriptionServiceOperationsTrait` em services de caso de uso (`PlanCatalogService`, `BillingSettingsService`, `PromotionService`, `PaymentValidationService`).
2. Reduzir instanciacao direta de services em controllers com factories de dominio no `BaseController` (ou provider no Registry).
3. Quebrar `PlannerModel` em sub-modelos por contexto (agenda base, eventos extras, cores, publicacao).
4. Criar auditoria automatizada adicional para tamanho de trait e densidade de dependencias por controller.
5. Expandir a trilha de testes alem do escopo de seguranca (contratos de service e smoke tests por fluxo principal).

## 8. Execucao da fase 1 (desacoplamento aplicado)

A fase 1 foi executada com centralizacao de factories/cache de services nos controladores base:

- `admin/Controller/BaseController.php`
- `client/Controller/BaseController.php`

Controllers refatorados para eliminar instanciacao direta de services no fluxo:

- `admin/Controller/OperationsController.php`
- `admin/Controller/BillingController.php`
- `client/Controller/TrackingController.php`
- `client/Controller/BillingController.php`

Evidencia objetiva apos a refatoracao:

- `admin/Controller/OperationsController.php`: `new_service_calls=0`
- `admin/Controller/BillingController.php`: `new_service_calls=0`
- `client/Controller/TrackingController.php`: `new_service_calls=0`
- `client/Controller/BillingController.php`: `new_service_calls=0`

Validacao tecnica apos a fase 1:

- `php tools/architecture/run-mvcl-audit.php` -> `PASS` (10/0/0)
- `php tests/security/run-security-suite.php` -> `PASS` (24/0/0)
- `php tools/security/run-operational-audit.php` -> `PASS` (17/0/0)

## 9. Execucao da fase 2 (decomposicao do modulo de assinaturas)

Foi executada decomposicao estrutural do modulo de assinaturas em traits coesos por dominio, mantendo assinatura publica do `SubscriptionService`:

- `system/Library/SubscriptionServiceOperationsTrait.php` (agregador)
- `system/Library/SubscriptionServiceSchemaAndPlansTrait.php`
- `system/Library/SubscriptionServiceBillingOperationsTrait.php`
- `system/Library/SubscriptionServiceEntitlementAndCheckoutTrait.php`

Resultado da fase 2:

- reducao do monolito operacional em um unico trait de 1135 linhas;
- segmentacao por responsabilidade (schema/planos, billing administrativo, entitlements+checkout);
- preservacao comportamental com regressao zero nas auditorias executadas.

Validacao tecnica apos a fase 2:

- `php -l` em todos os traits e `SubscriptionService`: sem erros
- `php tools/architecture/run-mvcl-audit.php` -> `PASS` (10/0/0)
- `php tests/security/run-security-suite.php` -> `PASS` (24/0/0)
- `php tools/security/run-operational-audit.php` -> `PASS` (17/0/0)

## 10. Execucao da fase 3 (decomposicao interna do SubscriptionService)

Foi executada nova decomposicao estrutural para reduzir acoplamento interno da classe principal de assinaturas.

Arquivos introduzidos para coesao por responsabilidade:

- `system/Library/SubscriptionServiceContextTrait.php`
- `system/Library/SubscriptionServiceBillingInternalsTrait.php`
- `system/Library/SubscriptionServicePlanPersistenceTrait.php`

Classe principal convertida em agregador/orquestrador:

- `system/Library/SubscriptionService.php`

Evidencia objetiva da fase 3:

- `SubscriptionService.php`: **42 linhas** (antes: 1140)
- `SubscriptionServiceContextTrait.php`: 374 linhas
- `SubscriptionServiceBillingInternalsTrait.php`: 358 linhas
- `SubscriptionServicePlanPersistenceTrait.php`: 386 linhas

Resultado tecnico:

- reducao expressiva do monolito de service;
- preservacao de API publica do modulo;
- isolamento de responsabilidades internas (contexto, faturamento interno e persistencia/plano);
- melhoria de legibilidade, manutenibilidade e testabilidade por unidade.

Validacao tecnica apos a fase 3:

- `php -l` em `SubscriptionService` e novos traits: sem erros
- `php tools/architecture/run-mvcl-audit.php` -> `PASS` (10/0/0)
- `php tests/security/run-security-suite.php` -> `PASS` (24/0/0)
- `php tools/security/run-operational-audit.php` -> `PASS` (17/0/0)

## 11. Execucao da fase 4 (decomposicao de controllers criticos)

Foi executada decomposicao adicional dos controladores com maior densidade funcional e de fluxo, mantendo os mesmos endpoints e regras de negocio:

- `admin/Controller/UsersController.php`
- `client/Controller/AuthController.php`

Novos concerns introduzidos:

- `admin/Controller/Concerns/UsersControllerFiltersTrait.php`
- `admin/Controller/Concerns/UsersControllerSubscriptionTrait.php`
- `client/Controller/Concerns/AuthPasswordResetFlowTrait.php`

Evidencia objetiva da fase 4:

- `admin/Controller/UsersController.php`: **226 linhas** (antes: 475)
- `client/Controller/AuthController.php`: **218 linhas** (antes: 448)
- substituicao de instanciacao direta de `SubscriptionService` nesses controladores por factory/cache de `BaseController` (`subscriptionService()`).

Resultado tecnico:

- maior coesao por dominio (filtros/lista, gestao de assinatura e fluxo de reset de senha);
- menor acoplamento do controller principal;
- melhor legibilidade e preparacao para testes direcionados por concern.

Validacao tecnica apos a fase 4:

- `php -l` em controladores e novos traits: sem erros
- `php tools/architecture/run-mvcl-audit.php` -> `PASS` (10/0/0)
- `php tests/security/run-security-suite.php` -> `PASS` (24/0/0)
- `php tools/security/run-operational-audit.php` -> `PASS` (17/0/0)

## 12. Execucao da fase 5 (decomposicao do PlannerModel)

Foi executada decomposicao estrutural do modelo de planejamento para aumentar coesao por contexto funcional, sem alterar assinatura publica do modelo:

- `client/Model/PlannerModel.php` (agregador)
- `client/Model/PlannerModelPlanLifecycleTrait.php`
- `client/Model/PlannerModelStatusAutomationTrait.php`
- `client/Model/PlannerModelCalendarTrait.php`

Evidencia objetiva da fase 5:

- `client/Model/PlannerModel.php`: **15 linhas** (antes: 788)
- `client/Model/PlannerModelPlanLifecycleTrait.php`: 150 linhas
- `client/Model/PlannerModelStatusAutomationTrait.php`: 353 linhas
- `client/Model/PlannerModelCalendarTrait.php`: 294 linhas

Resultado tecnico:

- separacao por dominio (ciclo de plano, status/automacao e calendario/cores/notas);
- reducao do acoplamento interno do model principal;
- aumento de legibilidade e prontidao para testes por unidade de responsabilidade.

Validacao tecnica apos a fase 5:

- `php -l` em `PlannerModel` e novos traits: sem erros
- `php tools/architecture/run-mvcl-audit.php` -> `PASS` (10/0/0)
- `php tests/security/run-security-suite.php` -> `PASS` (24/0/0)
- `php tools/security/run-operational-audit.php` -> `PASS` (17/0/0)

## 13. Execucao da fase 6 (decomposicao de SocialModel e CalendarModel)

Foi executada decomposicao estrutural adicional em modelos de dominio client para elevar coesao e reduzir acoplamento interno, mantendo contratos publicos:

- `client/Model/SocialModel.php` (agregador)
- `client/Model/SocialModelConnectionsTrait.php`
- `client/Model/SocialModelDraftsAndPresetsTrait.php`
- `client/Model/SocialModelSchemaTrait.php`
- `client/Model/CalendarModel.php` (agregador)
- `client/Model/CalendarModelEventsTrait.php`
- `client/Model/CalendarModelBaseEventsTrait.php`

Evidencia objetiva da fase 6:

- `client/Model/SocialModel.php`: **15 linhas** (antes: 287)
- `client/Model/SocialModelConnectionsTrait.php`: 89 linhas
- `client/Model/SocialModelDraftsAndPresetsTrait.php`: 155 linhas
- `client/Model/SocialModelSchemaTrait.php`: 51 linhas
- `client/Model/CalendarModel.php`: **11 linhas** (antes: 276)
- `client/Model/CalendarModelEventsTrait.php`: 172 linhas
- `client/Model/CalendarModelBaseEventsTrait.php`: 109 linhas

Resultado tecnico:

- separacao clara por responsabilidade em social (conexoes, conteudo/presets e schema);
- separacao clara por responsabilidade em calendario (agregacao de eventos e catalogo/base events);
- aumento de legibilidade e melhor base para testes unitarios por fatia funcional.

Validacao tecnica apos a fase 6:

- `php -l` em models e novos traits: sem erros
- `php tools/architecture/run-mvcl-audit.php` -> `PASS` (10/0/0)
- `php tests/security/run-security-suite.php` -> `PASS` (24/0/0)
- `php tools/security/run-operational-audit.php` -> `PASS` (17/0/0)

## 14. Execucao da fase 7 (decomposicao de servicos de publicacao e automacao)

Foi executada decomposicao estrutural em servicos centrais de integracao para reduzir monolitos e separar responsabilidades, mantendo contratos publicos:

- `system/Library/SocialPublishingService.php` (agregador)
- `system/Library/SocialPublishingSchemaTrait.php`
- `system/Library/SocialPublishingQueueTrait.php`
- `system/Library/SocialPublishingDeliveryTrait.php`
- `system/Library/AutomationService.php` (agregador)
- `system/Library/AutomationServiceSchemaAndValidationTrait.php`
- `system/Library/AutomationServiceWebhookCrudTrait.php`
- `system/Library/AutomationServiceDispatchTrait.php`

Evidencia objetiva da fase 7:

- `system/Library/SocialPublishingService.php`: **31 linhas** (antes: 680)
- `system/Library/SocialPublishingSchemaTrait.php`: 41 linhas
- `system/Library/SocialPublishingQueueTrait.php`: 289 linhas
- `system/Library/SocialPublishingDeliveryTrait.php`: 341 linhas
- `system/Library/AutomationService.php`: **31 linhas** (antes: 531)
- `system/Library/AutomationServiceSchemaAndValidationTrait.php`: 107 linhas
- `system/Library/AutomationServiceWebhookCrudTrait.php`: 147 linhas
- `system/Library/AutomationServiceDispatchTrait.php`: 268 linhas

Resultado tecnico:

- isolamento por dominio de responsabilidade (schema/validacao, CRUD, dispatch/rede, fila/publicacao);
- reducao forte de acoplamento interno das classes principais;
- melhor rastreabilidade e testabilidade por unidade de comportamento.

Validacao tecnica apos a fase 7:

- `php -l` em servicos e novos traits: sem erros
- `php tools/architecture/run-mvcl-audit.php` -> `PASS` (10/0/0)
- `php tests/security/run-security-suite.php` -> `PASS` (24/0/0)
- `php tools/security/run-operational-audit.php` -> `PASS` (17/0/0)

## 15. Execucao da fase 8 (decomposicao de services de governanca, tracking, jobs e seguranca)

Foi executada decomposicao estrutural adicional em services transversais de plataforma, mantendo contratos publicos:

- `system/Library/FeatureFlagService.php` (agregador)
- `system/Library/FeatureFlagSchemaTrait.php`
- `system/Library/FeatureFlagCrudTrait.php`
- `system/Library/FeatureFlagResolutionTrait.php`
- `system/Library/CampaignTrackingService.php` (agregador)
- `system/Library/CampaignTrackingSchemaTrait.php`
- `system/Library/CampaignTrackingOperationsTrait.php`
- `system/Library/JobMonitorService.php` (agregador)
- `system/Library/JobMonitorSchemaTrait.php`
- `system/Library/JobMonitorOperationsTrait.php`
- `system/Library/SecurityService.php` (agregador)
- `system/Library/SecurityRuntimeTrait.php`
- `system/Library/SecurityAuthAuditTrait.php`

Evidencia objetiva da fase 8:

- `system/Library/FeatureFlagService.php`: **33 linhas** (antes: 484)
- `system/Library/FeatureFlagSchemaTrait.php`: 140 linhas
- `system/Library/FeatureFlagCrudTrait.php`: 113 linhas
- `system/Library/FeatureFlagResolutionTrait.php`: 220 linhas
- `system/Library/CampaignTrackingService.php`: **30 linhas** (antes: 471)
- `system/Library/CampaignTrackingSchemaTrait.php`: 33 linhas
- `system/Library/CampaignTrackingOperationsTrait.php`: 423 linhas
- `system/Library/JobMonitorService.php`: **24 linhas** (antes: 463)
- `system/Library/JobMonitorSchemaTrait.php`: 77 linhas
- `system/Library/JobMonitorOperationsTrait.php`: 376 linhas
- `system/Library/SecurityService.php`: **18 linhas** (antes: 460)
- `system/Library/SecurityRuntimeTrait.php`: 187 linhas
- `system/Library/SecurityAuthAuditTrait.php`: 270 linhas

Resultado tecnico:

- isolamento por dominio de responsabilidade em governanca de flags, tracking, monitoramento e seguranca;
- reducao expressiva do acoplamento interno nas classes principais;
- melhoria de legibilidade, manutencao e testabilidade por fatias funcionais.

Validacao tecnica apos a fase 8:

- `php -l` em services e novos traits: sem erros
- `php tools/architecture/run-mvcl-audit.php` -> `PASS` (10/0/0)
- `php tests/security/run-security-suite.php` -> `PASS` (24/0/0)
- `php tools/security/run-operational-audit.php` -> `PASS` (17/0/0)

## 16. Conclusao formal

A estrutura atual do Solis esta em conformidade com MVCL para operacao e seguranca, sem nao conformidades criticas na separacao de camadas.

Os desvios remanescentes sao de maturidade arquitetural em componentes ainda extensos fora do modulo de assinaturas, nao de quebra estrutural MVCL. O sistema permanece apto para evolucao controlada, com caminho tecnico claro para as proximas iteracoes.

## 17. Execucao da fase 9 (verificacao estrutural completa e endurecimento MVCL)

Foi executada uma fase complementar de endurecimento arquitetural com foco em dois objetivos:

1. eliminar acoplamento remanescente por instanciacao direta de services baseados em `Registry` em controllers/concerns;
2. introduzir auditoria automatizada especifica para contratos de composicao (agregador + traits), tamanho estrutural e proibicao de DDL runtime no request path.

### 17.1 Refatoracao de acoplamento em controllers/concerns

Refatoracoes aplicadas:

- `client/Controller/BaseController.php`: inclusao de factory/cache para `socialPublishingService()`.
- `client/Controller/SocialController.php`: substituicao de `new SocialPublishingService(...)` e `new CampaignTrackingService(...)` por accessors do `BaseController`.
- `client/Controller/PlansController.php`: substituicao de `new SubscriptionService(...)` por `subscriptionService()`.
- `client/Controller/CalendarController.php`: substituicao de `new SubscriptionService(...)` por `subscriptionService()`.
- `client/Controller/Concerns/SocialPublishingActionsTrait.php`: substituicao de instanciacoes diretas de `SubscriptionService`, `SocialPublishingService`, `JobMonitorService`, `ObservabilityService` e `AutomationService` por accessors centralizados.
- `client/Controller/Concerns/SocialContentActionsTrait.php`: substituicao de instanciacoes diretas de `SubscriptionService` por accessor centralizado.
- `client/Controller/Concerns/SocialConnectionFlowTrait.php`: substituicao de instanciacoes diretas de `SubscriptionService` por accessor centralizado.

Evidencia objetiva apos a fase 9:

- busca por `new ...Service($this->registry)` em `admin|client|install/Controller` retorna ocorrencias apenas em:
  - `admin/Controller/BaseController.php`
  - `client/Controller/BaseController.php`

Interpretacao tecnica: a criacao de services de infraestrutura com `Registry` passou a ficar concentrada na camada de orquestracao base, reduzindo acoplamento distribuido e aumentando consistencia arquitetural.

### 17.2 Nova auditoria de composicao estrutural

Script criado:

- `tools/architecture/run-service-composition-audit.php`

Escopo tecnico coberto pelo novo auditor:

1. validacao de contratos de composicao para agregadores e traits por dominio;
2. validacao de namespace e declaracao de symbol (`class`/`trait`) nos arquivos contratados;
3. validacao de instanciacao direta de services com `Registry` fora de `BaseController`;
4. validacao de ausencia de DDL runtime em `controllers/models/libraries`;
5. monitoramento de tamanho de agregadores e traits por limiares de governanca.

Resultado:

- `php tools/architecture/run-service-composition-audit.php` -> `PASS_WITH_WARNINGS` (17/1/0)

Alertas emitidos (sem nao conformidade critica):

- `system/Library/SubscriptionServiceBillingOperationsTrait.php` (527 linhas)
- `system/Library/SubscriptionServiceEntitlementAndCheckoutTrait.php` (424 linhas)
- `system/Library/CampaignTrackingOperationsTrait.php` (423 linhas)
- `client/Controller/Concerns/SocialConnectionFlowTrait.php` (489 linhas)

Classificacao: risco de coesao/manutenibilidade (media prioridade), sem violacao MVCL critica.

### 17.3 Revalidacao consolidada apos a fase 9

- `php tools/architecture/run-mvcl-audit.php` -> `PASS` (10/0/0)
- `php tools/architecture/run-service-composition-audit.php` -> `PASS_WITH_WARNINGS` (17/1/0)
- `php tests/security/run-security-suite.php` -> `PASS` (24/0/0)
- `php tools/security/run-operational-audit.php` -> `PASS` (17/0/0)

## 18. Conclusao formal atualizada

Com as fases 1 a 9 concluidas, o Solis apresenta estrutura MVCL solida sob criterio operacional:

1. sem nao conformidades criticas de separacao de camadas;
2. sem DDL runtime em request path de `controllers/models/libraries`;
3. sem instanciacao distribuida de services com `Registry` fora dos `BaseController`;
4. com contratos de composicao (agregador + traits) validados automaticamente.

Risco residual atual: concentrado em tamanho/coesao de alguns traits especificos, com impacto de manutencao e testabilidade, mas sem bloqueio de conformidade estrutural.

## 19. Execucao da fase 10 (eliminacao de alertas de coesao estrutural)

Foi executada fase adicional com foco em remover os ultimos alertas de coesao da auditoria de composicao, mantendo contratos publicos e comportamento funcional.

### 19.1 Decomposicao de concerns e services em alerta

#### 19.1.1 Social Connection (controller concerns)

- `client/Controller/Concerns/SocialConnectionFlowTrait.php` foi convertido em agregador.
- Novos traits:
  - `client/Controller/Concerns/SocialConnectionOAuthFlowTrait.php`
  - `client/Controller/Concerns/SocialConnectionManualFlowTrait.php`
  - `client/Controller/Concerns/SocialConnectionSupportTrait.php`

Evidencia objetiva:

- `SocialConnectionFlowTrait.php`: **10 linhas** (antes: 489)
- `SocialConnectionOAuthFlowTrait.php`: 190 linhas
- `SocialConnectionManualFlowTrait.php`: 152 linhas
- `SocialConnectionSupportTrait.php`: 163 linhas

#### 19.1.2 Subscription billing e checkout

`system/Library/SubscriptionServiceBillingOperationsTrait.php` foi convertido em agregador com separacao por responsabilidade:

- `system/Library/SubscriptionServiceBillingPromotionsAndAnnouncementsTrait.php`
- `system/Library/SubscriptionServiceBillingSettingsTrait.php`
- `system/Library/SubscriptionServicePaymentValidationTrait.php`

Evidencia objetiva:

- `SubscriptionServiceBillingOperationsTrait.php`: **10 linhas** (antes: 527)
- `SubscriptionServiceBillingPromotionsAndAnnouncementsTrait.php`: 238 linhas
- `SubscriptionServiceBillingSettingsTrait.php`: 130 linhas
- `SubscriptionServicePaymentValidationTrait.php`: 171 linhas

`system/Library/SubscriptionServiceEntitlementAndCheckoutTrait.php` foi convertido em agregador:

- `system/Library/SubscriptionServiceEntitlementsTrait.php`
- `system/Library/SubscriptionServiceCheckoutLifecycleTrait.php`
- `system/Library/SubscriptionServiceAdminOverridesTrait.php`

Evidencia objetiva:

- `SubscriptionServiceEntitlementAndCheckoutTrait.php`: **10 linhas** (antes: 424)
- `SubscriptionServiceEntitlementsTrait.php`: 86 linhas
- `SubscriptionServiceCheckoutLifecycleTrait.php`: 218 linhas
- `SubscriptionServiceAdminOverridesTrait.php`: 132 linhas

#### 19.1.3 Campaign tracking

`system/Library/CampaignTrackingOperationsTrait.php` foi convertido em agregador:

- `system/Library/CampaignTrackingLinkCrudTrait.php`
- `system/Library/CampaignTrackingUrlHelpersTrait.php`

Evidencia objetiva:

- `CampaignTrackingOperationsTrait.php`: **9 linhas** (antes: 423)
- `CampaignTrackingLinkCrudTrait.php`: 267 linhas
- `CampaignTrackingUrlHelpersTrait.php`: 164 linhas

### 19.2 Endurecimento da auditoria de composicao

`tools/architecture/run-service-composition-audit.php` foi ampliado para validar contratos adicionais de agregadores recem-criados:

- `SubscriptionServiceBillingOperationsTrait` -> 3 subtraits contratuais;
- `SubscriptionServiceEntitlementAndCheckoutTrait` -> 3 subtraits contratuais;
- `CampaignTrackingOperationsTrait` -> 2 subtraits contratuais;
- `SocialConnectionFlowTrait` -> 3 subtraits contratuais.

### 19.3 Resultado consolidado apos fase 10

- `php tools/architecture/run-mvcl-audit.php` -> `PASS` (10/0/0)
- `php tools/architecture/run-service-composition-audit.php` -> `PASS` (22/0/0)
- `php tests/security/run-security-suite.php` -> `PASS` (24/0/0)
- `php tools/security/run-operational-audit.php` -> `PASS` (17/0/0)

Resultado formal:

1. alertas estruturais de coesao anteriormente abertos foram encerrados;
2. contratos de composicao em cadeia estao verificados automaticamente;
3. arquitetura MVCL permanece aderente, sem regressao funcional detectada nas auditorias.

Maturidade estrutural atualizada (escala 1-5): **4.2/5**.

## 20. Execucao da fase 11 (padronizacao de factories de service em controllers)

Foi executada fase adicional para consolidar um padrao unico de criacao de services em `Controller`:

1. remover instanciacao direta de services em controllers/concerns de negocio;
2. centralizar factories/cache em `BaseController` por area;
3. endurecer auditoria para impedir regressao do padrao.

### 20.1 Centralizacao aplicada no modulo client

`client/Controller/BaseController.php` recebeu factories/cache para services utilitarios e de dominio:

- `calendarService()`
- `planTemplateService()`
- `exportService()`
- `socialAuthService()`
- `socialFormatStandardsService()`
- `socialPlatformRegistry()`
- `contentStrategistService()`

Controllers/concerns atualizados para consumo via BaseController:

- `client/Controller/SocialController.php`
- `client/Controller/PlansController.php`
- `client/Controller/CalendarController.php`
- `client/Controller/Concerns/SocialContentActionsTrait.php`
- `client/Controller/Concerns/SocialConnectionOAuthFlowTrait.php`
- `client/Controller/Concerns/SocialConnectionManualFlowTrait.php`
- `client/Controller/Concerns/SocialConnectionSupportTrait.php`

### 20.2 Padronizacao aplicada no modulo admin

Foi removida instanciacao direta remanescente de `UsersListFilterService` fora da camada base:

- `admin/Controller/BaseController.php`: novo accessor `usersListFilter()`.
- `admin/Controller/Concerns/UsersControllerFiltersTrait.php`: passou a consumir accessor herdado.
- `admin/Controller/UsersController.php`: remocao de estado local redundante do service.

### 20.3 Endurecimento da auditoria de composicao

`tools/architecture/run-service-composition-audit.php` foi ampliado com novo gate:

- `checkServiceInstantiationInControllers()`

Regra formal:

- `new ...Service(...)` permitido apenas em:
  - `admin/Controller/BaseController.php`
  - `client/Controller/BaseController.php`

### 20.4 Evidencia objetiva

Varredura de instanciacao direta em controllers:

- `rg -n "new\\s+[A-Za-z0-9_\\\\]+Service\\(" admin/Controller client/Controller install/Controller`
- resultado: ocorrencias apenas em `admin/BaseController` e `client/BaseController`.

Resultado dos validadores apos fase 11:

- `php tools/architecture/run-mvcl-audit.php` -> `PASS` (10/0/0)
- `php tools/architecture/run-service-composition-audit.php` -> `PASS` (23/0/0)
- `php tests/security/run-security-suite.php` -> `PASS` (24/0/0)
- `php tools/security/run-operational-audit.php` -> `PASS` (17/0/0)

Conclusao da fase:

1. padrao de instanciacao de service em controllers foi formalizado e automatizado;
2. acoplamento por criacao ad-hoc de service foi eliminado do fluxo de negocio;
3. base arquitetural MVCL ganhou previsibilidade adicional para manutencao e testes.

Maturidade estrutural atualizada (escala 1-5): **4.3/5**.

## 21. Execucao da fase 12 (runner unico de quality gates)

Foi implementado runner unico para consolidar verificacoes arquiteturais e de seguranca em um unico comando operacional.

Arquivo criado:

- `tools/quality/run-quality-gates.php`

Escopo do runner:

1. executa `tools/architecture/run-mvcl-audit.php`;
2. executa `tools/architecture/run-service-composition-audit.php`;
3. executa `tests/security/run-security-suite.php`;
4. executa `tools/security/run-operational-audit.php`;
5. consolida resultado com status por gate, duracao e codigo de saida global.

Comando de execucao:

- `php tools/quality/run-quality-gates.php`

Evidencia objetiva (execucao local em 2026-05-07):

- `Checks=4`
- `Passes=4`
- `Failures=0`
- `Status=PASS`

Resultado tecnico:

1. padrao de validacao ponta-a-ponta formalizado em um entrypoint unico;
2. reducao de friccao para operacao local e futura integracao CI/CD;
3. rastreabilidade de qualidade ampliada com resumo consolidado por gate.

Maturidade estrutural atualizada (escala 1-5): **4.4/5**.

## 22. Execucao da fase 13 (governanca de quality gates e CI)

Foi executada fase complementar para aumentar rastreabilidade operacional e capacidade de bloqueio automatico por qualidade.

### 22.1 Endurecimento do runner unificado

Arquivo evoluido:

- `tools/quality/run-quality-gates.php`

Novos recursos implementados:

1. `--fail-fast` para interrupcao imediata na primeira falha;
2. `--exit-mode=boolean|bitmap` para retorno segmentado por gate;
3. `--only=id1,id2` e `--skip=id1,id2` para execucao seletiva;
4. `--list` para catalogo operacional de gates;
5. `--help` para padrao de uso;
6. mapeamento de bits por gate:
   - `mvcl=1`
   - `composition=2`
   - `security_suite=4`
   - `operational_security=8`

### 22.2 Evidencias de validacao local (2026-05-07)

Comandos executados e resultado:

1. `php -l tools/quality/run-quality-gates.php` -> sem erros de sintaxe.
2. `php tools/quality/run-quality-gates.php --list` -> catalogo de 4 gates com respectivos bits.
3. `php tools/quality/run-quality-gates.php` -> `PASS` com `Checks=4`, `Passes=4`, `Failures=0`, `ExitCode=0`.
4. `php tools/quality/run-quality-gates.php --exit-mode=bitmap` -> `PASS`, `FailureMask=0`, `ExitCode=0`.
5. Teste controlado de falha: `DB_HOST=invalid-host-for-audit` + `--only=operational_security --exit-mode=bitmap` -> `FAIL`, `FailureMask=8`, `ExitCode=8`.

Conclusao tecnica da validacao: o runner passou a fornecer sinalizacao deterministica por tipo de falha, adequada para gates de pipeline e diagnostico rapido.

### 22.3 Integracao CI adicionada

Workflow criado:

- `.github/workflows/quality-gates.yml`

Cobertura do workflow:

1. provisiona MySQL 8 (`service container`);
2. prepara base `solis_ci` e importa:
   - `install/sql/schema.sql`
   - `install/sql/seed.sql`
3. configura ambiente de seguranca/DB por variaveis (`APP_ENV`, `DB_*`, `TOKEN_CIPHER_KEY`, `ALLOWED_HOSTS`, etc.);
4. executa `php tools/quality/run-quality-gates.php --exit-mode=bitmap`.

Observacao operacional: a validacao local desta fase comprovou o comportamento funcional do runner; a execucao em GitHub Actions ocorrera no primeiro ciclo de `push`/`pull_request` apos o commit do workflow.

### 22.4 Resultado formal da fase 13

1. governanca de qualidade unificada concluida com controle de erro segmentado;
2. base pronta para bloqueio automatico de regressao arquitetural e de seguranca em CI;
3. aumento de previsibilidade de release sob criterio MVCL + seguranca.

Maturidade estrutural atualizada (escala 1-5): **4.5/5**.

## 23. Execucao da fase 14 (endurecimento de fronteiras MVCL em entrada/saida)

Foi executada fase adicional com foco em blindar regressao arquitetural nas fronteiras de entrada HTTP e saida de resposta.

### 23.1 Endurecimento do auditor de composicao

Arquivo atualizado:

- `tools/architecture/run-service-composition-audit.php`

Novas regras automatizadas adicionadas:

1. `checkInputSuperglobalsInCoreLayers()`:
   - bloqueia uso direto de `$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE`, `$_FILES`, `$_SESSION`
   - escopo: `admin|client|install (Controller/Model)` e `system/Library`
   - objetivo: reforcar uso de abstracoes de request e reduzir risco de bypass de sanitizacao/validacao.
2. `checkDirectOutputPrimitivesInControllersAndModels()`:
   - bloqueia saida direta por `echo`, `print`, `var_dump`, `die`, `exit`, `header`, `setcookie`
   - escopo: `admin|client|install (Controller/Model)`
   - objetivo: manter saida sob `Response/View`, preservando separacao de camadas MVCL.

### 23.2 Evidencias tecnicas da fase 14 (2026-05-07)

Validacoes executadas:

1. `php -l tools/architecture/run-service-composition-audit.php` -> sem erros de sintaxe.
2. `php tools/architecture/run-service-composition-audit.php` -> `PASS` com `Passes=25`, `Warnings=0`, `Failures=0`.
3. `php tools/quality/run-quality-gates.php --exit-mode=bitmap` -> `PASS` com:
   - `Checks=4`
   - `Passes=4`
   - `Failures=0`
   - `FailureMask=0`
   - `ExitCode=0`

### 23.3 Resultado formal da fase 14

1. fronteiras de entrada e saida ficaram protegidas por regra automatizada de regressao;
2. aderencia MVCL passou a incluir validacao explicita de disciplina de request/response em camadas core;
3. maturidade de governanca arquitetural evoluiu para padrao preventivo (nao apenas corretivo).

Maturidade estrutural atualizada (escala 1-5): **4.6/5**.

## 24. Execucao da fase 15 (politica de dependencias por dominio)

Foi executada fase adicional para formalizar e automatizar limites de dependencia entre dominios de area (`Admin`, `Client`, `Install`) e o nucleo de bibliotecas.

### 24.1 Endurecimento adicional do auditor

Arquivo atualizado:

- `tools/architecture/run-service-composition-audit.php`

Novas regras implementadas:

1. `checkCrossAreaNamespaceDependencies()`:
   - bloqueia dependencia cruzada entre namespaces de area em `Controller/Model`:
     - `use Admin\\...` em area `client` ou `install`
     - `use Client\\...` em area `admin` ou `install`
     - `use Install\\...` em area `admin` ou `client`
   - bloqueio cobre tambem instanciacao (`new`) e chamada estatica (`::`) cruzadas.
2. `checkSystemLibraryAreaIsolation()`:
   - bloqueia dependencia direta de `system/Library` para namespaces de area (`Admin|Client|Install`);
   - preserva isolamento do nucleo transversal em relacao aos modulos de interface.

### 24.2 Evidencias tecnicas da fase 15 (2026-05-07)

Validacoes executadas:

1. `php -l tools/architecture/run-service-composition-audit.php` -> sem erros de sintaxe.
2. `php tools/architecture/run-service-composition-audit.php` -> `PASS` com:
   - `Passes=27`
   - `Warnings=0`
   - `Failures=0`
3. `php tools/quality/run-quality-gates.php --exit-mode=bitmap` -> `PASS` com:
   - `Checks=4`
   - `Passes=4`
   - `Failures=0`
   - `FailureMask=0`
   - `ExitCode=0`

### 24.3 Resultado formal da fase 15

1. fronteiras de dependencia entre dominios ficaram protegidas por regra automatizada;
2. isolamento arquitetural entre camadas de area e `system/Library` foi explicitamente governado;
3. risco de acoplamento transversal inadvertido reduziu de forma mensuravel, com bloqueio preventivo de regressao.

Maturidade estrutural atualizada (escala 1-5): **4.7/5**.

## 25. Execucao da fase 16 (eliminacao de acesso direto a Database em controller)

Foi executada fase adicional para remover acoplamento residual de `Controller` com conexao transacional de banco, consolidando o acesso de persistencia em `Model`.

### 25.1 Refatoracao aplicada no modulo de autenticacao

Arquivos atualizados:

- `client/Controller/AuthController.php`
- `client/Controller/Concerns/AuthPasswordResetFlowTrait.php`
- `client/Model/AuthModel.php`

Mudancas objetivas:

1. substituicao de verificacoes diretas de disponibilidade de banco em controller/trait por chamada de modelo:
   - de `\$this->db->connected()` para `\$this->authModel()->databaseConnected()`.
2. remocao do controle transacional direto em controller no fluxo de cadastro:
   - de `beginTransaction/commit/rollBack` no `AuthController`
   - para `runInTransaction(callable)` centralizado em `AuthModel`.
3. preservacao do fluxo funcional:
   - criacao de usuario;
   - provisionamento de assinatura gratuita inicial;
   - rollback automatico em excecao.

### 25.2 Endurecimento adicional do auditor de composicao

Arquivo atualizado:

- `tools/architecture/run-service-composition-audit.php`

Nova regra implementada:

- `checkDirectDatabaseAccessInControllers()`:
  - bloqueia ocorrencia de `\$this->db->` em `admin|client|install/Controller`;
  - reforca politica MVCL de acesso a dados via `Model/Service`, nao via controller.

### 25.3 Evidencias tecnicas da fase 16 (2026-05-07)

Validacoes executadas:

1. `php -l client/Model/AuthModel.php` -> sem erros.
2. `php -l client/Controller/AuthController.php` -> sem erros.
3. `php -l client/Controller/Concerns/AuthPasswordResetFlowTrait.php` -> sem erros.
4. `php -l tools/architecture/run-service-composition-audit.php` -> sem erros.
5. `php tools/architecture/run-service-composition-audit.php` -> `PASS` com:
   - `Passes=28`
   - `Warnings=0`
   - `Failures=0`
6. `php tools/quality/run-quality-gates.php --exit-mode=bitmap` -> `PASS` com:
   - `Checks=4`
   - `Passes=4`
   - `Failures=0`
   - `FailureMask=0`
   - `ExitCode=0`

### 25.4 Resultado formal da fase 16

1. controller de autenticacao passou a operar sem dependencia direta da conexao de banco;
2. disciplina MVCL de persistencia por camada foi reforcada com gate automatico de regressao;
3. risco de acoplamento transacional em controller foi eliminado no fluxo de autenticacao.

Maturidade estrutural atualizada (escala 1-5): **4.8/5**.

## 26. Execucao da fase 17 (eliminacao de `$_SERVER` em controllers)

Foi executada fase adicional para remover o uso direto de `$_SERVER` em `Controller/Concern`, centralizando contexto HTTP em abstrações de camada base.

### 26.1 Centralizacao de infraestrutura HTTP na base de Controller

Arquivo atualizado:

- `system/Engine/Controller.php`

Novos utilitarios protegidos introduzidos:

1. `requestServer()`
2. `requestScriptDirectory()`
3. `requestRootDirectory()`
4. `requestIsHttps()`
5. `requestScheme()`
6. `effectiveRequestHost()`
7. `requestHostAuthority()`
8. `absoluteRouteUrl(string $route)`
9. `areaUrl(string $area)`

Objetivo tecnico: padronizar resolucao de host/scheme/path em uma unica camada, reduzindo duplicidade e risco de inconsistencias de seguranca entre modulos.

### 26.2 Refatoracoes aplicadas nos modulos

Arquivos atualizados:

- `client/Controller/AuthController.php`
- `client/Controller/Concerns/AuthPasswordResetMailTrait.php`
- `client/Controller/Concerns/SocialConnectionSupportTrait.php`
- `install/Controller/IndexController.php`

Mudancas objetivas:

1. remocao de `$_SERVER` direto em URL de landing/reset/social callback/install;
2. uso de `absoluteRouteUrl()` para links absolutos;
3. uso de `areaUrl('client')` para redirecionamento pos-instalacao;
4. uso de `effectiveRequestHost()`/`requestRootDirectory()` na resolucao de host/ambiente/hosts permitidos.

### 26.3 Endurecimento adicional do auditor de composicao

Arquivo atualizado:

- `tools/architecture/run-service-composition-audit.php`

Nova regra implementada:

- `checkServerSuperglobalInControllers()`:
  - bloqueia ocorrencia de `$_SERVER` em `admin|client|install/Controller`;
  - reforca consumo de contexto HTTP via `Request`/`Controller` base.

### 26.4 Evidencias tecnicas da fase 17 (2026-05-07)

Validacoes executadas:

1. `php -l` nos arquivos alterados -> sem erros de sintaxe.
2. `php tools/architecture/run-service-composition-audit.php` -> `PASS` com:
   - `Passes=29`
   - `Warnings=0`
   - `Failures=0`
3. `php tools/quality/run-quality-gates.php --exit-mode=bitmap` -> `PASS` com:
   - `Checks=4`
   - `Passes=4`
   - `Failures=0`
   - `FailureMask=0`
   - `ExitCode=0`

### 26.5 Resultado formal da fase 17

1. controllers passaram a operar sem dependencia direta de `$_SERVER`;
2. infraestrutura HTTP ganhou padrao unico por camada, com menor risco de divergencia de comportamento;
3. governanca MVCL evoluiu com bloqueio automatico de regressao para acesso de server superglobal.

Maturidade estrutural atualizada (escala 1-5): **4.9/5**.

## 27. Execucao da fase 18 (eliminacao de `$_SERVER` em models)

Foi executada fase adicional para concluir a retirada de `$_SERVER` direto nas camadas de dominio (`Model`), mantendo esse acesso restrito a abstrações de request/infra.

### 27.1 Refatoracao aplicada no instalador

Arquivo atualizado:

- `install/Model/InstallerModel.php`

Mudancas objetivas:

1. remocao de `$_SERVER` direto nos metodos de URL/base host:
   - `buildBaseUrl()`
   - `resolveAllowedHosts()`
2. introducao de helper interno `requestServer()` para consumo de `Request->server`;
3. resolucao de scheme/host/path migrada para dados normalizados do request atual.

Resultado: o modulo de instalacao deixou de depender de superglobal diretamente na camada `Model`.

### 27.2 Endurecimento adicional do auditor de composicao

Arquivo atualizado:

- `tools/architecture/run-service-composition-audit.php`

Nova regra implementada:

- `checkServerSuperglobalInModels()`:
  - bloqueia ocorrencia de `$_SERVER` em `admin|client|install/Model`;
  - consolida politica de consumo de contexto HTTP via `Request`/abstrações.

### 27.3 Evidencias tecnicas da fase 18 (2026-05-07)

Validacoes executadas:

1. `php -l install/Model/InstallerModel.php` -> sem erros de sintaxe.
2. `php -l tools/architecture/run-service-composition-audit.php` -> sem erros de sintaxe.
3. `rg -n --fixed-strings '$_SERVER' admin/Model client/Model install/Model` -> sem ocorrencias.
4. `php tools/architecture/run-service-composition-audit.php` -> `PASS` com:
   - `Passes=30`
   - `Warnings=0`
   - `Failures=0`
5. `php tools/quality/run-quality-gates.php --exit-mode=bitmap` -> `PASS` com:
   - `Checks=4`
   - `Passes=4`
   - `Failures=0`
   - `FailureMask=0`
   - `ExitCode=0`

### 27.4 Resultado formal da fase 18

1. camada de models ficou sem dependencia direta de `$_SERVER`;
2. governanca MVCL ganhou protecao automatica para esse criterio em controller e model;
3. superficie de acoplamento com superglobais em regras de dominio foi reduzida ao minimo.

Maturidade estrutural atualizada (escala 1-5): **5.0/5**.

## 58. Execucao da fase 49 (suite de fluxos criticos e integracao ao quality gate)

Foi executada fase complementar para elevar a governanca de maturidade tecnica com verificacao automatizada dos fluxos mais sensiveis do sistema.

### 58.1 Escopo tecnico implementado

Arquivos introduzidos/atualizados:

- `tests/critical/run-critical-flow-suite.php` (novo)
- `tools/quality/run-quality-gates.php` (atualizado)

Cobertura da nova suite critica:

1. fluxo de recuperacao de senha (Auth):
   - guardas de `POST + CSRF`;
   - token de reset com entropia forte e hash em armazenamento;
   - validacao estrita do formato de token;
   - hash de senha com `PASSWORD_DEFAULT`;
   - ausencia de DDL runtime no fluxo HTTP.
2. fluxo de checkout e validacao manual de pagamentos (Billing):
   - trilha de transacoes `pending/paid`;
   - evento de validacao manual solicitado;
   - transicoes de aprovacao/rejeicao com carimbos de processamento;
   - sincronismo de fatura paga e ativacao de plano na aprovacao.
3. pipeline de publicacao social:
   - protecao `POST + CSRF` nas mutacoes;
   - contrato de fila (`queuePublication`, `queueFromPlanItem`, `processDueQueue`);
   - transicoes de status (`processing`, `published`, `failed`, `manual_review`);
   - controle de `dry_run` por configuracao.
4. mutacoes do calendario:
   - protecao `POST + CSRF` em `saveNote`, `saveExtraEvent`, `saveColors`, `deleteExtraEvent`;
   - escopo de remocao vinculado ao usuario autenticado.

### 58.2 Integracao ao pipeline de qualidade

Novo gate registrado:

- `id=critical_flows`
- `bit=16`
- `label=Critical Flow Suite`
- `command=php tests/critical/run-critical-flow-suite.php`

Efeito pratico:

- o runner de qualidade passa de 4 para 5 gates disponiveis, com capacidade de bloquear regressao de contratos operacionais criticos de negocio/aplicacao.

### 58.3 Evidencias tecnicas da fase 49 (2026-05-07)

Validacoes executadas:

1. `php -l tests/critical/run-critical-flow-suite.php` -> sem erros.
2. `php -l tools/quality/run-quality-gates.php` -> sem erros.
3. `php tools/quality/run-quality-gates.php --list` -> gate `critical_flows` listado com `bit=16`.
4. `php tests/critical/run-critical-flow-suite.php` -> `PASS` com:
   - `Passes=4`
   - `Warnings=0`
   - `Failures=0`
5. `php tools/quality/run-quality-gates.php --exit-mode=bitmap` -> `FAIL` com:
   - `Checks=5`
   - `Passes=4`
   - `Failures=1`
   - `FailureMask=8`
   - causa objetiva: indisponibilidade de conexao com banco no gate `operational_security` (`SQLSTATE[HY000] [2002]`).
6. `php tools/quality/run-quality-gates.php --skip=operational_security --exit-mode=bitmap` -> `PASS` com:
   - `Checks=4`
   - `Passes=4`
   - `Failures=0`
   - `FailureMask=0`
   - `ExitCode=0`

### 58.4 Resultado formal da fase 49

1. o sistema ganhou suite dedicada para fluxos criticos com verificacao automatizada e rastreavel;
2. o pipeline de qualidade foi fortalecido com novo gate preventivo (`critical_flows`);
3. a unica falha no ciclo completo decorre de dependencia externa de ambiente (banco offline), sem indicio de regressao estrutural do codigo auditado.

## 59. Revalidacao operacional completa da fase 49 (2026-05-08)

Foi executada revalidacao integral no dia **2026-05-08**, apos estabilizacao do ambiente local de banco, para fechamento formal da fase.

### 59.1 Evidencias tecnicas (2026-05-08)

Validacoes executadas:

1. `php tools/security/run-operational-audit.php` -> `PASS` com:
   - `Passes=17`
   - `Warnings=0`
   - `Failures=0`
2. `php tools/quality/run-quality-gates.php --exit-mode=bitmap` -> `PASS` com:
   - `Checks=5`
   - `Passes=5`
   - `Failures=0`
   - `FailureMask=0`
   - `ExitCode=0`

### 59.2 Resultado formal consolidado

1. a validacao de seguranca operacional foi concluida sem pendencias;
2. todos os gates de qualidade (MVCL, composicao, seguranca, seguranca operacional e fluxos criticos) fecharam em conformidade plena;
3. a fase 49 passa a constar como encerrada com evidencias de execucao completas.

## 60. Execucao da fase 50 (smoke dinamico de fluxos criticos e gate de maturidade MVCL) - 2026-05-08

Foi executada fase adicional para elevar maturidade de verificacao em duas frentes:

1. cobertura dinamica de runtime na suite `critical_flows`;
2. novo gate arquitetural de budget para maturidade MVCL.

### 60.1 Suite critica com verificacao dinamica de banco

Arquivo atualizado:

- `tests/critical/run-critical-flow-suite.php`

Evolucao implementada:

1. adicao de smoke de runtime com conexao de banco via configuracao efetiva (root + storage + overrides de ambiente);
2. validacao de contratos estruturais por fluxo (tabelas, colunas, enums e indices):
   - `password_resets`
   - `billing_invoices`
   - `payment_transactions`
   - `social_publications`
   - `social_publication_logs`
   - `content_day_notes`
   - `calendar_extra_events`
3. validacao de consultas de contagem para confirmar operabilidade basica das tabelas criticas.

Resultado tecnico da suite critica apos ampliacao:

- `Passes=5`
- `Warnings=0`
- `Failures=0`
- `Status=PASS`

### 60.2 Novo gate de maturidade MVCL por orcamento

Arquivo criado:

- `tools/architecture/run-mvcl-maturity-budget-audit.php`

Cobertura do novo auditor:

1. budget de tamanho para controllers;
2. budget de metodos publicos em controllers;
3. budget de densidade de service accessors em controllers;
4. budget de tamanho em models/libraries com tratamento por classe/trait;
5. excecao formal de budget para `install/Model/InstallerModel.php` (perfil de instalacao).

Integracao no pipeline:

- `tools/quality/run-quality-gates.php`
  - novo gate: `id=mvcl_maturity`, `bit=32`.

### 60.3 Evidencias tecnicas da fase 50 (2026-05-08)

Validacoes executadas:

1. `php -l tests/critical/run-critical-flow-suite.php` -> sem erros.
2. `php -l tools/architecture/run-mvcl-maturity-budget-audit.php` -> sem erros.
3. `php -l tools/quality/run-quality-gates.php` -> sem erros.
4. `php tests/critical/run-critical-flow-suite.php` -> `PASS` com:
   - `Passes=5`
   - `Warnings=0`
   - `Failures=0`
5. `php tools/architecture/run-mvcl-maturity-budget-audit.php` -> `PASS` com:
   - `Passes=4`
   - `Warnings=0`
   - `Failures=0`
6. `php tools/quality/run-quality-gates.php --exit-mode=bitmap` -> `PASS` com:
   - `Checks=6`
   - `Passes=6`
   - `Failures=0`
   - `FailureMask=0`
   - `ExitCode=0`

### 60.4 Resultado formal da fase 50

1. a cobertura critica passou a combinar contrato estatico + evidencias dinamicas de runtime;
2. a maturidade MVCL ganhou gate preventivo dedicado para budgets arquiteturais;
3. o pipeline consolidado passou a operar com 6 gates em conformidade plena.

## 45. Execucao da fase 36 (reducao incremental da allowlist temporal em seguranca e fila social)

Foi executada fase adicional de reducao de excecoes temporais autorizadas, focada em trilhas operacionais de seguranca de autenticacao e fila de publicacoes sociais.

### 45.1 Refatoracoes aplicadas

Arquivos atualizados:

- `system/Library/SecurityAuthAuditTrait.php`
- `system/Library/SocialPublishingQueueTrait.php`

Mudancas objetivas:

1. remocao de `strtotime(...)` em `SecurityAuthAuditTrait`, com calculo de bloqueio por parser temporal explicito + aritmetica de janela (`+ minutos`);
2. remocao de `strtotime(...)` em `SocialPublishingQueueTrait::normalizeDatetime(...)`, com normalizacao estruturada de datetime (`YYYY-MM-DD[ T]HH:MM[:SS]`);
3. manutencao da semantica funcional de bloqueio/normalizacao com menor dependencia de parse heuristico.

### 45.2 Endurecimento adicional do auditor

Arquivo atualizado:

- `tools/architecture/run-service-composition-audit.php`

Ajuste aplicado:

1. reducao da allowlist de `checkStrtotimeUsageInModelsAndLibraries()`:
   - removidos:
     - `system/Library/SecurityAuthAuditTrait.php`
     - `system/Library/SocialPublishingQueueTrait.php`
2. reintroducao de `strtotime` nesses pontos passa a ser bloqueada automaticamente pelo gate.

Objetivo tecnico: continuar diminuindo superficie de excecoes temporais em bibliotecas de fluxo critico (seguranca/autenticacao e fila social), sem regressao operacional.

### 45.3 Evidencias tecnicas da fase 36 (2026-05-07)

Validacoes executadas:

1. `rg --pcre2 -n "(?<!->)\\bstrtotime\\s*\\(" system/Library/SecurityAuthAuditTrait.php system/Library/SocialPublishingQueueTrait.php` -> sem ocorrencias.
2. `php -l system/Library/SecurityAuthAuditTrait.php` -> sem erros.
3. `php -l system/Library/SocialPublishingQueueTrait.php` -> sem erros.
4. `php -l tools/architecture/run-service-composition-audit.php` -> sem erros.
5. `php tools/architecture/run-service-composition-audit.php` -> `PASS` com:
   - `Passes=47`
   - `Warnings=0`
   - `Failures=0`
6. `php tools/quality/run-quality-gates.php --exit-mode=bitmap` -> `PASS` com:
   - `Checks=4`
   - `Passes=4`
   - `Failures=0`
   - `FailureMask=0`
   - `ExitCode=0`

### 45.4 Resultado formal da fase 36

1. allowlist temporal foi reduzida novamente sem perda de estabilidade;
2. camadas de seguranca de login e fila social ficaram sem `strtotime` direto;
3. governanca arquitetural permaneceu integralmente em `PASS`.

Maturidade estrutural atualizada (escala 1-5): **5.0/5**.

## 57. Execucao da fase 48 (governanca de sanitizacao de saida em views criticas)

Foi executada fase de endurecimento da camada de apresentacao para formalizar politica de escape de saida em arquivos criticos de UI.

### 57.1 Refatoracoes aplicadas

Arquivos atualizados:

- `tests/security/run-security-suite.php`
- `client/View/billing/index.php`
- `admin/View/users/index.php`

Mudancas objetivas:

1. `Security Suite` recebeu novo teste dedicado: `testCriticalViewsOutputEscapingPolicy()`;
2. nova politica automatizada para short echo (`<?= ... ?>`) em views criticas:
   - exige `e(...)` ou `csrf_field()` como padrao;
   - permite excecoes controladas para casts numericos (`(int|float|bool)`) e toggles de atributo por ternario literal (`selected/checked/disabled/classes`);
3. `client/View/billing/index.php` teve reforco de escape e tipagem:
   - valores monetarios via `e($formatMoney(...))`;
   - contadores com cast inteiro explicito;
   - largura percentual sanitizada como string escapada;
4. `admin/View/users/index.php` recebeu cast explicito em `value` de options dinamicos (`groupId/optionId`).

Resultado tecnico: views criticas passaram a ter regra auditavel de output encoding, com reducao de saida bruta e maior previsibilidade de sanitizacao.

### 57.2 Endurecimento adicional da suíte de seguranca

Arquivo atualizado:

- `tests/security/run-security-suite.php`

Novo controle implementado:

1. `testCriticalViewsOutputEscapingPolicy()`:
   - escopo:
     - `admin/View/users/index.php`
     - `client/View/billing/index.php`
     - `admin/View/suggestions/form.php`
     - `admin/View/holidays/form.php`
   - valida cada short echo com parser por expressao e linha;
   - reprova expressoes fora da politica de escape definida.

Objetivo tecnico: evitar regressao de XSS/reflexao por saida nao escapada em telas de maior sensibilidade operacional.

### 57.3 Evidencias tecnicas da fase 48 (2026-05-07)

Validacoes executadas:

1. `php -l` dos arquivos alterados -> sem erros de sintaxe.
2. `php tests/security/run-security-suite.php` -> `PASS` com:
   - `Passes=25`
   - `Warnings=0`
   - `Failures=0`
3. `php tools/quality/run-quality-gates.php --exit-mode=bitmap` -> `PASS` com:
   - `Checks=4`
   - `Passes=4`
   - `Failures=0`
   - `FailureMask=0`
   - `ExitCode=0`
4. `php tools/architecture/run-service-composition-audit.php` -> `PASS` com:
   - `Passes=50`
   - `Warnings=0`
   - `Failures=0`

### 57.4 Resultado formal da fase 48

1. politica de sanitizacao de saida passou de heuristica basica para controle dedicado em views criticas;
2. regressao de short echo inseguro nessas telas agora gera falha automatica de seguranca;
3. baseline MVCL + seguranca permaneceu estavel em `PASS` integral.

Maturidade estrutural atualizada (escala 1-5): **5.0/5**.

## 56. Execucao da fase 47 (governanca temporal dedicada para `system/Engine`)

Foi executada fase adicional de governanca para cobrir explicitamente a camada `system/Engine` com regra temporal dedicada, eliminando uso direto de primitivas em `Controller/Model/Session`.

### 56.1 Refatoracoes aplicadas no Engine

Arquivos atualizados:

- `system/Engine/Controller.php`
- `system/Engine/Model.php`
- `system/Engine/Session.php`

Mudancas objetivas:

1. `Controller` passou a usar `TemporalClockTrait` do `Engine`;
2. `nowUnixTime()` migrou para `clockUnixNow()` e `formatDateTime()` para `clockFormat/clockFormatAt`;
3. `nowMicrotime()` migrou para `hrtime(true)` (medicao monotonica);
4. `parseDateToTimestamp()` deixou de usar `strtotime()` e passou a parser estruturado (`YYYY-MM-DD[ HH:MM[:SS]]`);
5. `Model` passou a delegar `modelClock*` para helpers do `TemporalClockTrait`;
6. `Session::destroy()` removeu `time()` direto em favor de `clockUnixNow()`.

Resultado tecnico: o `Engine` ficou sem `date()/strtotime()/time()/microtime()/DateTimeImmutable` fora do relogio central.

### 56.2 Endurecimento adicional do auditor

Arquivo atualizado:

- `tools/architecture/run-service-composition-audit.php`

Nova regra implementada:

1. `checkTemporalPrimitiveUsageInEngine()`:
   - escopo: `system/Engine`;
   - bloqueia `date/strtotime/time/microtime/DateTimeImmutable`;
   - allowlist restrita ao arquivo `system/Engine/TemporalClockTrait.php`.

Objetivo tecnico: impedir regressao temporal em camada core de infraestrutura, com regra preventiva explicita no pipeline.

### 56.3 Evidencias tecnicas da fase 47 (2026-05-07)

Validacoes executadas:

1. `php -l` dos arquivos alterados -> sem erros de sintaxe.
2. `rg` temporal em `system/Engine` -> ocorrencias apenas em `system/Engine/TemporalClockTrait.php`.
3. `php tools/architecture/run-service-composition-audit.php` -> `PASS` com:
   - `Passes=50`
   - `Warnings=0`
   - `Failures=0`
4. `php tools/quality/run-quality-gates.php --exit-mode=bitmap` -> `PASS` com:
   - `Checks=4`
   - `Passes=4`
   - `Failures=0`
   - `FailureMask=0`
   - `ExitCode=0`

### 56.4 Resultado formal da fase 47

1. governanca temporal foi estendida de dominio (`Model/Library`) para infraestrutura (`Engine`);
2. regra automatizada dedicada passou a proteger regressao no nucleo de framework;
3. arquitetura MVCL e seguranca operacional permaneceram estaveis com todos os gates em `PASS`.

Maturidade estrutural atualizada (escala 1-5): **5.0/5**.

## 55. Execucao da fase 46 (centralizacao temporal em Engine e allowlist `date()` zerada em Model/Library)

Foi executada fase de consolidacao arquitetural para deslocar a implementacao temporal de baixo nivel para a camada `Engine`, removendo a ultima excecao de `date()` na camada `Library`.

### 55.1 Refatoracoes aplicadas

Arquivo criado:

- `system/Engine/TemporalClockTrait.php`

Arquivo atualizado:

- `system/Library/TemporalClockTrait.php`

Mudancas objetivas:

1. implementacao completa de clock (`clockUnixNow`, `clockDateTimeNow`, `clockDateTimeFromUnix`, `clockDateTimeAfterSeconds`, `clockIso8601Now`, `clockFormat`, `clockFormatAt`) movida para `System\Engine\TemporalClockTrait`;
2. `System\Library\TemporalClockTrait` passou a ser trait de ponte (`use EngineTemporalClockTrait`), sem uso direto de `date()`;
3. fronteira de responsabilidade temporal foi formalizada: infraestrutura temporal no `Engine`, consumo por servicos em `Library`.

### 55.2 Endurecimento adicional do auditor

Arquivo atualizado:

- `tools/architecture/run-service-composition-audit.php`

Ajuste aplicado em `checkDateUsageInModelsAndLibraries()`:

1. allowlist de `date()` foi zerada (`allowedFiles = []`).

Objetivo tecnico: transformar `date()` em proibicao total dentro do escopo auditado (`admin/client/install Model` e `system/Library`), sem excecoes.

### 55.3 Evidencias tecnicas da fase 46 (2026-05-07)

Validacoes executadas:

1. `php -l system/Engine/TemporalClockTrait.php` -> sem erros de sintaxe.
2. `php -l system/Library/TemporalClockTrait.php` -> sem erros de sintaxe.
3. `php -l tools/architecture/run-service-composition-audit.php` -> sem erros de sintaxe.
4. `rg --pcre2 -n "(?<!->)\bdate\s*\(" admin/Model client/Model install/Model system/Library` -> sem ocorrencias.
5. `rg --pcre2 -n "(?<!new\s)\bDateTimeImmutable\b|new\s+DateTimeImmutable\s*\(|(?<!->)\bstrtotime\s*\(" admin/Model client/Model install/Model system/Library` -> sem ocorrencias.
6. `php tools/architecture/run-service-composition-audit.php` -> `PASS` com:
   - `Passes=49`
   - `Warnings=0`
   - `Failures=0`
7. `php tools/quality/run-quality-gates.php --exit-mode=bitmap` -> `PASS` com:
   - `Checks=4`
   - `Passes=4`
   - `Failures=0`
   - `FailureMask=0`
   - `ExitCode=0`

### 55.4 Resultado formal da fase 46

1. `Model/Library` ficou com baseline temporal sem `date()/strtotime()/DateTimeImmutable` direto;
2. a infraestrutura temporal foi reposicionada para `Engine`, reforcando separacao de responsabilidades;
3. a governanca temporal atingiu criterio maximo no escopo auditado, sem regressao em arquitetura e seguranca.

Maturidade estrutural atualizada (escala 1-5): **5.0/5**.

## 54. Execucao da fase 45 (eliminacao de `date()` direto em Library, exceto relogio central)

Foi executada fase adicional de endurecimento temporal para remover chamadas diretas de `date()` na camada `system/Library`, preservando somente o uso encapsulado no `TemporalClockTrait`.

### 54.1 Refatoracoes aplicadas

Arquivo atualizado (nucleo temporal):

- `system/Library/TemporalClockTrait.php`

Novo helper adicionado:

1. `clockFormatAt(int $timestamp, string $format)`.

Classes atualizadas para adotar o relogio central:

- `system/Library/CampaignTrackingService.php`
- `system/Library/FeatureFlagService.php`
- `system/Library/SocialPublishingService.php`
- `system/Library/PlannerService.php`
- `system/Library/PlanTemplateService.php`

Traits/servicos com migracao de `date()` para helpers de clock:

- `system/Library/CampaignTrackingLinkCrudTrait.php`
- `system/Library/FeatureFlagCrudTrait.php`
- `system/Library/FeatureFlagSchemaTrait.php`
- `system/Library/SocialPublishingQueueTrait.php`
- `system/Library/SocialPublishingDeliveryTrait.php`
- `system/Library/SubscriptionServiceAdminOverridesTrait.php`
- `system/Library/SubscriptionServiceBillingPromotionsAndAnnouncementsTrait.php`
- `system/Library/SubscriptionServiceContextTrait.php`
- `system/Library/SubscriptionServiceCheckoutLifecycleTrait.php`
- `system/Library/SubscriptionServicePlanPersistenceTrait.php`
- `system/Library/SubscriptionServicePaymentValidationTrait.php`
- `system/Library/SubscriptionServiceSchemaAndPlansTrait.php`

Resultado tecnico: pontos de timestamp operacional, janelas mensais e identificadores temporais passaram a depender de `clockDateTimeNow()/clockFormat()/clockFormatAt()`, com padronizacao transversal da camada de biblioteca.

### 54.2 Endurecimento adicional do auditor

Arquivo atualizado:

- `tools/architecture/run-service-composition-audit.php`

Ajuste aplicado em `checkDateUsageInModelsAndLibraries()`:

1. allowlist de `date()` reduzida para um unico arquivo:
   - `system/Library/TemporalClockTrait.php`.

Objetivo tecnico: garantir que `date()` permaneca exclusivamente no ponto de infraestrutura temporal, com bloqueio automatizado para qualquer regressao em `Model/Library`.

### 54.3 Evidencias tecnicas da fase 45 (2026-05-07)

Validacoes executadas:

1. `php -l` em todos os arquivos PHP alterados nesta fase -> sem erros de sintaxe.
2. `rg --pcre2 -n "(?<!->)\\bdate\\s*\\(" system/Library admin/Model client/Model install/Model` -> ocorrencias apenas em `system/Library/TemporalClockTrait.php`.
3. `php tools/architecture/run-service-composition-audit.php` -> `PASS` com:
   - `Passes=49`
   - `Warnings=0`
   - `Failures=0`
4. `php tools/quality/run-quality-gates.php --exit-mode=bitmap` -> `PASS` com:
   - `Checks=4`
   - `Passes=4`
   - `Failures=0`
   - `FailureMask=0`
   - `ExitCode=0`

### 54.4 Resultado formal da fase 45

1. camada `Library` ficou sem `date()` direto em regras de negocio;
2. uso de `date()` foi concentrado no relogio central (`TemporalClockTrait`);
3. governanca temporal alcançou baseline mais restritivo sem impacto nos gates de arquitetura, seguranca e operacao.

Maturidade estrutural atualizada (escala 1-5): **5.0/5**.

## 53. Execucao da fase 44 (reducao da allowlist de `date()` na camada Model)

Foi executada fase complementar de endurecimento temporal para reduzir a allowlist de `date()` no auditor de composicao, removendo ocorrencias diretas na camada de `Model` por meio de relogio centralizado.

### 53.1 Refatoracoes aplicadas

Arquivo atualizado (base da camada Model):

- `system/Engine/Model.php`

Novos helpers protegidos adicionados:

1. `modelClockUnixNow()`;
2. `modelClockDateTimeNow()`;
3. `modelClockIso8601Now()`;
4. `modelClockFormat(string $format)`;
5. `modelClockFormatAt(int $timestamp, string $format)`.

Arquivos atualizados na camada `admin/Model`:

- `admin/Model/AbstractCrudModel.php`
- `admin/Model/ContentSuggestionsModel.php`
- `admin/Model/SettingsModel.php`

Arquivos atualizados na camada `client/Model`:

- `client/Model/PlannerModelPlanLifecycleTrait.php`
- `client/Model/PlannerModelStatusAutomationTrait.php`
- `client/Model/PlannerModelCalendarTrait.php`
- `client/Model/SocialModelConnectionsTrait.php`
- `client/Model/SocialModelDraftsAndPresetsTrait.php`
- `client/Model/CalendarModelBaseEventsTrait.php`
- `client/Model/CalendarModelEventsTrait.php`

Ajustes complementares de apoio:

- `system/Library/TemporalClockTrait.php`: inclusao de `clockDateTimeFromUnix(int $timestamp)` e `clockFormat(string $format)`;
- `system/Library/SubscriptionServiceBillingInternalsTrait.php`: substituicao de `date('Ymd')` e `date('YmdHis')` por `clockFormat(...)`.

Resultado tecnico: campos e conversoes temporais em models passaram a usar helpers padronizados de camada base, reduzindo dispersao e acoplamento a primitivas globais.

### 53.2 Endurecimento adicional do auditor

Arquivo atualizado:

- `tools/architecture/run-service-composition-audit.php`

Ajuste aplicado em `checkDateUsageInModelsAndLibraries()`:

1. remocao da allowlist de arquivos `admin/Model/*` e `client/Model/*`;
2. manutencao temporaria apenas de excecoes residuais em `system/Library/*` e no proprio `system/Library/TemporalClockTrait.php`.

Objetivo tecnico: aumentar rigor da governanca temporal nas camadas de dominio, priorizando `Model` como camada sem `date()` direto.

### 53.3 Evidencias tecnicas da fase 44 (2026-05-07)

Validacoes executadas:

1. `php -l` em todos os arquivos alterados -> sem erros de sintaxe.
2. `rg --pcre2 -n "(?<!->)\\bdate\\s*\\(" admin/Model client/Model` -> sem ocorrencias.
3. `php tools/architecture/run-service-composition-audit.php` -> `PASS` com:
   - `Passes=49`
   - `Warnings=0`
   - `Failures=0`
4. `php tools/quality/run-quality-gates.php --exit-mode=bitmap` -> `PASS` com:
   - `Checks=4`
   - `Passes=4`
   - `Failures=0`
   - `FailureMask=0`
   - `ExitCode=0`

### 53.4 Resultado formal da fase 44

1. camada `Model` ficou sem uso direto de `date()` em `admin/client`;
2. calculos e formatos temporais migraram para helpers centralizados de base;
3. allowlist temporal de `date()` foi reduzida com seguranca, sem regressao nos gates estruturais e de seguranca.

Maturidade estrutural atualizada (escala 1-5): **5.0/5**.

## 52. Execucao da fase 43 (governanca de date() em Model/Library com baseline controlado)

Foi executada fase de governanca temporal para `date()` em camadas de dominio, com formalizacao de baseline auditavel e reducao adicional de uso direto em servicos criticos.

### 52.1 Refatoracoes aplicadas

Arquivo atualizado:

- `system/Library/TemporalClockTrait.php`

Novos helpers adicionados:

1. `clockDateTimeFromUnix(int $timestamp)`;
2. `clockIso8601Now()`;
3. `clockFormat(string $format)`.

Arquivos atualizados (substituicao de `date()` direto por helper temporal):

- `system/Library/Auth.php`
- `system/Library/AutomationService.php`
- `system/Library/AutomationServiceDispatchTrait.php`
- `system/Library/AutomationServiceWebhookCrudTrait.php`
- `system/Library/ObservabilityService.php`
- `system/Library/JobMonitorOperationsTrait.php`
- `system/Library/SecurityAuthAuditTrait.php`
- `system/Library/SubscriptionServiceBillingInternalsTrait.php`

Resultado tecnico: os pontos de data/hora desses servicos passaram a utilizar o relogio centralizado, reduzindo acoplamento e dispersao de implementacoes.

### 52.2 Endurecimento adicional do auditor

Arquivo atualizado:

- `tools/architecture/run-service-composition-audit.php`

Nova regra implementada:

1. `checkDateUsageInModelsAndLibraries()`:
   - escopo: `admin|client|install/Model` e `system/Library`;
   - bloqueia `date()` fora de allowlist temporal controlada;
   - baseline inicial explicitado para permitir reducao progressiva sem regressao.

Objetivo tecnico: transformar o uso de `date()` em criterio governado por pipeline, com trilha de reducao incremental.

### 52.3 Evidencias tecnicas da fase 43 (2026-05-07)

Validacoes executadas:

1. `php -l` em todos os arquivos alterados -> sem erros de sintaxe.
2. `rg --pcre2 -n "(?<!->)\\bdate\\s*\\("` em arquivos refatorados -> sem ocorrencias diretas residuais nesses pontos.
3. `php tools/architecture/run-service-composition-audit.php` -> `PASS` com:
   - `Passes=49`
   - `Warnings=0`
   - `Failures=0`
4. `php tools/quality/run-quality-gates.php --exit-mode=bitmap` -> `PASS` com:
   - `Checks=4`
   - `Passes=4`
   - `Failures=0`
   - `FailureMask=0`
   - `ExitCode=0`

### 52.4 Resultado formal da fase 43

1. `date()` em `Model/Library` passou a ter governanca automatizada com baseline rastreavel;
2. servicos criticos receberam reducao adicional de uso temporal direto via centralizacao no trait;
3. conformidade MVCL, seguranca e operacao foi preservada em `PASS` integral.

Maturidade estrutural atualizada (escala 1-5): **5.0/5**.

## 51. Execucao da fase 42 (centralizacao do relogio temporal em trait compartilhado)

Foi executada fase de consolidacao arquitetural para remover duplicacao de helpers temporais e padronizar a obtencao de tempo de runtime em `Library`.

### 51.1 Refatoracoes aplicadas

Arquivo criado:

- `system/Library/TemporalClockTrait.php`

Arquivos atualizados (adoção do trait):

- `system/Library/Auth.php`
- `system/Library/JobMonitorService.php`
- `system/Library/SecurityService.php`
- `system/Library/ObservabilityService.php`
- `system/Library/SocialAuthService.php`
- `system/Library/SubscriptionService.php`

Arquivos atualizados (consumo dos helpers centralizados):

- `system/Library/JobMonitorOperationsTrait.php`
- `system/Library/SecurityAuthAuditTrait.php`
- `system/Library/SubscriptionServiceBillingInternalsTrait.php`

Mudancas objetivas:

1. extracao de funcoes temporais repetidas para `TemporalClockTrait`:
   - `clockUnixNow()`
   - `clockDateTimeNow()`
   - `clockDateTimeAfterSeconds()`
2. remocao de implementacoes duplicadas locais (`currentUnixTime`) em classes/traits operacionais;
3. padronizacao de calculo de expiracao e vencimento com um unico ponto de manutencao.

### 51.2 Resultado estrutural

1. reducao de duplicacao de logica temporal em servicos criticos de seguranca e observabilidade;
2. aumento de coesao na camada `system/Library` com reuso interno orientado a trait;
3. manutencao da conformidade MVCL sem impacto no comportamento funcional observado.

### 51.3 Evidencias tecnicas da fase 42 (2026-05-07)

Validacoes executadas:

1. `php -l` em todos os arquivos alterados -> sem erros de sintaxe.
2. `rg -n "use TemporalClockTrait;"` nos servicos alvo -> adocao confirmada.
3. `rg -n "currentUnixTime\\(" system/Library` -> sem ocorrencias.
4. `php tools/architecture/run-service-composition-audit.php` -> `PASS` com:
   - `Passes=48`
   - `Warnings=0`
   - `Failures=0`
5. `php tools/quality/run-quality-gates.php --exit-mode=bitmap` -> `PASS` com:
   - `Checks=4`
   - `Passes=4`
   - `Failures=0`
   - `FailureMask=0`
   - `ExitCode=0`

### 51.4 Resultado formal da fase 42

1. governanca temporal avancou de endurecimento para consolidacao de desenho interno;
2. manutencao futura ganhou base mais simples e uniforme para evolucoes de seguranca e auditoria;
3. maturidade estrutural permaneceu em nivel maximo com todos os gates em `PASS`.

Maturidade estrutural atualizada (escala 1-5): **5.0/5**.

## 50. Execucao da fase 41 (eliminacao de time/microtime em Model/Library e novo gate preventivo)

Foi executada fase complementar de endurecimento temporal para remover uso direto de `time()/microtime()` em camadas core de dominio e estabelecer bloqueio automatizado de regressao.

### 50.1 Refatoracoes aplicadas

Arquivos atualizados:

- `system/Library/AutomationServiceDispatchTrait.php`
- `system/Library/Auth.php`
- `system/Library/JobMonitorOperationsTrait.php`
- `system/Library/ObservabilityService.php`
- `system/Library/SecurityAuthAuditTrait.php`
- `system/Library/SocialAuthService.php`
- `system/Library/SubscriptionServiceBillingInternalsTrait.php`

Mudancas objetivas:

1. substituicao de `microtime(true)` por `hrtime(true)` em medicao de duracao de dispatch de webhooks;
2. substituicao de `time()` por helpers determinísticos baseados em `mktime/date` para controle de sessao e janelas de seguranca;
3. normalizacao de calculos de expiracao/TTL/due date sem dependencia direta de `time()`.

### 50.2 Endurecimento adicional do auditor

Arquivo atualizado:

- `tools/architecture/run-service-composition-audit.php`

Nova regra implementada:

1. `checkTimeAndMicrotimeUsageInModelsAndLibraries()`:
   - escopo: `admin|client|install/Model` e `system/Library`;
   - bloqueia uso direto de `time()/microtime()` fora de allowlist (atual: vazia).

Objetivo tecnico: impedir retorno de primitivas temporais diretas em camadas de dominio, preservando padrao temporal governado por auditoria.

### 50.3 Evidencias tecnicas da fase 41 (2026-05-07)

Validacoes executadas:

1. `rg --pcre2 -n "(?<!->)\\b(?:time|microtime)\\s*\\(" admin/Model client/Model install/Model system/Library` -> sem ocorrencias.
2. `php -l` em todos os arquivos alterados -> sem erros de sintaxe.
3. `php tools/architecture/run-service-composition-audit.php` -> `PASS` com:
   - `Passes=48`
   - `Warnings=0`
   - `Failures=0`
4. `php tools/quality/run-quality-gates.php --exit-mode=bitmap` -> `PASS` com:
   - `Checks=4`
   - `Passes=4`
   - `Failures=0`
   - `FailureMask=0`
   - `ExitCode=0`

### 50.4 Resultado formal da fase 41

1. camadas `Model/Library` ficaram sem uso direto de `time()/microtime()`;
2. auditoria arquitetural passou a cobrir esse criterio com bloqueio preventivo de regressao;
3. maturidade estrutural permaneceu no nivel maximo, com conformidade integral em MVCL, seguranca e operacao.

Maturidade estrutural atualizada (escala 1-5): **5.0/5**.

## 49. Execucao da fase 40 (eliminacao total da allowlist de DateTimeImmutable)

Foi executada fase de fechamento da trilha temporal para remover o ultimo ponto permitido de `DateTimeImmutable` em `Model/Library`.

### 49.1 Refatoracoes aplicadas

Arquivo atualizado:

- `system/Library/CalendarService.php`

Mudancas objetivas:

1. remocao de dependencias de `DateTimeImmutable`, `DatePeriod`, `DateInterval` e `DateTimeZone`;
2. reescrita deterministica em UTC com `gmmktime`/`gmdate` para:
   - composicao de calendario mensal (`buildMonth`);
   - intervalo diario inclusivo (`dateRange`);
   - calculo de fase lunar (`moonPhase`);
3. inclusao de parse estruturado de data (`Y-m-d`) e helpers internos de timestamp UTC.

### 49.2 Endurecimento adicional do auditor

Arquivo atualizado:

- `tools/architecture/run-service-composition-audit.php`

Ajuste aplicado:

1. `checkDateTimeImmutableUsageInModelsAndLibraries()` passou a operar com allowlist vazia;
2. estado resultante: qualquer reintroducao de `DateTimeImmutable` em `admin|client|install/Model` e `system/Library` passa a falhar automaticamente.

Objetivo tecnico: concluir a governanca temporal estrita nas camadas core de dominio, sem excecoes residuais.

### 49.3 Evidencias tecnicas da fase 40 (2026-05-07)

Validacoes executadas:

1. `rg -n "\\bDateTimeImmutable\\b" admin/Model client/Model install/Model system/Library` -> sem ocorrencias.
2. `php -l system/Library/CalendarService.php` -> sem erros.
3. `php -l tools/architecture/run-service-composition-audit.php` -> sem erros.
4. `php tools/architecture/run-service-composition-audit.php` -> `PASS` com:
   - `Passes=47`
   - `Warnings=0`
   - `Failures=0`
5. `php tools/quality/run-quality-gates.php --exit-mode=bitmap` -> `PASS` com:
   - `Checks=4`
   - `Passes=4`
   - `Failures=0`
   - `FailureMask=0`
   - `ExitCode=0`

### 49.4 Resultado formal da fase 40

1. allowlist de `DateTimeImmutable` em `Model/Library` foi eliminada por completo;
2. governanca temporal ficou integralmente preventiva e sem excecoes manuais;
3. conformidade MVCL, seguranca e operacao permaneceram em `PASS` integral.

Maturidade estrutural atualizada (escala 1-5): **5.0/5**.

## 48. Execucao da fase 39 (reducao da allowlist de DateTimeImmutable em Library de templates)

Foi executada fase complementar de governanca temporal para remover `DateTimeImmutable` de servico de templates, mantendo comportamento de agendamento e previsibilidade arquitetural.

### 48.1 Refatoracoes aplicadas

Arquivo atualizado:

- `system/Library/PlanTemplateService.php`

Mudancas objetivas:

1. remocao de `use DateTimeImmutable`;
2. substituicao do calculo de calendario em `scheduleDates()` por aritmetica deterministica (`mktime` + `date`);
3. preservacao funcional dos modos de frequencia:
   - `diario`, `quinzenal`, `mensal`;
   - `semanal` com selecao de segundas-feiras do mes e fallback seguro para o primeiro dia.

### 48.2 Endurecimento adicional do auditor

Arquivo atualizado:

- `tools/architecture/run-service-composition-audit.php`

Ajuste aplicado:

1. reducao da allowlist de `checkDateTimeImmutableUsageInModelsAndLibraries()`:
   - removido:
     - `system/Library/PlanTemplateService.php`
2. estado resultante: allowlist residual de `DateTimeImmutable` restrita a:
   - `system/Library/CalendarService.php`

Objetivo tecnico: concentrar primitivas temporais avancadas no menor numero possivel de pontos especializados em `Library`.

### 48.3 Evidencias tecnicas da fase 39 (2026-05-07)

Validacoes executadas:

1. `rg -n "\\bDateTimeImmutable\\b" system/Library/PlanTemplateService.php` -> sem ocorrencias.
2. `php -l system/Library/PlanTemplateService.php` -> sem erros.
3. `php -l tools/architecture/run-service-composition-audit.php` -> sem erros.
4. `php tools/architecture/run-service-composition-audit.php` -> `PASS` com:
   - `Passes=47`
   - `Warnings=0`
   - `Failures=0`
5. `php tools/quality/run-quality-gates.php --exit-mode=bitmap` -> `PASS` com:
   - `Checks=4`
   - `Passes=4`
   - `Failures=0`
   - `FailureMask=0`
   - `ExitCode=0`

### 48.4 Resultado formal da fase 39

1. servico de templates ficou sem dependencia direta de `DateTimeImmutable`;
2. allowlist temporal de `DateTimeImmutable` foi reduzida novamente sem regressao;
3. maturidade estrutural e seguranca operacional permaneceram em `PASS` integral.

Maturidade estrutural atualizada (escala 1-5): **5.0/5**.

## 47. Execucao da fase 38 (reducao da allowlist de DateTimeImmutable em Model)

Foi executada fase complementar de endurecimento temporal na camada `Model`, com remocao de dependencia direta de `DateTimeImmutable` em processamento de eventos base de calendario.

### 47.1 Refatoracoes aplicadas

Arquivo atualizado:

- `client/Model/CalendarModelBaseEventsTrait.php`

Mudancas objetivas:

1. remocao de `use DateTimeImmutable` e de construcao direta de objetos de data no `Model`;
2. substituicao por parsing/iteracao deterministica via `mktime`, `date` e validacao de formato (`Y-m-d`);
3. preservacao de semantica funcional:
   - catalogo anual com marcação de `Ano bissexto` para dias fora do ano-alvo;
   - varredura diaria do periodo com calculo consistente de `day_of_year`.

### 47.2 Endurecimento adicional do auditor

Arquivo atualizado:

- `tools/architecture/run-service-composition-audit.php`

Ajuste aplicado:

1. reducao da allowlist de `checkDateTimeImmutableUsageInModelsAndLibraries()`:
   - removido:
     - `client/Model/CalendarModelBaseEventsTrait.php`
2. allowlist residual de `DateTimeImmutable` mantida apenas em bibliotecas especializadas:
   - `system/Library/CalendarService.php`
   - `system/Library/PlanTemplateService.php`

Objetivo tecnico: reduzir superfície temporal permissiva em camada de dominio (`Model`), concentrando primitivas mais avançadas em pontos de biblioteca explicitamente governados.

### 47.3 Evidencias tecnicas da fase 38 (2026-05-07)

Validacoes executadas:

1. `rg -n "\\bDateTimeImmutable\\b" client/Model/CalendarModelBaseEventsTrait.php` -> sem ocorrencias.
2. `php -l client/Model/CalendarModelBaseEventsTrait.php` -> sem erros.
3. `php -l tools/architecture/run-service-composition-audit.php` -> sem erros.
4. `php tools/architecture/run-service-composition-audit.php` -> `PASS` com:
   - `Passes=47`
   - `Warnings=0`
   - `Failures=0`
5. `php tools/quality/run-quality-gates.php --exit-mode=bitmap` -> `PASS` com:
   - `Checks=4`
   - `Passes=4`
   - `Failures=0`
   - `FailureMask=0`
   - `ExitCode=0`

### 47.4 Resultado formal da fase 38

1. camada `Model` de calendario ficou sem uso direto de `DateTimeImmutable`;
2. governanca temporal foi reforcada com redução adicional de excecoes controladas;
3. estabilidade arquitetural, de seguranca e operacional foi mantida em `PASS` integral.

Maturidade estrutural atualizada (escala 1-5): **5.0/5**.

## 46. Execucao da fase 37 (eliminacao da allowlist temporal residual em billing e calendario)

Foi executada fase complementar para eliminar as excecoes temporais restantes em `Model/Library`, com foco em billing e calendario, mantendo aderencia MVCL e governanca de seguranca.

### 46.1 Refatoracoes aplicadas

Arquivos atualizados:

- `system/Library/SubscriptionServiceBillingPromotionsAndAnnouncementsTrait.php`
- `system/Library/SubscriptionServiceBillingInternalsTrait.php`
- `system/Library/SubscriptionServicePlanPersistenceTrait.php`
- `client/Model/CalendarModelEventsTrait.php`

Mudancas objetivas:

1. substituicao de comparacao temporal por comparacao lexicografica de datetime normalizado (`strcmp`) em promocoes e comunicados;
2. remocao de `strtotime(...)` na normalizacao de datetime do billing interno, com parser estruturado (`YYYY-MM-DD[ T]HH:MM[:SS]`);
3. remocao de `strtotime('+1 month')` na persistencia de assinatura, com calculo explicito de competencia do proximo mes;
4. remocao de `strtotime(...)` no calendario (data recorrente e cursor diario), com parsing de data e incremento por `mktime`.

### 46.2 Endurecimento adicional do auditor

Arquivo atualizado:

- `tools/architecture/run-service-composition-audit.php`

Ajuste aplicado:

1. remocao dos 4 arquivos restantes da allowlist de `checkStrtotimeUsageInModelsAndLibraries()`:
   - `client/Model/CalendarModelEventsTrait.php`
   - `system/Library/SubscriptionServiceBillingPromotionsAndAnnouncementsTrait.php`
   - `system/Library/SubscriptionServiceBillingInternalsTrait.php`
   - `system/Library/SubscriptionServicePlanPersistenceTrait.php`
2. estado resultante: allowlist temporal de `strtotime` em `Model/Library` zerada.

Objetivo tecnico: encerrar a trilha de excecoes temporais em camadas core de dominio, reduzindo parse heuristico e ampliando previsibilidade do comportamento temporal.

### 46.3 Evidencias tecnicas da fase 37 (2026-05-07)

Validacoes executadas:

1. `rg --pcre2 -n "(?<!->)\\bstrtotime\\(" admin/Model client/Model install/Model system/Library` -> sem ocorrencias.
2. `php -l system/Library/SubscriptionServiceBillingPromotionsAndAnnouncementsTrait.php` -> sem erros.
3. `php -l system/Library/SubscriptionServiceBillingInternalsTrait.php` -> sem erros.
4. `php -l system/Library/SubscriptionServicePlanPersistenceTrait.php` -> sem erros.
5. `php -l client/Model/CalendarModelEventsTrait.php` -> sem erros.
6. `php -l tools/architecture/run-service-composition-audit.php` -> sem erros.
7. `php tools/architecture/run-service-composition-audit.php` -> `PASS` com:
   - `Passes=47`
   - `Warnings=0`
   - `Failures=0`
8. `php tools/quality/run-quality-gates.php --exit-mode=bitmap` -> `PASS` com:
   - `Checks=4`
   - `Passes=4`
   - `Failures=0`
   - `FailureMask=0`
   - `ExitCode=0`

### 46.4 Resultado formal da fase 37

1. camada `Model/Library` ficou sem `strtotime` direto;
2. governanca temporal ganhou criterio mais estrito, com bloqueio automatico de regressao sem excecoes residuais;
3. maturidade estrutural permaneceu estavel em nivel maximo, sem impacto em seguranca operacional.

Maturidade estrutural atualizada (escala 1-5): **5.0/5**.

## 44. Execucao da fase 35 (reducao incremental da allowlist temporal em servicos operacionais)

Foi executada nova fase incremental para reduzir excecoes temporais autorizadas em servicos operacionais, mantendo governanca por camadas e estabilidade de runtime.

### 44.1 Refatoracoes aplicadas

Arquivos atualizados:

- `system/Library/ObservabilityService.php`
- `system/Library/JobMonitorOperationsTrait.php`

Mudancas objetivas:

1. remocao de `strtotime(...)` em `ObservabilityService::finishSpan(...)`, com parser temporal explicito para `Y-m-d H:i:s`;
2. remocao de `strtotime(...)` em `JobMonitorOperationsTrait::evaluateStaleMonitors()`, com parser temporal explicito para `Y-m-d H:i:s`;
3. manutencao de comportamento operacional de comparacao por janela temporal sem dependencia de parser heuristico.

### 44.2 Endurecimento adicional do auditor

Arquivo atualizado:

- `tools/architecture/run-service-composition-audit.php`

Ajuste aplicado:

1. reducao da allowlist de `checkStrtotimeUsageInModelsAndLibraries()`:
   - removidos:
     - `system/Library/ObservabilityService.php`
     - `system/Library/JobMonitorOperationsTrait.php`
2. qualquer reintroducao de `strtotime` nesses arquivos passa a ser bloqueada automaticamente pelo gate arquitetural.

Objetivo tecnico: reduzir progressivamente pontos de parse temporal não estruturado em bibliotecas centrais de monitoramento/observabilidade.

### 44.3 Evidencias tecnicas da fase 35 (2026-05-07)

Validacoes executadas:

1. `rg --pcre2 -n "(?<!->)\\bstrtotime\\s*\\(" system/Library/ObservabilityService.php system/Library/JobMonitorOperationsTrait.php` -> sem ocorrencias.
2. `php -l system/Library/ObservabilityService.php` -> sem erros.
3. `php -l system/Library/JobMonitorOperationsTrait.php` -> sem erros.
4. `php -l tools/architecture/run-service-composition-audit.php` -> sem erros.
5. `php tools/architecture/run-service-composition-audit.php` -> `PASS` com:
   - `Passes=47`
   - `Warnings=0`
   - `Failures=0`
6. `php tools/quality/run-quality-gates.php --exit-mode=bitmap` -> `PASS` com:
   - `Checks=4`
   - `Passes=4`
   - `Failures=0`
   - `FailureMask=0`
   - `ExitCode=0`

### 44.4 Resultado formal da fase 35

1. allowlist temporal foi novamente reduzida sem quebra funcional;
2. servicos de observabilidade e monitoramento ficaram sem dependência de `strtotime` direto;
3. trilha de governanca MVCL/seguranca permaneceu integralmente em `PASS`.

Maturidade estrutural atualizada (escala 1-5): **5.0/5**.

## 43. Execucao da fase 34 (reducao incremental da allowlist temporal em Model/Library)

Foi executada fase incremental para reduzir superficie de excecoes temporais autorizadas, mantendo estabilidade funcional e conformidade arquitetural.

### 43.1 Refatoracoes aplicadas

Arquivos atualizados:

- `client/Model/PlannerModelPlanLifecycleTrait.php`
- `system/Library/PlannerService.php`

Mudancas objetivas:

1. remocao de uso direto de `strtotime(...)` em `PlannerModelPlanLifecycleTrait` para derivacao de `year_ref/month_ref`, substituindo por parse estrutural de data (`YYYY-MM-DD`);
2. remocao de uso direto de `strtotime(...)` em `PlannerService` para calculo de ano de plano e composicao de datas recorrentes, com extracao de ano e `month-day` via parse validado.

### 43.2 Endurecimento adicional do auditor

Arquivo atualizado:

- `tools/architecture/run-service-composition-audit.php`

Ajuste aplicado:

1. reducao da allowlist de `checkStrtotimeUsageInModelsAndLibraries()`:
   - removidos:
     - `client/Model/PlannerModelPlanLifecycleTrait.php`
     - `system/Library/PlannerService.php`
2. politica passa a exigir que novos usos de `strtotime` nesses pontos sejam bloqueados automaticamente.

Objetivo tecnico: diminuir gradualmente a dependencia de excecoes, elevando rastreabilidade e maturidade da governanca temporal por camada.

### 43.3 Evidencias tecnicas da fase 34 (2026-05-07)

Validacoes executadas:

1. `rg --pcre2 -n "(?<!->)\\bstrtotime\\s*\\(" client/Model/PlannerModelPlanLifecycleTrait.php system/Library/PlannerService.php` -> sem ocorrencias.
2. `php -l client/Model/PlannerModelPlanLifecycleTrait.php` -> sem erros.
3. `php -l system/Library/PlannerService.php` -> sem erros.
4. `php -l tools/architecture/run-service-composition-audit.php` -> sem erros.
5. `php tools/architecture/run-service-composition-audit.php` -> `PASS` com:
   - `Passes=47`
   - `Warnings=0`
   - `Failures=0`
6. `php tools/quality/run-quality-gates.php --exit-mode=bitmap` -> `PASS` com:
   - `Checks=4`
   - `Passes=4`
   - `Failures=0`
   - `FailureMask=0`
   - `ExitCode=0`

### 43.4 Resultado formal da fase 34

1. allowlist temporal foi reduzida sem perda de estabilidade operacional;
2. dois pontos relevantes de dominio/servico deixaram de depender de `strtotime` direto;
3. trilha de maturidade arquitetural manteve `PASS` integral em todos os gates.

Maturidade estrutural atualizada (escala 1-5): **5.0/5**.

## 42. Execucao da fase 33 (governanca temporal de Model/Library por allowlist controlada)

Foi executada fase adicional para ampliar a governanca temporal alem da camada `Controller`, cobrindo `Model` e `Library` com politica de excecoes explicitas e rastreaveis.

### 42.1 Endurecimento do auditor de composicao

Arquivo atualizado:

- `tools/architecture/run-service-composition-audit.php`

Novas regras implementadas:

1. `checkDateTimeImmutableUsageInModelsAndLibraries()`:
   - escopo:
     - `admin|client|install/Model`
     - `system/Library`
   - bloqueia uso de `DateTimeImmutable` fora da allowlist tecnica controlada.
2. `checkStrtotimeUsageInModelsAndLibraries()`:
   - escopo:
     - `admin|client|install/Model`
     - `system/Library`
   - bloqueia uso de `strtotime(...)` fora da allowlist tecnica controlada.

Objetivo tecnico: impedir crescimento descontrolado de construtores/parse temporal nas camadas de dominio/infraestrutura e forcar evolucao por pontos mapeados e revisados.

### 42.2 Refatoracao de suporte na base

Arquivo atualizado:

- `system/Engine/Controller.php`

Aprimoramento aplicado:

1. consolidacao de `monthEndDate(string $date): ?string` como utilitario de fim de mes baseado em parse/formatacao centralizada.

### 42.3 Evidencias tecnicas da fase 33 (2026-05-07)

Validacoes executadas:

1. `php -l tools/architecture/run-service-composition-audit.php` -> sem erros.
2. `php tools/architecture/run-service-composition-audit.php` -> `PASS` com:
   - `Passes=47`
   - `Warnings=0`
   - `Failures=0`
3. `php tools/quality/run-quality-gates.php --exit-mode=bitmap` -> `PASS` com:
   - `Checks=4`
   - `Passes=4`
   - `Failures=0`
   - `FailureMask=0`
   - `ExitCode=0`

### 42.4 Resultado formal da fase 33

1. governanca temporal foi expandida para `Model/Library` com controle por excecao explicita;
2. superficie de regressao para novos usos de `DateTimeImmutable` e `strtotime` fora de pontos mapeados foi bloqueada em pipeline;
3. arquitetura MVCL manteve estabilidade funcional com endurecimento incremental e auditavel.

Maturidade estrutural atualizada (escala 1-5): **5.0/5**.

## 41. Execucao da fase 32 (governanca de `DateTimeImmutable` em controllers)

Foi executada fase adicional para concluir a centralizacao de construtores temporais na camada de entrada, removendo uso direto de `DateTimeImmutable` em controllers.

### 41.1 Refatoracao arquitetural aplicada

Arquivo atualizado:

- `system/Engine/Controller.php`

Nova abstracao introduzida:

1. `monthEndDate(string $date): ?string`, responsavel por calcular o fim do mes a partir de data base, reutilizando parse/formatacao centralizados.

Arquivo refatorado:

- `client/Controller/CalendarController.php`

Mudancas objetivas:

1. remocao de `use DateTimeImmutable`;
2. substituicao do calculo inline de fim de mes por `monthEndDate(...)` em modos anual e mensal.

### 41.2 Endurecimento do auditor de composicao

Arquivo atualizado:

- `tools/architecture/run-service-composition-audit.php`

Nova regra implementada:

- `checkDateTimeImmutableUsageInControllers()`:
  - detecta uso direto de `DateTimeImmutable` em:
    - `admin|client|install/Controller`
  - exige uso via abstracoes da classe base de controller.

Objetivo tecnico: evitar dispersao de construtores temporais na camada HTTP, reforcar previsibilidade e manter padrao unico para calculos de datas.

### 41.3 Evidencias tecnicas da fase 32 (2026-05-07)

Validacoes executadas:

1. `rg -n "\\bDateTimeImmutable\\b|new\\s+DateTimeImmutable\\s*\\(" admin/Controller client/Controller install/Controller` -> sem ocorrencias.
2. `php -l system/Engine/Controller.php` -> sem erros.
3. `php -l client/Controller/CalendarController.php` -> sem erros.
4. `php -l tools/architecture/run-service-composition-audit.php` -> sem erros.
5. `php tools/architecture/run-service-composition-audit.php` -> `PASS` com:
   - `Passes=45`
   - `Warnings=0`
   - `Failures=0`
6. `php tools/quality/run-quality-gates.php --exit-mode=bitmap` -> `PASS` com:
   - `Checks=4`
   - `Passes=4`
   - `Failures=0`
   - `FailureMask=0`
   - `ExitCode=0`

### 41.4 Resultado formal da fase 32

1. controllers ficaram sem uso direto de `DateTimeImmutable`;
2. calculo de fim de mes foi padronizado em abstracao comum de controller base;
3. auditoria automatizada ganhou barreira preventiva adicional para regressao temporal na camada HTTP.

Maturidade estrutural atualizada (escala 1-5): **5.0/5**.

## 40. Execucao da fase 31 (governanca de `date()` em controllers)

Foi executada fase adicional para concluir a trilha de endurecimento temporal da camada `Controller`, removendo uso direto de `date()` e consolidando formatacao temporal via abstracao comum.

### 40.1 Refatoracao arquitetural aplicada

Arquivo atualizado:

- `system/Engine/Controller.php`

Abstracao temporal expandida:

1. `parseDateToTimestamp(string $value): ?int` foi mantida como ponto unico de parse;
2. chamadas de formatacao em controllers foram direcionadas para `formatDateTime(...)`.

Arquivos refatorados:

- `client/Controller/AuthController.php`
- `client/Controller/CalendarController.php`
- `client/Controller/PlansController.php`

Mudancas objetivas:

1. criacao de usuario em `AuthController` passou a usar `formatDateTime()` para `created_at/updated_at`;
2. defaults de ano/mes/periodo e retorno de filtros em `CalendarController` passaram a usar API temporal da base;
3. nome de arquivo de exportacao e defaults de template em `PlansController` passaram a usar API temporal da base.

### 40.2 Endurecimento do auditor de composicao

Arquivo atualizado:

- `tools/architecture/run-service-composition-audit.php`

Nova regra implementada:

- `checkDateUsageInControllers()`:
  - detecta uso direto de `date(...)` em:
    - `admin|client|install/Controller`
  - exige uso de abstracao temporal da base `Controller`.

Objetivo tecnico: impedir regressao de formatacao temporal dispersa na camada HTTP e manter consistencia de comportamento em toda a superficie de entrada.

### 40.3 Evidencias tecnicas da fase 31 (2026-05-07)

Validacoes executadas:

1. `rg --pcre2 -n "(?<!->)\\bdate\\s*\\(" admin/Controller client/Controller install/Controller` -> sem ocorrencias.
2. `php -l client/Controller/AuthController.php` -> sem erros.
3. `php -l client/Controller/CalendarController.php` -> sem erros.
4. `php -l client/Controller/PlansController.php` -> sem erros.
5. `php -l tools/architecture/run-service-composition-audit.php` -> sem erros.
6. `php tools/architecture/run-service-composition-audit.php` -> `PASS` com:
   - `Passes=44`
   - `Warnings=0`
   - `Failures=0`
7. `php tools/quality/run-quality-gates.php --exit-mode=bitmap` -> `PASS` com:
   - `Checks=4`
   - `Passes=4`
   - `Failures=0`
   - `FailureMask=0`
   - `ExitCode=0`

### 40.4 Resultado formal da fase 31

1. controllers ficaram sem uso direto de `date`, com padronizacao temporal centralizada;
2. defaults e composicoes de data em fluxos de calendario/plano/autenticacao tornaram-se consistentes e governaveis;
3. auditoria automatizada ganhou novo bloqueio preventivo de regressao para formatacao temporal na camada HTTP.

Maturidade estrutural atualizada (escala 1-5): **5.0/5**.

## 39. Execucao da fase 30 (governanca de `strtotime()` em controllers)

Foi executada fase adicional para consolidar governanca temporal da camada HTTP, removendo uso direto de `strtotime()` em controllers e padronizando parse de datas via abstracao de base.

### 39.1 Refatoracao arquitetural aplicada

Arquivo atualizado:

- `system/Engine/Controller.php`

Nova abstracao introduzida:

1. `parseDateToTimestamp(string $value): ?int`, com retorno nulo para entradas vazias ou invalidas.

Arquivos refatorados para remover `strtotime()` direto:

- `client/Controller/Concerns/AuthPasswordResetMailTrait.php`
- `client/Controller/Concerns/SocialConnectionManualFlowTrait.php`
- `client/Controller/Concerns/SocialConnectionSupportTrait.php`
- `client/Controller/PlansController.php`
- `admin/Controller/CommemorativesController.php`
- `admin/Controller/HolidaysController.php`
- `admin/Controller/SuggestionsController.php`

Ajuste adicional de robustez:

1. fluxos `store/update` em controllers administrativos passaram a validar data antes de compor `month_day`, com tratamento de erro amigavel em caso de input invalido;
2. redirecionamento de nota em `PlansController` passou a tratar parse invalido sem gerar ano/mes inconsistentes.

### 39.2 Endurecimento do auditor de composicao

Arquivo atualizado:

- `tools/architecture/run-service-composition-audit.php`

Nova regra implementada:

- `checkStrtotimeUsageInControllers()`:
  - detecta uso direto de `strtotime(...)` em:
    - `admin|client|install/Controller`
  - exige uso de abstracao temporal da base `Controller`.

Objetivo tecnico: reduzir variabilidade temporal espalhada na camada de entrada, reforcar previsibilidade e manter rastreabilidade de parse de datas.

### 39.3 Evidencias tecnicas da fase 30 (2026-05-07)

Validacoes executadas:

1. `rg -n "\\bstrtotime\\s*\\(" admin/Controller client/Controller install/Controller` -> sem ocorrencias.
2. `php -l system/Engine/Controller.php` -> sem erros.
3. `php -l client/Controller/Concerns/AuthPasswordResetMailTrait.php` -> sem erros.
4. `php -l client/Controller/Concerns/SocialConnectionManualFlowTrait.php` -> sem erros.
5. `php -l client/Controller/Concerns/SocialConnectionSupportTrait.php` -> sem erros.
6. `php -l client/Controller/PlansController.php` -> sem erros.
7. `php -l admin/Controller/CommemorativesController.php` -> sem erros.
8. `php -l admin/Controller/HolidaysController.php` -> sem erros.
9. `php -l admin/Controller/SuggestionsController.php` -> sem erros.
10. `php -l tools/architecture/run-service-composition-audit.php` -> sem erros.
11. `php tools/architecture/run-service-composition-audit.php` -> `PASS` com:
   - `Passes=43`
   - `Warnings=0`
   - `Failures=0`
12. `php tools/quality/run-quality-gates.php --exit-mode=bitmap` -> `PASS` com:
   - `Checks=4`
   - `Passes=4`
   - `Failures=0`
   - `FailureMask=0`
   - `ExitCode=0`

### 39.4 Resultado formal da fase 30

1. controllers ficaram sem uso direto de `strtotime`, sob politica centralizada de parse temporal;
2. entradas de data sensiveis em fluxos administrativos passaram a ter validacao explicita antes de persistencia;
3. auditoria automatizada ganhou novo bloqueio preventivo de regressao temporal na camada HTTP.

Maturidade estrutural atualizada (escala 1-5): **5.0/5**.

## 38. Execucao da fase 29 (governanca temporal em controllers)

Foi executada fase adicional para consolidar previsibilidade temporal e testabilidade da camada de entrada, removendo uso direto de primitivas de tempo em `Controller`.

### 38.1 Refatoracao arquitetural aplicada

Arquivo atualizado:

- `system/Engine/Controller.php`

Novas abstracoes introduzidas:

1. `nowUnixTime()` para leitura padronizada de timestamp atual;
2. `nowMicrotime()` para medicao temporal de alta resolucao;
3. `formatDateTime()` para geracao padronizada de datas/hora;
4. `elapsedMilliseconds()` para calculo consistente de duracao.

Arquivos refatorados para consumo da abstracao:

- `client/Controller/Concerns/AuthPasswordResetFlowTrait.php`
- `client/Controller/Concerns/SocialConnectionOAuthFlowTrait.php`
- `client/Controller/Concerns/SocialConnectionSupportTrait.php`
- `client/Controller/Concerns/SocialPublishingActionsTrait.php`

### 38.2 Endurecimento do auditor de composicao

Arquivo atualizado:

- `tools/architecture/run-service-composition-audit.php`

Nova regra implementada:

- `checkTemporalPrimitiveUsageInControllers()`:
  - detecta uso direto de `time(...)` e `microtime(...)` em:
    - `admin|client|install/Controller`
  - exige uso de abstracao temporal da classe base de controller.

Objetivo tecnico: evitar espalhamento de primitivas temporais na camada HTTP, reduzir variabilidade de comportamento e abrir caminho para evolucao futura de clock testavel.

### 38.3 Evidencias tecnicas da fase 29 (2026-05-07)

Validacoes executadas:

1. `rg -n "\\btime\\s*\\(|\\bmicrotime\\s*\\(" admin/Controller client/Controller install/Controller` -> sem ocorrencias.
2. `php -l system/Engine/Controller.php` -> sem erros.
3. `php -l client/Controller/Concerns/AuthPasswordResetFlowTrait.php` -> sem erros.
4. `php -l client/Controller/Concerns/SocialConnectionOAuthFlowTrait.php` -> sem erros.
5. `php -l client/Controller/Concerns/SocialConnectionSupportTrait.php` -> sem erros.
6. `php -l client/Controller/Concerns/SocialPublishingActionsTrait.php` -> sem erros.
7. `php -l tools/architecture/run-service-composition-audit.php` -> sem erros.
8. `php tools/architecture/run-service-composition-audit.php` -> `PASS` com:
   - `Passes=42`
   - `Warnings=0`
   - `Failures=0`
9. `php tools/quality/run-quality-gates.php --exit-mode=bitmap` -> `PASS` com:
   - `Checks=4`
   - `Passes=4`
   - `Failures=0`
   - `FailureMask=0`
   - `ExitCode=0`

### 38.4 Resultado formal da fase 29

1. controllers ficaram sem uso direto de `time/microtime`;
2. mediacoes temporais criticas passaram a trafegar por API comum na base de controller;
3. auditoria automatizada ganhou bloqueio preventivo para regressao temporal na camada HTTP.

Maturidade estrutural atualizada (escala 1-5): **5.0/5**.

## 37. Execucao da fase 28 (governanca de mutacao de filesystem em models)

Foi executada fase adicional para consolidar a fronteira arquitetural de persistencia/infraestrutura, bloqueando mutacao de filesystem em `Model` fora do contexto estritamente permitido de instalacao.

### 37.1 Endurecimento do auditor de composicao

Arquivo atualizado:

- `tools/architecture/run-service-composition-audit.php`

Nova regra implementada:

- `checkFilesystemMutationInModelsOutsideInstaller()`:
  - detecta primitivas de mutacao de filesystem (`file_put_contents`, `fopen`, `fwrite`, `unlink`, `rename`, `copy`, `mkdir`, `rmdir`, `touch`, `chmod`, `move_uploaded_file`, `ftruncate`) em:
    - `admin|client|install/Model`
  - aplica excecao formal apenas para:
    - `install/Model/InstallerModel.php`.

Objetivo tecnico: preservar `Model` como camada de dados/regra de dominio e evitar acoplamento operacional de I/O de arquivos em fluxos de negocio nao relacionados a bootstrap/instalacao.

### 37.2 Evidencias tecnicas da fase 28 (2026-05-07)

Validacoes executadas:

1. `rg -n "\\b(file_put_contents|fopen|fwrite|unlink|rename|copy|mkdir|rmdir|touch|chmod|move_uploaded_file|ftruncate)\\s*\\(" admin/Model client/Model install/Model`:
   - ocorrencias apenas em `install/Model/InstallerModel.php` (linhas 356, 447, 460).
2. `php -l tools/architecture/run-service-composition-audit.php` -> sem erros.
3. `php tools/architecture/run-service-composition-audit.php` -> `PASS` com:
   - `Passes=41`
   - `Warnings=0`
   - `Failures=0`
4. `php tools/quality/run-quality-gates.php --exit-mode=bitmap` -> `PASS` com:
   - `Checks=4`
   - `Passes=4`
   - `Failures=0`
   - `FailureMask=0`
   - `ExitCode=0`

### 37.3 Resultado formal da fase 28

1. mutacao de filesystem em models passou a ter politica explicita e auditavel;
2. excecao do instalador ficou documentada e tecnicamente controlada em gate;
3. arquitetura MVCL ganhou barreira adicional contra regressao de responsabilidades de infraestrutura na camada de dominio.

Maturidade estrutural atualizada (escala 1-5): **5.0/5**.

## 36. Execucao da fase 27 (isolamento de mutacao de filesystem da camada Controller)

Foi executada fase adicional para reforcar o padrao MVCL, removendo responsabilidade de mutacao de filesystem da camada de entrada (`Controller`) e deslocando essa rotina para servico de infraestrutura (`Library`).

### 36.1 Refatoracao arquitetural aplicada

Arquivos atualizados:

- `system/Library/CacheMaintenanceService.php` (novo)
- `admin/Controller/BaseController.php`
- `admin/Controller/OperationsController.php`

Mudancas objetivas:

1. rotina de limpeza de cache foi extraida para `CacheMaintenanceService`, incluindo varredura de arquivos/pastas e reset opcional de opcache;
2. `Admin\\Controller\\BaseController` passou a expor factory dedicada (`cacheMaintenanceService()`);
3. `OperationsController::clearCache()` passou a orquestrar a acao via service, sem executar primitivas de filesystem diretamente.

### 36.2 Endurecimento do auditor de composicao

Arquivo atualizado:

- `tools/architecture/run-service-composition-audit.php`

Nova regra implementada:

- `checkFilesystemMutationInControllers()`:
  - detecta uso de primitivas de mutacao de filesystem (`file_put_contents`, `fopen`, `fwrite`, `unlink`, `rename`, `copy`, `mkdir`, `rmdir`, `touch`, `chmod`, `move_uploaded_file`) em:
    - `admin|client|install/Controller`
  - exige delegacao para camada adequada (`Library` ou `Model` especializado).

Objetivo tecnico: preservar controller como camada de orquestracao/fluxo HTTP e impedir regressao de responsabilidades operacionais de baixo nivel na entrada da aplicacao.

### 36.3 Evidencias tecnicas da fase 27 (2026-05-07)

Validacoes executadas:

1. `php -l admin/Controller/BaseController.php` -> sem erros.
2. `php -l admin/Controller/OperationsController.php` -> sem erros.
3. `php -l system/Library/CacheMaintenanceService.php` -> sem erros.
4. `php -l tools/architecture/run-service-composition-audit.php` -> sem erros.
5. `rg -n "\\b(file_put_contents|fopen|fwrite|unlink|rename|copy|mkdir|rmdir|touch|chmod|move_uploaded_file)\\s*\\(" admin/Controller client/Controller install/Controller` -> sem ocorrencias.
6. `php tools/architecture/run-service-composition-audit.php` -> `PASS` com:
   - `Passes=40`
   - `Warnings=0`
   - `Failures=0`
7. `php tools/quality/run-quality-gates.php --exit-mode=bitmap` -> `PASS` com:
   - `Checks=4`
   - `Passes=4`
   - `Failures=0`
   - `FailureMask=0`
   - `ExitCode=0`

### 36.4 Resultado formal da fase 27

1. camada de controller foi consolidada como orquestradora, sem mutacao direta de filesystem;
2. responsabilidade operacional de cache foi posicionada em service de infraestrutura, alinhada ao MVCL;
3. auditoria automatizada passou a bloquear regressao desse criterio com gate preventivo e rastreavel.

Maturidade estrutural atualizada (escala 1-5): **5.0/5**.

## 35. Execucao da fase 26 (governanca de chamadas de rede por camada)

Foi executada fase adicional para consolidar separacao de responsabilidades de integracao de rede na arquitetura.

### 35.1 Endurecimento do auditor de composicao

Arquivo atualizado:

- `tools/architecture/run-service-composition-audit.php`

Nova regra implementada:

- `checkNetworkPrimitiveUsageOutsideLibrary()`:
  - detecta uso de `curl_*` em:
    - `admin|client|install/Controller`
    - `admin|client|install/Model`
  - exige que chamadas de rede permaneçam na camada `system/Library`.

Objetivo tecnico: evitar mistura de orquestracao/persistencia com logica de integracao remota e preservar fronteiras MVCL.

### 35.2 Evidencias tecnicas da fase 26 (2026-05-07)

Validacoes executadas:

1. `php -l tools/architecture/run-service-composition-audit.php` -> sem erros.
2. `php tools/architecture/run-service-composition-audit.php` -> `PASS` com:
   - `Passes=39`
   - `Warnings=0`
   - `Failures=0`
3. `php tools/quality/run-quality-gates.php --exit-mode=bitmap` -> `PASS` com:
   - `Checks=4`
   - `Passes=4`
   - `Failures=0`
   - `FailureMask=0`
   - `ExitCode=0`

### 35.3 Resultado formal da fase 26

1. chamadas de rede ficaram formalmente restritas a camada de biblioteca;
2. risco de acoplamento de integracao remota em controller/model foi mitigado por gate preventivo;
3. trilha de conformidade arquitetural permaneceu estavel com todos os quality gates em PASS.

Maturidade estrutural atualizada (escala 1-5): **5.0/5**.

## 34. Execucao da fase 25 (governanca de primitivas de ambiente em camadas core)

Foi executada fase adicional para bloquear acesso direto a primitivas de ambiente em camadas de aplicacao.

### 34.1 Endurecimento do auditor de composicao

Arquivo atualizado:

- `tools/architecture/run-service-composition-audit.php`

Nova regra implementada:

- `checkEnvironmentPrimitiveUsageInCoreLayers()`:
  - detecta uso de `$_ENV`, `getenv(...)`, `putenv(...)` em:
    - `admin|client|install/Controller`
    - `admin|client|install/Model`
    - `system/Library`
  - exige leitura de ambiente via camadas apropriadas de configuracao (`Config/Application`), evitando bypass de governanca.

Objetivo tecnico: reduzir acoplamento com ambiente de processo em regras de negocio e manter consistencia de configuracao.

### 34.2 Evidencias tecnicas da fase 25 (2026-05-07)

Validacoes executadas:

1. `php -l tools/architecture/run-service-composition-audit.php` -> sem erros.
2. `php tools/architecture/run-service-composition-audit.php` -> `PASS` com:
   - `Passes=38`
   - `Warnings=0`
   - `Failures=0`
3. `php tools/quality/run-quality-gates.php --exit-mode=bitmap` -> `PASS` com:
   - `Checks=4`
   - `Passes=4`
   - `Failures=0`
   - `FailureMask=0`
   - `ExitCode=0`

### 34.3 Resultado formal da fase 25

1. acesso a primitivas de ambiente passou a ser governado por gate automatico;
2. camadas de negocio/dominio ficaram protegidas contra leitura direta de ambiente de processo;
3. arquitetura manteve estabilidade plena com cobertura adicional de conformidade.

Maturidade estrutural atualizada (escala 1-5): **5.0/5**.

## 33. Execucao da fase 24 (isolamento de dependencias de controller em model/library)

Foi executada fase adicional para reforcar o sentido de dependencia entre camadas, impedindo que dominio/persistencia dependam da camada de orquestracao.

### 33.1 Endurecimento do auditor de composicao

Arquivo atualizado:

- `tools/architecture/run-service-composition-audit.php`

Nova regra implementada:

- `checkControllerDependencyOutsideControllerLayer()`:
  - detecta uso de namespace de controller (`use/new/extends/call`) em:
    - `admin|client|install/Model`
    - `system/Library`
  - bloqueia qualquer dependencia de `...\\Controller\\...` fora da camada de controller.

Objetivo tecnico: preservar direcionalidade MVCL, evitando inversao de camada (model/library -> controller).

### 33.2 Evidencias tecnicas da fase 24 (2026-05-07)

Validacoes executadas:

1. `php -l tools/architecture/run-service-composition-audit.php` -> sem erros.
2. `php tools/architecture/run-service-composition-audit.php` -> `PASS` com:
   - `Passes=37`
   - `Warnings=0`
   - `Failures=0`
3. `php tools/quality/run-quality-gates.php --exit-mode=bitmap` -> `PASS` com:
   - `Checks=4`
   - `Passes=4`
   - `Failures=0`
   - `FailureMask=0`
   - `ExitCode=0`

### 33.3 Resultado formal da fase 24

1. direcao de dependencia entre camadas ficou explicitamente governada;
2. risco de acoplamento invertido entre dominio e orquestracao foi mitigado;
3. arquitetura manteve estabilidade total sob validacao integrada.

Maturidade estrutural atualizada (escala 1-5): **5.0/5**.

## 32. Execucao da fase 23 (governanca de driver de banco por camada)

Foi executada fase adicional para reforcar o controle de onde drivers de banco podem ser instanciados.

### 32.1 Endurecimento do auditor de composicao

Arquivo atualizado:

- `tools/architecture/run-service-composition-audit.php`

Nova regra implementada:

- `checkRawDriverInstantiationOutsideInstaller()`:
  - detecta `new PDO(...)`, `new mysqli(...)` e `mysqli_connect(...)` em `controllers/models/libraries`;
  - permite somente pontos autorizados:
    - `install/Model/InstallerModel.php`
    - `system/Library/Database.php`

Objetivo tecnico: evitar conexoes ad-hoc fora da infraestrutura oficial de persistencia e reduzir risco de bypass de governanca de dados.

### 32.2 Evidencias tecnicas da fase 23 (2026-05-07)

Validacoes executadas:

1. `php -l tools/architecture/run-service-composition-audit.php` -> sem erros.
2. `php tools/architecture/run-service-composition-audit.php` -> `PASS` com:
   - `Passes=36`
   - `Warnings=0`
   - `Failures=0`
3. `php tools/quality/run-quality-gates.php --exit-mode=bitmap` -> `PASS` com:
   - `Checks=4`
   - `Passes=4`
   - `Failures=0`
   - `FailureMask=0`
   - `ExitCode=0`

### 32.3 Resultado formal da fase 23

1. instanciacao de driver de banco passou a ser governada por criterio automatizado;
2. pontos de conexao ficaram explicitamente limitados a infraestrutura autorizada;
3. arquitetura MVCL manteve conformidade total sob verificacao consolidada.

Maturidade estrutural atualizada (escala 1-5): **5.0/5**.

## 31. Execucao da fase 22 (governanca de loader model cross-area)

Foi executada fase complementar para reforcar isolamento entre dominios na resolucao de models via `Loader`.

### 31.1 Endurecimento do auditor de composicao

Arquivo atualizado:

- `tools/architecture/run-service-composition-audit.php`

Nova regra implementada:

- `checkCrossAreaModelLoaderUsageInControllers()`:
  - detecta `loader->model(..., 'admin|client|install')` em controllers;
  - bloqueia quando a area alvo diverge da area do controller atual.

Objetivo tecnico: impedir acoplamento transversal entre dominios por carga direta de model de outra area.

### 31.2 Evidencias tecnicas da fase 22 (2026-05-07)

Validacoes executadas:

1. `php -l tools/architecture/run-service-composition-audit.php` -> sem erros.
2. `php tools/architecture/run-service-composition-audit.php` -> `PASS` com:
   - `Passes=35`
   - `Warnings=0`
   - `Failures=0`
3. `php tools/quality/run-quality-gates.php --exit-mode=bitmap` -> `PASS` com:
   - `Checks=4`
   - `Passes=4`
   - `Failures=0`
   - `FailureMask=0`
   - `ExitCode=0`

### 31.3 Resultado formal da fase 22

1. isolamento de dominio por area foi reforcado tambem no fluxo de resolucao de models;
2. risco de dependencia cruzada por loader diminuiu com controle preventivo automatizado;
3. trilha de governanca arquitetural permaneceu estavel sem regressao em seguranca e operacao.

Maturidade estrutural atualizada (escala 1-5): **5.0/5**.

## 30. Execucao da fase 21 (governanca de heranca de models e instanciacao em controllers)

Foi executada fase complementar para formalizar a disciplina da camada de dados na arquitetura MVCL.

### 30.1 Endurecimento do auditor de composicao

Arquivo atualizado:

- `tools/architecture/run-service-composition-audit.php`

Novas regras implementadas:

1. `checkModelInheritanceByArea()`:
   - `admin`: models devem herdar `Model` ou `AbstractCrudModel` (com `AbstractCrudModel` herdando `Model`);
   - `client/install`: models devem herdar `Model`.
2. `checkModelInstantiationInControllers()`:
   - bloqueia `new ...Model(...)` em `admin|client|install/Controller`;
   - reforca consumo de models via `Loader/Registry`.

Objetivo tecnico: reduzir acoplamento de construcoes ad-hoc e preservar o ciclo padrao de injeção/resolução de dependencias.

### 30.2 Evidencias tecnicas da fase 21 (2026-05-07)

Validacoes executadas:

1. `php -l tools/architecture/run-service-composition-audit.php` -> sem erros.
2. `php tools/architecture/run-service-composition-audit.php` -> `PASS` com:
   - `Passes=34`
   - `Warnings=0`
   - `Failures=0`
3. `php tools/quality/run-quality-gates.php --exit-mode=bitmap` -> `PASS` com:
   - `Checks=4`
   - `Passes=4`
   - `Failures=0`
   - `FailureMask=0`
   - `ExitCode=0`

### 30.3 Resultado formal da fase 21

1. contratos de heranca de model por dominio foram convertidos em regra auditavel;
2. instanciacao direta de model em controller passou a ter bloqueio preventivo de regressao;
3. arquitetura MVCL consolidou padrao de acesso a dados mais previsivel e governavel.

Maturidade estrutural atualizada (escala 1-5): **5.0/5**.

## 29. Execucao da fase 20 (governanca de heranca de controllers por area)

Foi executada fase adicional para transformar o padrao de heranca de controllers em regra automatizada de governanca arquitetural.

### 29.1 Endurecimento do auditor de composicao

Arquivo atualizado:

- `tools/architecture/run-service-composition-audit.php`

Nova regra implementada:

- `checkControllerInheritanceByArea()`:
  - `admin` e `client`: controllers de negocio devem herdar `BaseController`;
  - `admin/BaseController` e `client/BaseController`: devem herdar `Controller`;
  - `install`: controllers devem herdar `Controller` (ou `BaseController`, quando aplicavel).

Objetivo tecnico: evitar regressao de bootstrap de seguranca/factories comuns e manter consistencia de camada de orquestracao por area.

### 29.2 Evidencias tecnicas da fase 20 (2026-05-07)

Validacoes executadas:

1. `php -l tools/architecture/run-service-composition-audit.php` -> sem erros de sintaxe.
2. `php tools/architecture/run-service-composition-audit.php` -> `PASS` com:
   - `Passes=32`
   - `Warnings=0`
   - `Failures=0`
3. `php tools/quality/run-quality-gates.php --exit-mode=bitmap` -> `PASS` com:
   - `Checks=4`
   - `Passes=4`
   - `Failures=0`
   - `FailureMask=0`
   - `ExitCode=0`

### 29.3 Resultado formal da fase 20

1. padrao de heranca por area deixou de ser apenas convencao e passou a ser criterio verificavel em pipeline;
2. risco de regressao arquitetural por controller fora de base foi reduzido;
3. estrutura MVCL ganhou reforco na camada de entrada/orquestracao.

Maturidade estrutural atualizada (escala 1-5): **5.0/5**.

## 28. Execucao da fase 19 (eliminacao de `$_SERVER` em libraries)

Foi executada fase complementar para remover o uso direto de `$_SERVER` na camada `system/Library`, consolidando acesso a contexto HTTP via `Request`.

### 28.1 Refatoracoes aplicadas na camada de biblioteca

Arquivos atualizados:

- `system/Library/Auth.php`
- `system/Library/SecurityRuntimeTrait.php`
- `system/Library/CampaignTrackingUrlHelpersTrait.php`

Mudancas objetivas:

1. substituicao de fallback direto `$_SERVER` por leitura segura de `Request->server` com fallback vazio;
2. introducao de helper interno de contexto em traits (`requestServerContext()`) para padronizacao;
3. preservacao de comportamento de host/scheme/IP/user-agent com menor acoplamento a superglobal.

### 28.2 Endurecimento adicional do auditor de composicao

Arquivo atualizado:

- `tools/architecture/run-service-composition-audit.php`

Nova regra implementada:

- `checkServerSuperglobalInLibraries()`:
  - bloqueia ocorrencia de `$_SERVER` em `system/Library`;
  - consolida a politica de acesso por abstrações de request em todas as camadas de dominio.

### 28.3 Evidencias tecnicas da fase 19 (2026-05-07)

Validacoes executadas:

1. `rg -n --fixed-strings '$_SERVER' system/Library` -> sem ocorrencias.
2. `php -l system/Library/Auth.php` -> sem erros.
3. `php -l system/Library/SecurityRuntimeTrait.php` -> sem erros.
4. `php -l system/Library/CampaignTrackingUrlHelpersTrait.php` -> sem erros.
5. `php -l tools/architecture/run-service-composition-audit.php` -> sem erros.
6. `php tools/architecture/run-service-composition-audit.php` -> `PASS` com:
   - `Passes=31`
   - `Warnings=0`
   - `Failures=0`
7. `php tools/quality/run-quality-gates.php --exit-mode=bitmap` -> `PASS` com:
   - `Checks=4`
   - `Passes=4`
   - `Failures=0`
   - `FailureMask=0`
   - `ExitCode=0`

### 28.4 Resultado formal da fase 19

1. uso direto de `$_SERVER` foi removido de controller, model e library;
2. arquitetura consolidou fronteira limpa de contexto HTTP via abstrações centrais;
3. auditoria automatizada passou a cobrir regressao completa desse criterio em todas as camadas de aplicacao.

Maturidade estrutural atualizada (escala 1-5): **5.0/5**.
