# Integracao com WordPress

## Objetivo

Documentar, de forma tecnica e operacional, como integrar o Solis com WordPress para publicacao de conteudo.

Este guia cobre:

- arquitetura recomendada
- autenticacao
- mapeamento de dados
- fluxo de publicacao
- seguranca
- testes
- roadmap por fases
- analise sobre criar (ou nao) um plugin WordPress dedicado

## Estado Atual do Solis (Base Ja Pronta)

Hoje o Solis ja possui componentes importantes para esta integracao:

- fila de publicacao por plataforma (`social_publications`)
- logs por tentativa (`social_publication_logs`)
- conexao manual por token (`social_connections`)
- hub de publicacao com `queue`, `publishNow` e `processQueue`
- automacao por eventos (`social.publication_*`)
- canal `blog` ja previsto em catalogos/plataformas

Ponto importante:

- no provider real, o fluxo implementado atualmente e LinkedIn.
- para WordPress, precisamos implementar um novo provider no `SocialPublishingService`.

## Modelos de Integracao Possiveis

### Modelo A: Solis publica direto no WordPress REST API

**Como funciona**

- Solis faz `POST` em `https://SEU-SITE/wp-json/wp/v2/posts`.
- autenticacao por Application Password (recomendado para MVP).

**Vantagens**

- menor tempo de entrega
- menos componentes para operar
- menor custo inicial

**Desvantagens**

- menos flexibilidade para regras especificas por site
- regras de negocio ficam mais acopladas no Solis

### Modelo B: Solis envia para endpoint de plugin no WordPress

**Como funciona**

- Solis publica para endpoint custom (`/wp-json/nosfir/v1/publish`).
- plugin recebe, valida seguranca, transforma payload e cria/atualiza post.

**Vantagens**

- maior controle local no WordPress
- melhor para multiplos sites com regras diferentes
- facilita idempotencia e mapeamentos avancados

**Desvantagens**

- maior esforco inicial
- exige manutencao de plugin no ciclo de vida

### Modelo C: Hibrido (recomendacao de evolucao)

1. Fase inicial com Modelo A (rapida).
2. Evoluir para plugin quando houver necessidade de governanca avancada.

## Recomendacao Tecnica (Pratica)

Para o seu projeto atual, a recomendacao mais eficiente e:

1. **MVP sem plugin**, com publicacao direta via REST API do WordPress.
2. **Plugin como fase 2/3**, quando surgir uma destas necessidades:
   - multisite com regras por dominio
   - transformacao de payload mais complexa
   - callbacks de confirmacao para o Solis
   - requisitos fortes de idempotencia/auditoria no lado WordPress

## Pre-Requisitos no WordPress

Antes de integrar:

1. WordPress 6.x atualizado.
2. Site com HTTPS valido.
3. Usuario com permissao de publicar (Author/Editor/Admin, conforme politica).
4. Application Password gerada para esse usuario.
5. REST API acessivel:
   - `GET /wp-json/`
   - `POST /wp-json/wp/v2/posts`
6. Firewall/WAF liberando `POST` para endpoints REST usados.

## Autenticacao Recomendada

### Opcao recomendada para MVP: Application Password

- envio via Basic Auth (`username:application_password`)
- nao usar senha real da conta
- segredo armazenado criptografado no Solis (`access_token_enc`)

### Outras opcoes

- JWT plugin: util, mas adiciona dependencia externa.
- OAuth2: mais robusto em cenarios enterprise, mas aumenta complexidade.

## Mapeamento de Dados Solis -> WordPress

Mapeamento inicial sugerido:

- `social_publications.title` -> `title`
- `social_publications.message_text` -> `content`
- `social_publications.scheduled_at` -> `date` + `status=future` (quando houver agendamento)
- `social_publications.media_url` -> fase 2 (upload de media)
- `social_publications.payload_json` -> campos extras (categorias, tags, slug, excerpt, etc.)
- `social_publications.provider_post_id` -> `id` retornado pelo WordPress

