# Integracoes, Automacoes E Manutencao

## Objetivo

Documentar, em passo a passo, as 7 frentes implementadas para ampliar o Solis no ecossistema Nosfir:

1. Hub de publicacao oficial por canal
2. Automacoes de rotinas por webhooks
3. Rastreio de campanha (UTM/MTM + short links + resultados)
4. Dashboard executivo embutido
5. Observabilidade operacional
6. Monitoramento de jobs e alertas
7. Feature flags com governanca por area/permissao/hierarquia

## Visao De Implementacao

### Cliente

- `client/social/index`:
  - conexoes sociais
  - padroes de formato
  - hub de publicacao (fila, publicar agora, processar fila)
- `client/tracking/index`:
  - geracao de links rastreaveis
  - short link interno (+ Bitly opcional)
  - painel de cliques por campanha/canal
- `client/dashboard/index`:
  - KPIs executivos de tracking, publicacao, webhooks, jobs e erros

### Admin

- `admin/operations/index`:
  - feature flags
  - webhooks
  - monitores de jobs
  - alertas ativos
  - eventos de observabilidade
  - manutencao manual

### Servicos Tecnicos

- `System\Library\FeatureFlagService`
- `System\Library\AutomationService`
- `System\Library\CampaignTrackingService`
- `System\Library\SocialPublishingService`
- `System\Library\ObservabilityService`
- `System\Library\JobMonitorService`

## Passo A Passo Por Frente

## 1) Hub De Publicacao Oficial

### O que foi entregue

- fila de publicacoes em `social_publications`
- log por publicacao em `social_publication_logs`
- enfileiramento manual e por item de plano
- publish imediato por item
- processamento em lote da fila
- modo `dry_run` (default) para validacao segura
- conector real para LinkedIn (quando `dry_run = false` e conexao valida)

### Fluxo de uso

1. Conectar plataforma em `client/social/index`.
2. Na secao "Hub de publicacao oficial", escolher:
   - item de plano (opcional)
   - plataformas
   - texto/midia/agendamento
3. Enfileirar publicacao.
4. Publicar agora ou processar fila.
5. Acompanhar status (`queued`, `processing`, `published`, `failed`, `manual_review`).

### Configuracao

- `system/Config/app.php`:
  - `integrations.social_publisher.dry_run`
  - `integrations.social_publisher.linkedin_version`

## 2) Automacoes Por Webhooks

### O que foi entregue

- cadastro de webhooks por evento em `automations_webhooks`
- logs de dispatch em `automation_dispatch_logs`
- suporte a eventos exatos e wildcard (`plan.*`, `*`)
- assinatura HMAC opcional (`X-Nosfir-Signature`)
- auth `none`, `bearer`, `basic`, `header`
- teste manual de webhook no painel admin

### Eventos principais disparados

- `plan.item_status_changed`
- `social.publication_queued`
- `social.publication_published`
- `social.publication_failed`
- `social.queue_processed`
- `tracking.link_created`
- `tracking.link_clicked`
- `jobs.alert.opened`
- `jobs.alert.summary`
- `system.webhook_test`

### Fluxo de uso

1. Abrir `admin/operations/index`.
2. Cadastrar webhook com `event_key`.
3. Definir seguranca (auth e assinatura).
4. Executar "Testar".
5. Validar resposta no historico de dispatch.

## 3) Rastreio De Campanhas

### O que foi entregue

- modulo `client/tracking/index`
- tabela `campaign_tracking_links`
- geracao automatica de URL rastreavel:
  - `utm_source`, `utm_medium`, `utm_campaign`, `utm_content`, `utm_term`
  - `mtm_campaign`, `mtm_keyword`
- short link interno publico:
  - rota `tracking/redirect/{shortCode}`
- fallback com Bitly (quando token configurado)
- consolidado de cliques por campanha/canal

### Configuracao

- `system/Config/app.php`:
  - `integrations.tracking.bitly_access_token`

### Fluxo de uso

1. Abrir `client/tracking/index`.
2. Informar URL de destino e parametros UTM/MTM.
3. Salvar link rastreavel.
4. Usar short link em campanhas sociais.
5. Monitorar cliques no proprio modulo de tracking.

## 4) Dashboard Executivo

### O que foi entregue

No `client/dashboard/index`, bloco executivo com:

