# Modulo Cliente

## Visao Geral

A area `client` concentra operacao diaria de estrategia e execucao:

- autenticacao e conta
- dashboard
- calendario
- planos editoriais
- central IA para planos e campanhas
- central social
- rastreamento de campanhas
- planos e faturamento

## Autenticacao E Conta (`client/auth/*`)

### Fluxos Publicos

- `auth/login` e `auth/authenticate`
- `auth/register` e `auth/createAccount`
- `auth/forgotpassword` e `auth/sendpasswordreset`
- `auth/resetpassword` e `auth/updatepassword`

### Regras Principais

- cadastro valida nome, email e senha minima
- conta nova entra em grupo cliente e recebe plano Basico Gratuito automaticamente
- login/logout usam CSRF e validacao de metodo
- reset de senha usa token de 64 chars com hash (`password_resets`) e expiracao configuravel

### Idioma E Experiencia De Login

- idiomas de interface suportados: `pt-br` e `en-us`
- seletor de idioma com dropdown, bandeira e codigo curto dentro do card de login em:
  - landing principal (`/`, arquivo `index.php`)
  - tela dedicada de login cliente (`/client/auth/login`)
- persistencia do idioma ativo em sessao (`language_code`) com fallback de `app.languages.fallback`
- submit protegido por CSRF para troca de idioma tanto no fluxo de login interno quanto na landing

## Dashboard (`client/dashboard/index`)

Exibe:

- total de planos
- total de itens planejados
- campanhas ativas
- sugestoes estrategicas ativas
- pipeline por status (`planned`, `scheduled`, `published`, `skipped`)
- proximas publicacoes
- bloco executivo com:
  - links rastreados e cliques
  - fila/publicacoes concluidas/falhas
  - webhooks ativos
  - alertas de jobs
  - erros de observabilidade (24h)

## Calendario Unificado (`client/calendar/*`)

Modos disponiveis:

- `index`
- `annual/{year}`
- `monthly/{year}/{month}`
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
- paleta de cores por usuario (`user_calendar_colors`)

## Planos Editoriais (`client/plans/*`)

### Criacao De Planos

- por periodo manual (`plans/store`)
- por templates anuais (`plans/storeTemplate`) via `PlanTemplateService`

### Central IA De Planos E Campanhas (`plans/storeAi`)

- gera campanha + plano + itens em um unico fluxo transacional
- suporta estrategia de campanha:
  - criar nova campanha
  - vincular campanha existente
  - gerar plano sem campanha
- usa IA definida pelo Admin como padrao global, com possibilidade de override por cliente
- permite override manual de IA no formulario do cliente (quando necessario)
- valida feature flag/comercial (`allow_ai_draft_generator`) e quotas antes da geracao

### Detalhe Do Plano (`plans/show/{id}`)

- filtros por status e busca textual
- cards de insights:
  - total de itens
  - taxa de conclusao
  - taxa de publicacao
  - atrasos
  - proximo item pendente
- atualizacao individual de item (`plans/updateItem/{itemId}`)
- atualizacao em lote (`plans/bulkUpdateStatus`)
- exportacao CSV (`plans/exportCsv/{id}`)

### Seguranca De Plano

Operacoes de leitura/edicao/exportacao validam escopo do usuario (`planByIdForUser`, `planItemsForUser`).

## Central Social (`client/social/*`)

### Conexoes De Plataforma

- OAuth2 para plataformas do `SocialPlatformRegistry`
- conexao manual por token quando necessario
- disconnect com auditoria
- painel de seguranca de conexoes com glifos para status visual rapido por rede

### Estrategia De Conteudo

- geracao de drafts via `ContentStrategistService`
- persistencia em `social_content_drafts`

### Padroes De Formato

- matriz de standards via `SocialFormatStandardsService`
- presets por usuario (`social_format_presets`)

### Hub De Publicacao

- fila em `social_publications`
- logs em `social_publication_logs`
- enfileirar (`social/queuePublication`)
- publicar agora (`social/publishNow/{id}`)
- processar fila (`social/processQueue`)
- status: `queued`, `processing`, `published`, `failed`, `manual_review`

## Rastreamento De Campanhas (`client/tracking/*`)

### Recursos

- geracao de links UTM/MTM
- short link interno publico (`tracking/redirect/{shortCode}`)
- encurtamento externo Bitly (opcional)
- consolidado de cliques por campanha/canal
- arquivamento de links encerrados

### Persistencia

- tabela `campaign_tracking_links`

## Planos E Faturamento (`client/billing/*`)

### O Que Centraliza

- contexto da assinatura ativa (`user_subscriptions` + `subscription_plans`)
- consumo por limite (`plan_limits` + uso mensal)
- catalogo de planos publicos com promocao ativa
- historico de faturas (`billing_invoices`)
- pagamento de faturas e troca de plano

### Fluxos

- `billing/index`: painel de assinatura, limites e historico
- `billing/subscribe`: upgrade/downgrade com metodo de pagamento
- `billing/payInvoice/{invoiceId}`: pagamento de fatura aberta/falha

## Principais Controllers/Models

- `Client\Controller\AuthController`
- `Client\Controller\DashboardController`
- `Client\Controller\CalendarController`
- `Client\Controller\PlansController`
- `Client\Controller\SocialController`
- `Client\Controller\TrackingController`
- `Client\Controller\BillingController`
- `Client\Model\CalendarModel`
- `Client\Model\PlannerModel`
- `Client\Model\SocialModel`
