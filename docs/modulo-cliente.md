# Modulo Cliente

## Visao Geral

A area `client` concentra operacao diaria de estrategia e execucao:

- Dashboard
- Calendario
- Planos editoriais
- Central social
- Rastreamento de campanhas

## Dashboard (`client/dashboard/index`)

Exibe:

- total de planos
- total de itens planejados
- campanhas ativas
- sugestoes estrategicas ativas
- pipeline por status (planned, scheduled, published, skipped)
- proximas publicacoes
- bloco executivo com:
  - links rastreados e cliques
  - fila/publicacoes concluidas/falhas
  - webhooks ativos
  - alertas de jobs
  - erros de observabilidade (24h)

## Calendario Unificado (`client/calendar/index`)

Modos disponiveis:

- `annual`
- `monthly`
- `period`

Camadas consolidadas por data:

- feriados (`holidays`)
- datas comemorativas (`commemorative_dates`)
- sugestoes (`content_suggestions`)
- campanhas (`campaigns`)
- eventos base (`system/Storage/base_events.php`)
- observacoes manuais (`content_day_notes`)
- eventos extras (`calendar_extra_events`)

Recursos de apoio:

- filtros por canal, objetivo e campanha
- toggles de visibilidade por camada
- personalizacao de paleta de cores por usuario (`user_calendar_colors`)

## Planos Editoriais (`client/plans/*`)

### Criacao De Planos

- por periodo manual (`plans/store`)
- por templates anuais (`plans/storeTemplate`) via `PlanTemplateService`

### Detalhe Do Plano (`plans/show/{id}`)

- filtros por status e busca textual
- cards de insights:
  - total de itens
  - taxa de conclusao
  - taxa de publicacao
  - atrasos
  - proximo item pendente
- atualizacao individual de item:
  - status
  - observacao manual
- atualizacao em lote de status:
  - selecao multipla
  - marcar todos / limpar selecao
- exportacao CSV (`plans/exportCsv/{id}`)

### Seguranca De Plano

Operacoes de leitura/edicao/exportacao validam escopo do usuario (`planByIdForUser`, `planItemsForUser`).

## Central Social (`client/social/index`)

### Conexoes De Plataforma

- OAuth2 para plataformas configuradas no `SocialPlatformRegistry`
- conexao manual por token quando necessario
- disconnect com auditoria

### Estrategia De Conteudo

- geracao de drafts via `ContentStrategistService`
- persistencia em `social_content_drafts`

### Padroes De Formato

- matriz de standards via `SocialFormatStandardsService`
- fontes de referencia por plataforma/formato
- presets personalizados por usuario (`social_format_presets`)

### Hub De Publicacao

- fila de publicacoes por plataforma (`social_publications`)
- publicacao avulsa ou por item de plano
- processamento em lote da fila
- status de envio:
  - `queued`
  - `processing`
  - `published`
  - `failed`
  - `manual_review`
- logs por publicacao (`social_publication_logs`)

## Rastreamento De Campanhas (`client/tracking/*`)

### Recursos

- geracao de links rastreaveis com UTM/MTM
- short link interno publico (`tracking/redirect/{shortCode}`)
- encurtamento externo Bitly (opcional)
- consolidado de cliques por campanha/canal
- arquivamento de links encerrados

### Persistencia

- tabela `campaign_tracking_links`

## Principais Controllers/Models

- `Client\Controller\CalendarController`
- `Client\Controller\PlansController`
- `Client\Controller\SocialController`
- `Client\Controller\TrackingController`
- `Client\Model\CalendarModel`
- `Client\Model\PlannerModel`
- `Client\Model\SocialModel`