Status internos do Solis:

- `queued` -> aguardando envio
- `processing` -> em envio
- `published` -> WP confirmou criacao/publicacao
- `failed` -> falha tecnica ou de validacao
- `manual_review` -> conexao/configuracao invalida

## Fluxo Tecnico End-to-End

1. Usuario conecta `blog` no Solis.
2. Solis salva conexao manual com:
   - `site_url` e `username` em `metadata_json`
   - `application_password` criptografada em `access_token_enc`
3. Usuario enfileira publicacao no hub social.
4. `processQueue` chama `publishToProvider`.
5. Novo branch `blog` executa `publishWordPress`.
6. Solis monta payload e chama `/wp-json/wp/v2/posts`.
7. Em sucesso:
   - salva `provider_post_id`
   - seta `status=published`
   - registra log
8. Em erro:
   - seta `status=failed`
   - registra `error_message` + contexto tecnico no log.
9. Eventos de automacao continuam disponiveis (`social.publication_published`, `social.publication_failed`).

## Mudancas Recomendadas no Solis (Arquivos)

### 1) UI de conexao manual

- `client/View/social/index.php`
  - adicionar campos especificos para `blog`:
    - `site_url`
    - `username`
    - `application_password`

### 2) Controller de conexao

- `client/Controller/SocialController.php`
  - em `saveManualConnection`, validar campos de `blog`
  - persistir `site_url` e `username` em `metadata`
  - persistir `application_password` como token manual criptografado

### 3) Publicador

- `system/Library/SocialPublishingService.php`
  - estender `publishToProvider()` para `blog`
  - criar `publishWordPress(array $publication, array $connection): array`
  - tratar HTTP status e mensagem de erro retornada pelo WP

### 4) Configuracao

- `system/Config/app.php`
  - adicionar bloco sugerido:
    - `integrations.social_publisher.wordpress.default_post_status`
    - `integrations.social_publisher.wordpress.timeout_seconds`
    - `integrations.social_publisher.wordpress.verify_ssl`

### 5) Documentacao operacional

- atualizar guias de operacao para incluir o fluxo WordPress.

## Contrato de Payload (Sugestao)

Exemplo para criacao simples:

```json
{
  "title": "Titulo do post",
  "content": "<p>Conteudo vindo do Solis</p>",
  "status": "publish"
}
```

Exemplo com agendamento:

```json
{
  "title": "Post agendado",
  "content": "<p>Conteudo</p>",
  "status": "future",
  "date": "2026-05-20T14:30:00"
}
```

## Tratamento de Erros (Obrigatorio)

Padrao recomendado:

1. `401/403`: credencial invalida ou permissao insuficiente.
2. `404`: endpoint REST indisponivel/bloqueado.
3. `429`: rate limit (recomendado retry com backoff).
4. `5xx`: indisponibilidade do site WordPress.
5. timeout/rede: erro transiente, manter tentativa controlada.

Sempre registrar no `social_publication_logs`:

- codigo HTTP
- corpo resumido da resposta
- endpoint chamado
- tentativa

## Seguranca

Checklist minimo:

1. Exigir HTTPS em producao.
2. Nunca expor segredo em logs ou UI.
3. Rotacionar Application Password periodicamente.
4. Manter `dry_run` habilitado em homologacao.
5. Validar dominio informado em `site_url` (evitar URL malformada).
6. Respeitar politicas de host/proxy ja existentes no Solis.

## Testes de Homologacao

### Testes funcionais

1. Conectar `blog` com credenciais validas.
2. Publicar avulso no WordPress.
3. Publicar a partir de item do plano.
4. Agendar e validar publicacao `future`.
5. Validar fila em lote (`processQueue`).

### Testes de falha

1. Token invalido.
2. URL de site invalida.
3. Endpoint REST bloqueado.
4. Timeout de rede.
5. Sem permissao de publicar no WP.

