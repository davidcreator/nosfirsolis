# NosfirSolis

Solis e um sistema tatico dentro do ecossistema **Nosfir**.
Ele foca planejamento estrategico, execucao diaria, publicacao social, tracking e operacao comercial basica.

## Escopo Atual (2026-05-05)

- Area cliente com:
  - login, cadastro publico e recuperacao de senha por token
  - recuperacao de e-mail de acesso via e-mail de recuperacao
  - ativacao automatica de assinatura no plano Basico Gratuito apos cadastro
  - dashboard estrategico e dashboard executivo
  - calendario unificado (anual, mensal e por periodo)
  - planos editoriais por periodo e por templates anuais
  - detalhe de plano com filtros, insights e atualizacao individual/em lote
  - exportacao CSV
  - central social (OAuth/manual, drafts, presets e fila de publicacao)
  - tracking de campanhas (UTM/MTM, short links e cliques)
  - area de planos e faturamento (upgrade/downgrade, limites, historico de faturas e pagamento)
- Area admin com:
  - dashboard administrativo
  - CRUD de feriados, comemorativas, sugestoes, canais e campanhas
  - usuarios e hierarquia por nivel
  - filtros salvos para lista de usuarios
  - gestao de plano e recursos por usuario
  - central de operacoes (feature flags, webhooks, monitores, alertas, observabilidade e limpeza de cache)
  - central de billing (planos, limites, promocoes, comunicados, conta recebedora e validacao manual de pagamentos)
- Instalador com validacao de ambiente, seed inicial e bloqueio de reinstalacao sem chave/permissao.
- Camada de seguranca com:
  - rate limit de login por IP/email
  - logs de auditoria
  - validacao de sessao por fingerprint e TTL
  - controle de acesso por area (`admin` e `client`)
  - allowlist de hosts (`ALLOWED_HOSTS`) contra Host Header Injection

## Estado Atual De Seguranca

Ultima execucao local em **2026-05-07**:

- comando: `php tests/security/run-security-suite.php`
- resultado: `PASS` (12 `PASS`, 0 `FAIL`, 0 `WARN`)
- destaque: inclui checks de host guard, `allowed_hosts` e versionamento de arquivos sensiveis de storage
- observacao operacional: historico Git reescrito em `2026-05-07` (`main`: `93cee3a` -> `57d9e6d`); colaboradores devem reclonar ou resetar branch local.

## Documentacao Organizada

Indice geral: [`docs/README.md`](docs/README.md)

1. [`docs/produto-nosfir-solis.md`](docs/produto-nosfir-solis.md) - contexto de produto e escopo atualizado
2. [`docs/arquitetura-mvcl.md`](docs/arquitetura-mvcl.md) - estrutura, pipeline e camadas
3. [`docs/modulo-cliente.md`](docs/modulo-cliente.md) - auth, dashboard, calendario, planos, social, tracking e billing
4. [`docs/modulo-admin.md`](docs/modulo-admin.md) - governanca de base, usuarios, operacoes e billing
5. [`docs/seguranca-e-autenticacao.md`](docs/seguranca-e-autenticacao.md) - auth por area, sessao e auditoria
6. [`docs/banco-de-dados.md`](docs/banco-de-dados.md) - dominios de tabelas e relacionamentos
7. [`docs/instalacao-configuracao-operacao.md`](docs/instalacao-configuracao-operacao.md) - instalacao e rotina operacional
8. [`docs/integracoes-automacoes-e-manutencao.md`](docs/integracoes-automacoes-e-manutencao.md) - integracoes e manutencao
9. [`docs/integracao-wordpress.md`](docs/integracao-wordpress.md) - guia de integracao com WordPress
10. [`docs/plano-comercial-e-monetizacao.md`](docs/plano-comercial-e-monetizacao.md) - plano comercial de referencia
11. [`docs/seguranca-e-sanitizacao-producao.md`](docs/seguranca-e-sanitizacao-producao.md) - hardening e suite de seguranca
12. [`docs/deploy-producao-apache-nginx-env.md`](docs/deploy-producao-apache-nginx-env.md) - deploy com variaveis de ambiente
13. [`docs/limpeza-historico-segredos-git.md`](docs/limpeza-historico-segredos-git.md) - runbook para reescrita segura de historico Git
14. [`docs/comunicado-reescrita-historico-2026-05-07.md`](docs/comunicado-reescrita-historico-2026-05-07.md) - comunicado pronto para equipe apos rewrite
15. [`docs/checklist-rotacao-segredos-2026-05-07.md`](docs/checklist-rotacao-segredos-2026-05-07.md) - checklist de rotacao de credenciais
16. [`docs/sincronizacao-local-pos-rewrite-2026-05-07.md`](docs/sincronizacao-local-pos-rewrite-2026-05-07.md) - guia para alinhar clones sem perder trabalho local
17. [`docs/comunicado-operacional-slack-whatsapp-2026-05-07.md`](docs/comunicado-operacional-slack-whatsapp-2026-05-07.md) - mensagem pronta por perfil (dev/qa/infra) para janela de sincronizacao
18. [`docs/mensagens-prontas-disparo-2026-05-07.md`](docs/mensagens-prontas-disparo-2026-05-07.md) - versoes prontas para WhatsApp, Slack e e-mail
19. [`docs/mensagens-ultra-curta-e-executiva-2026-05-07.md`](docs/mensagens-ultra-curta-e-executiva-2026-05-07.md) - versoes ultra curta e executiva para status rapido e diretoria
20. [`docs/mensagem-final-formal-2026-05-07.md`](docs/mensagem-final-formal-2026-05-07.md) - pacote formal consolidado para comunicacao oficial
21. [`docs/ata-incidente-seguranca-2026-05-07.md`](docs/ata-incidente-seguranca-2026-05-07.md) - ata formal para registro institucional e auditoria
22. [`docs/ata-incidente-seguranca-assinatura-2026-05-07.md`](docs/ata-incidente-seguranca-assinatura-2026-05-07.md) - versao de assinatura com termo de ciencia e aprovacoes formais
23. [`docs/ata-curta-diretoria-2026-05-07.md`](docs/ata-curta-diretoria-2026-05-07.md) - versao resumida de 1 pagina para decisao e assinatura da diretoria
24. [`docs/CHANGELOG.md`](docs/CHANGELOG.md) - historico das mudancas documentadas

