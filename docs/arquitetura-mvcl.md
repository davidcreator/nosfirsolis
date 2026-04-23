# Arquitetura MVCL

## Estrutura Geral

```text
admin/    -> area administrativa
client/   -> area de operacao (usuario final)
install/  -> instalador
system/   -> engine, libs, configs e servicos base
```

## Areas Da Aplicacao

- `client`: dashboard, calendario, planos e central social.
- `admin`: gestao de base estrategica e usuarios.
- `install`: setup inicial e reinstalacao controlada.

## Pipeline De Execucao

1. `index.php` (ou `admin/index.php`, `client/index.php`, `install/index.php`)
2. `System\Engine\Application`
3. `System\Engine\Router`
4. `Controller` correspondente
5. `Model` e `Library` necessarios
6. `View` + layout
7. `Response`

## Roteamento

Roteamento dinamico no formato:

`/controller/action/param1/param2`

Exemplo:

- `plans/show/12`
- `calendar/index?mode=monthly&year=2026&month=4`
- `tracking/redirect/AbC123xy` (rota publica para short links)

## Camadas

- **Engine** (`system/Engine`): Application, Router, Request, Response, Loader, View.
- **Library** (`system/Library`): servicos de negocio e integracao (auth, seguranca, calendario, social, exportacao, publicacao, tracking, automacao, observabilidade, jobs, feature flags).
- **Model** (`admin/Model`, `client/Model`): acesso a dados e regras por contexto.
- **Controller** (`admin/Controller`, `client/Controller`): orquestracao de fluxo HTTP.
- **View** (`admin/View`, `client/View`): camada de interface.

## Persistencia E Evolucao De Schema

Parte do schema e criada no instalador (`install/sql/schema.sql`), e algumas estruturas sao garantidas em runtime:

- `security_login_attempts` e `security_audit_logs` via `SecurityService`
- `calendar_extra_events` e `user_calendar_colors` via `PlannerModel`
- `social_*` tabelas via `SocialModel`
- coluna `user_groups.hierarchy_level` via `UserGroupsModel`
- `feature_flags` via `FeatureFlagService`
- `automations_webhooks` e `automation_dispatch_logs` via `AutomationService`
- `campaign_tracking_links` via `CampaignTrackingService`
- `social_publications` e `social_publication_logs` via `SocialPublishingService`
- `observability_events` e `observability_spans` via `ObservabilityService`
- `job_monitors`, `job_checkins` e `job_alerts` via `JobMonitorService`

Essa estrategia facilita upgrade incremental sem migration framework dedicado.
