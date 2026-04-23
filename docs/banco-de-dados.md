# Banco De Dados

## Visao Geral

O NosfirSolis usa MySQL com `utf8mb4` e schema inicial definido em:

- `install/sql/schema.sql`
- `install/sql/seed.sql`

A conexao e carregada por `system/Config/database.php` e sobrescrita na instalacao por `config.php` e `system/Storage/config.php`.

## Convencoes De Modelagem

- Tabelas usam `id` inteiro auto incremento como chave primaria.
- Quase todas entidades usam `created_at` e `updated_at`.
- Relacionamentos usam `INT UNSIGNED` com `FOREIGN KEY`.
- Campos JSON sao persistidos como `LONGTEXT`.
- Status pode ser `TINYINT(1)` (ativo/inativo) ou `ENUM`, conforme o dominio.

## Dominios E Tabelas

### Identidade E Governanca

- `user_groups`: grupos, permissoes (`permissions_json`) e nivel hierarquico (`hierarchy_level`).
- `users`: usuarios, grupo, senha hash, status e ultimo login.
- `settings`: configuracoes chave/valor.
- `languages`: idiomas disponiveis no sistema.

### Base Estrategica De Conteudo

- `social_channels`: catalogo de canais sociais.
- `video_channels`: catalogo de canais de video.
- `content_platforms`: plataformas unificadas para uso em sugestoes e planos.
- `content_categories`: categorias de formato/editoria.
- `content_pillars`: pilares de conteudo.
- `content_objectives`: objetivos de conteudo.
- `tags`: tags de apoio para sugestoes.

### Calendario, Datas E Campanhas

- `holiday_regions`: regioes de feriados.
- `holidays`: feriados fixos/moveis.
- `commemorative_dates`: datas comemorativas.
- `campaigns`: campanhas com periodo e status.
- `content_suggestions`: sugestoes editoriais vinculaveis a campanha, pilar, objetivo e categoria.
- `content_suggestion_channels`: relacao N:N entre sugestao e plataforma.
- `content_suggestion_tags`: relacao N:N entre sugestao e tag.

### Planejamento Operacional

- `content_plans`: plano editorial por usuario e periodo.
- `content_plan_items`: itens do plano com status operacional.
- `content_day_notes`: observacoes manuais por data/contexto.
- `calendar_extra_events`: eventos extras no calendario do usuario.
- `user_calendar_colors`: paleta personalizada de cores por usuario.

### Social E Distribuicao

- `social_connections`: contas conectadas (OAuth/manual), tokens criptografados e metadata.
- `social_content_drafts`: rascunhos estrategicos gerados para redes sociais.
- `social_format_presets`: presets de tamanho/formato por plataforma e usuario.
- `social_publications`: fila e status de publicacoes no hub social.
- `social_publication_logs`: logs por tentativa/publicacao.

### Tracking De Campanhas

- `campaign_tracking_links`: links rastreaveis UTM/MTM, short code, cliques e status.

### Operacoes E Governanca Tecnica

- `feature_flags`: governanca de rollout por area/permissao/hierarquia.
- `automations_webhooks`: webhooks de eventos operacionais.
- `automation_dispatch_logs`: historico de disparo por webhook.
- `job_monitors`: definicao de jobs monitorados.
- `job_checkins`: check-ins de execucao.
- `job_alerts`: alertas de falha/stale/lentidao.
- `observability_events`: eventos estruturados de telemetria.
- `observability_spans`: spans de duracao para rastreabilidade de fluxo.

### Seguranca E Auditoria

- `security_login_attempts`: tentativas de login para rate limit.
- `security_audit_logs`: trilha de auditoria de eventos de seguranca.

## Relacionamentos Principais