- links rastreados
- cliques totais
- publicacoes concluidas
- fila/falhas de publicacao
- webhooks ativos
- alertas de jobs abertos
- erros de observabilidade nas ultimas 24h
- top campanhas por cliques

### Fontes de dados

- `campaign_tracking_links`
- `social_publications`
- `automations_webhooks`
- `job_alerts`
- `observability_events`

## 5) Observabilidade

### O que foi entregue

- eventos estruturados em `observability_events`
- spans em `observability_spans`
- logs por categoria/nivel/area/usuario
- captura de excecao via service
- compatibilidade opcional com Sentry (se SDK estiver instalado)

### Configuracao

- `system/Config/app.php`:
  - `integrations.observability.sentry_enabled`
  - `integrations.observability.sentry_dsn`

### Uso

- logs automaticos em fluxos criticos (tracking, social publish, status de plano, operacoes)
- consulta via `admin/operations/index`

## 6) Monitoramento De Jobs E Alertas

### O que foi entregue

- monitores em `job_monitors`
- check-ins em `job_checkins`
- alertas em `job_alerts`
- deteccao de:
  - falha (`failure`)
  - lentidao (`slow`)
  - ausencia de check-in (`stale`)
- dispatch de alerta para webhooks via evento `jobs.alert.opened`

### Monitores padrao

- `plans.status_updates`
- `social.publisher_queue`
- `automation.webhook_dispatch`

### Fluxo de uso

1. Abrir `admin/operations/index`.
2. Cadastrar/ajustar monitor (`intervalo esperado`, `tempo maximo`).
3. Acompanhar check-ins e alertas ativos.
4. Executar manutencao manual quando necessario.

## 7) Feature Flags Com Governanca

### O que foi entregue

- tabela `feature_flags`
- estrategias de rollout:
  - `all`
  - `admins_only`
  - `clients_only`
  - `min_hierarchy`
  - `permission`
- controle por area (`all`, `admin`, `client`)
- mapa de flags aplicado automaticamente em controllers base

### Flags padrao criadas

- `social.publish_hub`
- `automation.webhooks`
- `tracking.campaign_links`
- `dashboard.executive`
- `observability.telemetry`
- `jobs.monitoring`
- `governance.feature_flags`

### Fluxo de uso

1. Abrir `admin/operations/index`.
2. Criar/editar flag.
3. Definir area e estrategia.
4. Validar comportamento no cliente/admin.

## Rotas Novas

- Cliente:
  - `tracking/index`
  - `tracking/store`
  - `tracking/archive/{id}`
  - `tracking/redirect/{shortCode}` (publica)
  - `social/queuePublication`
  - `social/publishNow/{id}`
  - `social/processQueue`
- Admin:
  - `operations/index`
  - `operations/saveFeatureFlag`
  - `operations/deleteFeatureFlag/{id}`
  - `operations/saveWebhook`
  - `operations/deleteWebhook/{id}`
  - `operations/testWebhook/{id}`
  - `operations/saveMonitor`
  - `operations/deleteMonitor/{id}`
  - `operations/runMaintenance`

## Rotina De Manutencao Recomendada

### Diario

- revisar alertas em `admin/operations/index`
- validar fila de publicacao no `client/social/index`
- verificar erros recentes de observabilidade

### Semanal

- revisar falhas de webhook e ajustar retries/timeouts
- revisar top campanhas no tracking e realinhar estrategia
- arquivar links de campanha encerrada

### Mensal

- revisar feature flags ativas/inativas
- revisar segredos (webhooks, tokens OAuth, Bitly)
- validar configuracao `dry_run` vs producao do hub de publicacao

## Checklist De Integracao Externa

- Webhooks:
  - endpoint HTTPS
  - timeout suportado
  - validacao de assinatura HMAC
- LinkedIn:
  - token valido
  - `platform_user_id` em formato URN
  - `integrations.social_publisher.dry_run = false` em producao
- Bitly:
  - token preenchido em `integrations.tracking.bitly_access_token`

## Estrategia De Evolucao

- adicionar conectores reais por plataforma no `SocialPublishingService`
- adicionar jobs assicronos/filas externas para alto volume
- ampliar painel de analytics por campanha com custo/receita/conversao
- integrar exportacao de eventos para stack externa de observabilidade
