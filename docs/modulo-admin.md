# Modulo Admin

## Visao Geral

A area `admin` e o centro de governanca do Solis:

- curadoria de base estrategica
- administracao de usuarios
- controle de hierarquia
- governanca de IA para planos e campanhas
- monitoramento operacional
- governanca de integracoes e automacoes
- governanca de monetizacao e pagamentos

## Acesso E Idioma (`admin/auth/login`)

- autenticacao aceita:
  - `users.email`
  - `users.recovery_email`
  - `users.name` (usuario)
- seletor de idioma visivel no card de login com dropdown, bandeiras e codigos curtos (`pt-br` e `en-us`)
- troca de idioma persiste `language_code` em sessao via `language/save` com validacao CSRF

## Dashboard Administrativo (`admin/dashboard/index`)

Exibe indicadores de:

- usuarios
- feriados
- comemorativas
- sugestoes
- campanhas
- plataformas

Tambem apresenta:

- distribuicao relativa da base estrategica
- sugestoes recentes
- formatos recorrentes

## CRUDs Principais

### Feriados (`admin/holidays/*`)

- create/read/update/delete
- tipo: `national`, `regional`, `international`
- suporte a regra movel (`is_movable`, `movable_rule`)

### Datas Comemorativas (`admin/commemoratives/*`)

- create/read/update/delete
- recorrencia (`recurrence_type`)
- contexto (`commercial`, `institutional`, `seasonal`, `editorial`)

### Sugestoes Estrategicas (`admin/suggestions/*`)

- create/read/update/delete
- associacoes com categoria, pilar, objetivo e campanha
- relacao N:N com plataformas (`content_suggestion_channels`)

### Canais E Plataformas (`admin/channels/*`)

- create/read/update/delete de `content_platforms`
- slug automatico via `slugify` do `BaseController`

### Campanhas (`admin/campaigns/*`)

- create/read/update/delete
- periodo e status (`planned`, `active`, `completed`, `archived`)

## Usuarios E Hierarquia (`admin/users/*`)

### Objetivo

Permitir governanca de niveis de acesso e de entitlements de produto sem depender apenas de permissao textual.

### Modelo De Nivel

- campo `user_groups.hierarchy_level`
- regra: **quanto menor o numero, maior a autoridade**

### Regras Aplicadas

- admin so cria usuario em grupo com nivel >= ao seu
- admin so altera niveis de grupos dentro do seu escopo
- admin so altera plano/recursos de usuarios gerenciaveis pelo nivel

### Fluxos

- `users/index`: lista usuarios, grupos, plano atual, recursos efetivos e filtros
- `users/store`: cria usuario com validacoes de hierarquia e email unico
- `users/saveHierarchy`: atualiza niveis hierarquicos em lote
- `users/saveDefaultFilters`: salva filtro padrao da listagem por admin
- `users/clearDefaultFilters`: remove filtro padrao salvo
- `users/updatePlan/{userId}`: altera plano da assinatura do usuario
- `users/saveUserFeatures/{userId}`: sobrescreve recursos por usuario (`user_feature_overrides`)

## Operacoes E Integracoes (`admin/operations/*`)

### O Que Centraliza

- **Feature flags** (`feature_flags`)
  - chave da flag
  - area alvo
  - estrategia de rollout
  - ativacao/desativacao
- **Webhooks de automacao** (`automations_webhooks`)
  - evento alvo
  - endpoint
  - autenticacao e assinatura
  - retries e timeout
- **Logs de dispatch** (`automation_dispatch_logs`)
- **Monitores de job** (`job_monitors`)
  - intervalo esperado
  - runtime maximo
- **Check-ins e alertas de job** (`job_checkins`, `job_alerts`)
- **Observabilidade** (`observability_events`)
- **Manutencao operacional**
  - avaliacao de monitores stale
  - limpeza de cache em `system/Storage/cache`

### Fluxos

- `operations/index`: painel consolidado de operacoes
- `operations/saveFeatureFlag`: cria/atualiza flag
- `operations/deleteFeatureFlag/{id}`: remove flag
- `operations/saveWebhook`: cria/atualiza webhook
- `operations/deleteWebhook/{id}`: remove webhook
- `operations/testWebhook/{id}`: valida endpoint
- `operations/saveMonitor`: cria/atualiza monitor
- `operations/deleteMonitor/{id}`: remove monitor
- `operations/runMaintenance`: executa manutencao manual
- `operations/clearCache`: limpa cache local e registra evento

## Billing Admin (`admin/billing/*`)

### O Que Centraliza

- catalogo de planos (`subscription_plans`)
- limites por plano (`plan_limits`)
- promocoes (`billing_promotions`)
- comunicados de preco/reajuste (`billing_announcements`)
- configuracoes de pagamento e validacao (`settings`)
- fila de validacoes manuais (`payment_transactions` pendentes)

### Fluxos

- `billing/index`: painel completo de planos e pagamentos
- `billing/savePlan/{planId}`: atualiza nome, preco, visibilidade e limites
- `billing/savePromotion` e `billing/deletePromotion/{id}`
- `billing/saveAnnouncement` e `billing/deleteAnnouncement/{id}`
- `billing/savePaymentSettings`: conta recebedora, meios e modo de validacao
- `billing/approvePayment/{transactionId}` e `billing/rejectPayment/{transactionId}`

## Central De Planos E Campanhas IA (`admin/plans_campaigns/*`)

### O Que Centraliza

- governanca de IA padrao global para geracao de planos/campanhas
- atribuicao de IA por cliente (override por usuario)
- listagem de clientes com origem da IA (`default` ou `custom`)
- ajustes operacionais de campanhas (status, objetivo, periodo)
- ajustes operacionais de planos (status, campanha vinculada e notas)
- filtros combinados e paginacao para clientes, campanhas e planos

### Fluxos

- `plans_campaigns/index`: painel consolidado com KPIs e tres blocos de governanca
- `plans_campaigns/saveDefaultManager`: define a IA padrao global
- `plans_campaigns/saveClientManager/{userId}`: define ou limpa IA personalizada por cliente
- `plans_campaigns/updateCampaign/{campaignId}`: atualiza governanca de campanha
- `plans_campaigns/updatePlan/{planId}`: atualiza governanca de plano

## Principais Controllers/Models

- `Admin\Controller\DashboardController`
- `Admin\Controller\HolidaysController`
- `Admin\Controller\CommemorativesController`
- `Admin\Controller\SuggestionsController`
- `Admin\Controller\ChannelsController`
- `Admin\Controller\CampaignsController`
- `Admin\Controller\UsersController`
- `Admin\Controller\OperationsController`
- `Admin\Controller\BillingController`
- `Admin\Controller\PlansCampaignsController`
- `Admin\Model\UserGroupsModel`
- `Admin\Model\UsersModel`
- `Admin\Model\ContentSuggestionsModel`
- `Admin\Model\PlansCampaignsModel`