Documentacao complementar fora de `docs/`:

- [`tests/security/README.md`](tests/security/README.md) - resumo da suite de seguranca
- [`tools/wordpress-plugin/nosfir-solis-bridge/README.md`](tools/wordpress-plugin/nosfir-solis-bridge/README.md) - plugin WordPress MVP
- [`install/extensions/README.md`](install/extensions/README.md) - extensoes opcionais do instalador

## Governanca De Contribuicao

- [`CONTRIBUTING.md`](CONTRIBUTING.md) - fluxo formal de branches, commits, PR e gates obrigatorios

## Fluxo Rapido De Operacao

1. Admin configura base estrategica (`/admin`) e usuarios/hierarquia.
2. Admin governa operacoes (`/admin/operations`): flags, webhooks, jobs, observabilidade e cache.
3. Admin governa monetizacao (`/admin/billing`): planos, promocoes, comunicados e validacoes.
4. Cliente opera estrategia (`/client`): planos, calendario e status.
5. Cliente conecta/publica (`/client/social`): OAuth/manual, drafts e fila de publicacao.
6. Cliente rastreia campanhas (`/client/tracking`): links UTM/MTM, short links e cliques.
7. Cliente gerencia assinatura (`/client/billing`): plano ativo, limites, faturas e pagamentos.

## Estrutura MVCL

```text
admin/
client/
install/
system/
config.php
index.php
```

## Requisitos

- PHP 8.1+
- MySQL 5.7+ ou 8+
- Extensoes PHP:
  - `pdo_mysql`
  - `mbstring`
  - `json`
  - `openssl`
- Apache com `mod_rewrite`

## Variaveis De Ambiente

`config.php` carrega automaticamente o arquivo `.env` na raiz do projeto (quando presente).
Em producao, a precedencia e: variaveis do host/processo > `.env`.

Use `.env.example` como base e configure:

- `APP_ENV`: `development` (local) ou `production` (online)
- `TOKEN_CIPHER_KEY`: segredo forte para criptografia de tokens
- `TOKEN_CIPHER_KEY_PREVIOUS`: chave(s) anterior(es) para decrypt durante rotacao
- `TRUSTED_PROXIES`: IPs/CIDRs confiaveis
- `ALLOWED_HOSTS`: hosts permitidos separados por virgula
- `HOST_GUARD_COMPATIBILITY_MODE`: manter `0` em producao (habilitar `1` apenas em migracao legada controlada)
- `AUTOMATION_ALLOW_PRIVATE_WEBHOOK_ENDPOINTS`: `0` (recomendado) ou `1`
- `MAIL_DRIVER`: `php_mail` (padrao) ou `smtp`
- `MAIL_FROM_EMAIL`, `MAIL_FROM_NAME`: remetente padrao
- `MAIL_SMTP_HOST`, `MAIL_SMTP_PORT`, `MAIL_SMTP_ENCRYPTION`, `MAIL_SMTP_USERNAME`, `MAIL_SMTP_PASSWORD`: configuracao SMTP
- `DB_MIGRATION_*`: credenciais privilegiadas para migracoes DDL (recomendado em producao)

Gerar chave forte:

```bash
php tools/security/generate-token-cipher-key.php
```

## Instalacao Rapida

1. Acesse `http://localhost/nosfirsolis/install`.
2. Preencha banco + administrador.
3. Selecione ambiente (`development` ou `production`) e hosts permitidos.
4. Conclua instalacao.
5. Acesse:
   - Cliente: `http://localhost/nosfirsolis` ou `http://localhost/nosfirsolis/client`
   - Admin: `http://localhost/nosfirsolis/admin`

## Composer

Composer e utilizado dentro de `system/` com vendor em `system/Vendor`.

```bash
cd system
composer install
composer build
```

## Build De Producao (Pasta `prod`)

Para gerar o espelho de producao na raiz do projeto:

1. `composer --working-dir=system build`
2. copiar projeto para `prod/` (sem `.git`, `docs/`, `tests/`, `tools/`)
3. `composer --working-dir=prod/system build`

A pasta `prod/` possui `.gitignore` proprio para evitar duplicacao de artefatos no GitHub.

## Testes De Seguranca

```bash
php tests/security/run-security-suite.php
```
