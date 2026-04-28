# Instalacao, Configuracao E Operacao

## Objetivo

Este guia documenta como instalar, configurar e operar o Solis dentro do ecossistema Nosfir.

## Requisitos Tecnicos

- PHP 8.1+
- MySQL 5.7+ ou 8+
- Apache com `mod_rewrite`
- Extensoes PHP:
  - `pdo_mysql`
  - `mbstring`
  - `json`
  - `openssl`
- Permissao de escrita em `system/Storage`

## Instalacao Inicial

1. Posicione o projeto em um host acessivel (ex.: `http://localhost/nosfirsolis`).
2. Instale dependencias PHP do core:
   ```bash
   cd system
   composer install
   ```
3. Acesse `http://localhost/nosfirsolis/install`.
4. Preencha:
   - dados de banco (`host`, `port`, `database`, `user`, `password`)
   - usuario administrador inicial (`nome`, `email`, `senha`)
   - timezone e idioma
   - ambiente (`development` local ou `production` online)
   - hosts permitidos (separados por virgula)
5. Conclua a instalacao e siga o redirecionamento para `/client`.

## O Que O Instalador Gera

Durante a instalacao o sistema:

- executa `install/sql/schema.sql`
- executa `install/sql/seed.sql`
- cria o usuario admin inicial
- grava configuracao de runtime em:
  - `admin/config.php`
  - `system/Storage/config.php` (ou `system/storage/config.php`, conforme o host)
  - `.env` (atualiza `APP_ENV`, `ALLOWED_HOSTS` e segredos de runtime)

## Ordem De Carga Das Configuracoes

`System\Engine\Application` aplica configuracoes nesta ordem:

1. `system/Config/app.php`
2. `system/Config/database.php`
3. `system/Config/routes_{area}.php`
4. `config.php` (raiz)
5. `{area}/config.php` (ex.: `admin/config.php`)
6. `system/Storage/config.php` (compatibilidade/runtime)

Arquivos carregados por ultimo podem sobrescrever valores anteriores.

## Configuracao Pos-Instalacao

### Aplicacao E Sessao

Revise em `config.php` (defaults) e `system/Storage/config.php`:

- `app.environment`
- `app.base_url`
- `app.timezone`
- `app.default_language`
- `app.session_name`

### Banco De Dados

Garanta valores corretos de:

- `database.host`
- `database.port`
- `database.database`
- `database.username`
- `database.password`

### Seguranca

Revise:

- `security.allow_reinstall` (manter `false` em operacao normal)
- `security.reinstall_permission`
- `security.reinstall_key` (valor secreto)
- `security.allowed_hosts` (em dev inclui localhost; em producao use dominios oficiais)
- `APP_ENV` e `ALLOWED_HOSTS` no `.env`
- parametros de auth/rate-limit em `system/Config/app.php` (`security.auth.*`)

### Integracoes Sociais

Preencha credenciais de OAuth em `system/Config/app.php`, bloco:

- `integrations.social.instagram`
- `integrations.social.facebook`
- `integrations.social.linkedin`
- `integrations.social.tiktok`
- `integrations.social.x-twitter`
- `integrations.social.pinterest`
- `integrations.social.threads`
- `integrations.social.youtube`
- `integrations.social.vimeo`

### Publicacao Social E Tracking

Revise tambem:

- `integrations.social_publisher.dry_run`
- `integrations.social_publisher.linkedin_version`
- `integrations.tracking.bitly_access_token`

### Observabilidade

- `integrations.observability.sentry_enabled`
- `integrations.observability.sentry_dsn`

## Fluxo Operacional Recomendado

### Passo 1: Setup Administrativo

No `/admin`:

- manter feriados e comemorativas
- manter canais/plataformas e campanhas
- manter sugestoes estrategicas
- criar usuarios e ajustar `hierarchy_level` dos grupos
- configurar `admin/operations`:
  - feature flags
  - webhooks
  - monitores de jobs
  - acompanhamento de observabilidade

### Passo 2: Operacao De Conteudo

No `/client`:

- criar planos por periodo ou por template anual
- revisar itens no detalhe do plano
- atualizar status individual e em lote
- registrar observacoes no calendario
- exportar CSV para apoio operacional

### Passo 3: Operacao Social

No `/client/social`:

- conectar contas (OAuth ou manual)
- gerar drafts estrategicos
- manter presets de formato por plataforma
- operar hub de publicacao (fila, publicar agora, processar lote)

### Passo 4: Tracking De Campanhas

No `/client/tracking`:

- criar links UTM/MTM
- usar short links internos (ou Bitly)
- acompanhar cliques por campanha/canal

## Controle De Acesso E Hierarquia

- Login e validado por area (`admin` e `client`) com base em `permissions_json`.
- `admin.*` ou `*` permite acesso admin.
- `client.*` ou `*` permite acesso cliente.
- Em hierarquia de grupos, numero menor = maior autoridade.
- Admin nao pode criar/editar niveis acima da propria autoridade.

## Reinstalacao Segura (Somente Emergencia)

Para liberar reinstalacao apos sistema instalado:

1. Garantir usuario logado no admin com permissao `admin.install.reinstall`.
2. Ajustar `security.allow_reinstall = true`.
3. Definir/usar `security.reinstall_key` forte.
4. Acessar:
   - `http://localhost/nosfirsolis/install?reinstall_key=<chave>`
5. Executar reinstalacao.
6. Encerrar janela de risco:
   - voltar `security.allow_reinstall` para `false`
   - rotacionar `security.reinstall_key`

## Troubleshooting Rapido

- Redireciona sempre para `/install`:
  - conferir `app.installed = true` e conexao com banco.
- Login bloqueado:
  - checar limite em `security.auth.*` e aguardar janela de bloqueio.
- Instalador retorna bloqueado/403:
  - validar permissao admin, flag de reinstalacao e chave.
- Mensagem `Bad Request: host nao permitido.`:
  - revisar `APP_ENV` (`production` online) e `ALLOWED_HOSTS` no ambiente.
  - incluir o host/dominio real de acesso em `ALLOWED_HOSTS`.
- Erro de conexao MySQL:
  - revisar host/porta/credenciais e permissao do usuario de banco.

## Checklist De Operacao Continua

- backup diario do banco
- revisao periodica de usuarios e permissoes
- auditoria de eventos criticos (`security_audit_logs`)
- atualizacao da base estrategica (campanhas, datas, sugestoes)
- revisao de chaves e segredos de OAuth
- revisao de feature flags por ambiente
- revisao de falhas de webhook (`automation_dispatch_logs`)
- revisao de alertas de jobs (`job_alerts`)
- revisao de erros de observabilidade (`observability_events`)