### Validacoes de dados

1. `provider_post_id` salvo corretamente.
2. `status` no Solis coerente com retorno do WP.
3. logs completos por tentativa.

## Roadmap de Implementacao

### Fase 1 (MVP) - Complexidade Media

- publicacao de texto para WordPress
- conexao manual `blog` com `site_url + username + app password`
- status e logs integrados no hub

Estimativa: 2 a 4 dias uteis.

### Fase 2 - Complexidade Media/Alta

- upload de imagem (`media_url` -> media library)
- categorias, tags, excerpt, slug, autor
- agendamento mais robusto com timezone controlado

Estimativa: 2 a 5 dias uteis.

### Fase 3 - Complexidade Alta

- suporte a multiplos sites WordPress por usuario
- retries com politica avancada
- callbacks de reconciliacao

Estimativa: 3 a 6 dias uteis.

## Plugin WordPress: Vale a Pena?

Resposta curta: **sim, em cenarios de escala ou regras avancadas**.

Para o seu contexto atual:

- **nao e obrigatorio no MVP**
- **e recomendado no medio prazo** se a integracao virar parte central da operacao

### Quando criar plugin faz muito sentido

1. Voce opera varios sites WordPress com politicas diferentes.
2. Precisa de transformacoes especificas por site (taxonomias, CPT, SEO, meta).
3. Quer idempotencia forte por `delivery_id`.
4. Precisa de trilha local de auditoria no WordPress.
5. Quer callbacks/eventos de volta para o Solis.

### Escopo sugerido do plugin

1. Endpoint REST custom:
   - `POST /wp-json/nosfir/v1/publish`
2. Validacao de seguranca:
   - token fixo ou assinatura HMAC
3. Idempotencia:
   - bloquear duplicidade por `delivery_id`
4. Mapeamento de payload:
   - post status, categorias, tags, featured media, meta
5. Retorno padronizado:
   - `post_id`, `status`, `url`, `error_code`, `error_message`
6. Tela administrativa basica:
   - status da integracao
   - logs recentes
   - chave de integracao

### Custo/beneficio do plugin

- Beneficio: governanca, previsibilidade e desacoplamento.
- Custo: desenvolvimento e manutencao de mais um artefato.

Conclusao objetiva:

- comece sem plugin para ganhar velocidade;
- evolua para plugin quando houver volume, variacao de regras ou necessidade de controle fino.

## Implementacao Inicial Ja Criada Neste Repositorio

O scaffold inicial do plugin WordPress ja foi criado em:

- `tools/wordpress-plugin/nosfir-solis-bridge/`

Arquivos principais:

- `tools/wordpress-plugin/nosfir-solis-bridge/nosfir-solis-bridge.php`
- `tools/wordpress-plugin/nosfir-solis-bridge/src/class-nosfir-solis-bridge-plugin.php`
- `tools/wordpress-plugin/nosfir-solis-bridge/uninstall.php`
- `tools/wordpress-plugin/nosfir-solis-bridge/README.md`

Capacidades do scaffold:

1. endpoint `GET /wp-json/nosfir/v1/health`
2. endpoint `POST /wp-json/nosfir/v1/publish`
3. autenticacao por `HMAC` ou `Bearer`
4. idempotencia por `delivery_id`
5. tela de configuracao no admin (`Settings -> Solis Bridge`)

Proximo passo tecnico recomendado:

1. conectar o `SocialPublishingService` do Solis para publicar no endpoint do plugin (`/wp-json/nosfir/v1/publish`).

## Decisoes em Aberto (Para fechar antes da implementacao)

1. Publicar no WordPress com `publish` imediato ou `draft` por padrao?
2. Suportar multiplos sites por usuario ja na fase 1?
3. Priorizar media upload na fase 1 ou deixar para fase 2?
4. Iniciar com endpoint nativo WP (`/wp/v2/posts`) ou ja nascer com plugin custom?
