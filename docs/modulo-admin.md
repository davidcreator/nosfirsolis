# Modulo Admin

## Visao Geral

A area `admin` e o centro de governanca do Solis:

- curadoria de base estrategica
- administracao de usuarios
- controle de hierarquia
- monitoramento operacional
- governanca de integracoes e automacoes

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
- tipo: national, regional, international
- suporte a regra movel (`is_movable`, `movable_rule`)

### Datas Comemorativas (`admin/commemoratives/*`)

- create/read/update/delete
- recorrencia (`recurrence_type`)
- contexto (`commercial`, `institutional`, `seasonal`, `editorial`)

### Sugestoes Estrategicas (`admin/suggestions/*`)

- create/read/update/delete
- associacoes com categoria, pilar, objetivo e campanha
- relacionamento N:N com plataformas (`content_suggestion_channels`)

### Canais E Plataformas (`admin/channels/*`)

- create/read/update/delete de `content_platforms`
- slug automatico via `slugify` do `BaseController`

### Campanhas (`admin/campaigns/*`)

- create/read/update/delete
- periodo e status (`planned`, `active`, `completed`, `archived`)

## Usuarios E Hierarquia (`admin/users/*`)

### Objetivo

Permitir governanca de niveis de acesso sem depender apenas de permissao textual.

### Modelo De Nivel

- campo `user_groups.hierarchy_level`
- regra: **quanto menor o numero, maior a autoridade**

### Regras Aplicadas

- admin so cria usuario em grupo com nivel >= ao seu
- admin so altera niveis de grupos dentro do seu escopo
- atualizacao de hierarquia em lote na tela de usuarios

### Fluxos

- `users/index`: lista usuarios + grupos gerenciaveis
- `users/store`: cria usuario com validacoes de hierarquia e email unico
- `users/saveHierarchy`: salva niveis de grupos respeitando limite de escopo

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
- **Check-ins e alertas de job**
  - `job_checkins`
  - `job_alerts`
- **Observabilidade**
  - visualizacao de `observability_events`

### Fluxos

- `operations/index`: painel consolidado de operacoes
- `operations/saveFeatureFlag`: cria/atualiza flag
- `operations/saveWebhook`: cria webhook
- `operations/testWebhook/{id}`: valida endpoint
- `operations/saveMonitor`: cria monitor
- `operations/runMaintenance`: executa manutencao operacional

## Principais Controllers/Models

- `Admin\Controller\DashboardController`
- `Admin\Controller\HolidaysController`
- `Admin\Controller\CommemorativesController`
- `Admin\Controller\SuggestionsController`
- `Admin\Controller\ChannelsController`
- `Admin\Controller\CampaignsController`
- `Admin\Controller\UsersController`
- `Admin\Controller\OperationsController`
- `Admin\Model\UserGroupsModel`
- `Admin\Model\UsersModel`
- `Admin\Model\ContentSuggestionsModel`