- `users.user_group_id -> user_groups.id`
- `holidays.holiday_region_id -> holiday_regions.id`
- `content_suggestions.content_category_id -> content_categories.id`
- `content_suggestions.content_pillar_id -> content_pillars.id`
- `content_suggestions.content_objective_id -> content_objectives.id`
- `content_suggestions.campaign_id -> campaigns.id`
- `content_suggestion_channels.content_suggestion_id -> content_suggestions.id`
- `content_suggestion_channels.content_platform_id -> content_platforms.id`
- `content_suggestion_tags.content_suggestion_id -> content_suggestions.id`
- `content_suggestion_tags.tag_id -> tags.id`
- `content_plans.user_id -> users.id`
- `content_plans.campaign_id -> campaigns.id`
- `content_plan_items.content_plan_id -> content_plans.id`
- `content_plan_items.content_suggestion_id -> content_suggestions.id`
- `content_plan_items.campaign_id -> campaigns.id`
- `content_plan_items.content_objective_id -> content_objectives.id`
- `content_day_notes.user_id -> users.id`
- `calendar_extra_events.user_id -> users.id`
- `user_calendar_colors.user_id -> users.id`
- `social_connections.user_id -> users.id`
- `social_content_drafts.user_id -> users.id`
- `social_format_presets.user_id -> users.id`
- `social_publications.user_id -> users.id`
- `social_publications.plan_id -> content_plans.id`
- `social_publications.plan_item_id -> content_plan_items.id`
- `social_publications.connection_id -> social_connections.id`
- `social_publication_logs.publication_id -> social_publications.id`
- `campaign_tracking_links.user_id -> users.id`
- `campaign_tracking_links.campaign_id -> campaigns.id`
- `campaign_tracking_links.plan_item_id -> content_plan_items.id`
- `automation_dispatch_logs.webhook_id -> automations_webhooks.id`
- `job_checkins.monitor_id -> job_monitors.id`
- `job_alerts.monitor_id -> job_monitors.id`
- `security_login_attempts.user_id -> users.id`
- `security_audit_logs.user_id -> users.id`
- `observability_events.user_id -> users.id`
- `observability_spans.user_id -> users.id`

## Enums E Campos Criticos

- `campaigns.status`: `planned`, `active`, `completed`, `archived`
- `content_plans.status`: `draft`, `active`, `archived`
- `content_plan_items.status`: `planned`, `scheduled`, `published`, `skipped`
- `content_suggestions.recurrence_type`: `none`, `yearly`, `monthly`
- `commemorative_dates.recurrence_type`: `none`, `yearly`
- `social_connections.status`: `connected`, `manual`, `revoked`
- `social_format_presets.format_type`: `post`, `carousel`
- `social_publications.status`: `queued`, `processing`, `published`, `failed`, `manual_review`
- `campaign_tracking_links.status`: `active`, `archived`
- `feature_flags.target_area`: `all`, `admin`, `client`
- `feature_flags.rollout_strategy`: `all`, `admins_only`, `clients_only`, `min_hierarchy`, `permission`
- `job_monitors.last_status`: `ok`, `warning`, `error`, `stale`
- `job_checkins.status`: `ok`, `warning`, `error`
- `job_alerts.alert_type`: `failure`, `stale`, `slow`
- `job_alerts.status`: `open`, `resolved`
- `observability_events.level`: `debug`, `info`, `warning`, `error`, `critical`
- `security_audit_logs.severity`: `info`, `warning`, `critical`
- `user_groups.hierarchy_level`: numero menor representa maior autoridade

## Seed Inicial (Estado Base)

O `install/sql/seed.sql` entrega uma base inicial com:

- grupos de usuario (`Administradores`, `Clientes`) e permissoes padrao
- idiomas iniciais
- configuracoes iniciais do sistema
- categorias, pilares e objetivos de conteudo
- canais/plataformas sociais e de video
- feriados, datas comemorativas, campanhas e sugestoes de exemplo

## Evolucao De Schema Em Runtime

Mesmo com schema inicial pronto, algumas camadas reforcam compatibilidade:

- `Admin\Model\UserGroupsModel::ensureHierarchySchema`
  - garante coluna `hierarchy_level` em `user_groups`
- `Client\Model\PlannerModel::ensureCalendarSupportTables`
  - garante `calendar_extra_events`, `user_calendar_colors` e coluna `color_hex`
- `Client\Model\SocialModel::ensureSocialTables`
  - garante tabelas do dominio social
- `System\Library\SecurityService::ensureTables`
  - garante tabelas de login attempts e auditoria
- `System\Library\FeatureFlagService::ensureTables`
  - garante `feature_flags` e flags padrao
- `System\Library\AutomationService::ensureTables`
  - garante webhooks e logs de dispatch
- `System\Library\CampaignTrackingService::ensureTables`
  - garante `campaign_tracking_links`
- `System\Library\SocialPublishingService::ensureTables`
  - garante fila e logs de publicacao
- `System\Library\ObservabilityService::ensureTables`
  - garante eventos e spans de telemetria
- `System\Library\JobMonitorService::ensureTables`
  - garante monitores/check-ins/alertas e monitores padrao

Esse padrao ajuda upgrades incrementais sem framework de migration dedicado.

## Backup E Restauracao (Referencia)

Exemplo de backup:

```bash
mysqldump -u <usuario> -p <banco> > nosfirsolis-backup.sql
```

Exemplo de restauracao:

```bash
mysql -u <usuario> -p <banco> < nosfirsolis-backup.sql
```
