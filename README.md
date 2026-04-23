# NosfirSolis

Solis e um sistema tatico dentro do ecossistema **Nosfir**.  
Ele foi desenhado para resolver um recorte especifico: **estrategias de campanhas e execucao de conteudo para redes sociais**.

## Posicionamento No Ecossistema Nosfir

- **Nosfir**: ecossistema maior, com frentes amplas de operacao digital.
- **Solis (NosfirSolis)**: modulo menor, focado em planejamento estrategico de campanhas, calendario editorial e execucao social.

## O Que Ja Esta Implementado

- Area cliente com:
  - dashboard estrategico
  - dashboard executivo com visao de tracking/publicacao/jobs/erros
  - calendario unificado (anual, mensal e por periodo)
  - planos editoriais por periodo e por templates anuais
  - detalhes do plano com insights, filtros, atualizacao individual e em lote de status
  - exportacao CSV de planos
  - central social com conexoes OAuth/manual, drafts estrategicos, presets de formato e hub de publicacao multi-canal
  - modulo de rastreamento de campanhas (UTM/MTM, short links e cliques)
- Area admin com:
  - dashboard administrativo
  - CRUD de feriados, comemorativas, sugestoes, canais e campanhas
  - gestao de usuarios com controle de hierarquia por nivel
  - central de operacoes com feature flags, webhooks, monitores de jobs e observabilidade
- Instalador com validacao de ambiente, seed inicial e bloqueio de reinstalacao sem chave/permissao.
- Camada de seguranca com:
  - rate limit de login por IP/email
  - logs de auditoria
  - validacao de sessao por fingerprint e TTL
  - controle de acesso por area (`admin` e `client`) baseado em permissoes.

## Documentacao Organizada

Indice geral: [`docs/README.md`](docs/README.md)

1. [`docs/produto-nosfir-solis.md`](docs/produto-nosfir-solis.md) - contexto de produto e escopo Solis dentro do Nosfir
2. [`docs/arquitetura-mvcl.md`](docs/arquitetura-mvcl.md) - estrutura, pipeline e camadas
3. [`docs/modulo-cliente.md`](docs/modulo-cliente.md) - dashboard, calendario, planos e social
4. [`docs/modulo-admin.md`](docs/modulo-admin.md) - governanca de base estrategica e usuarios
5. [`docs/seguranca-e-autenticacao.md`](docs/seguranca-e-autenticacao.md) - auth por area, sessao e auditoria
6. [`docs/banco-de-dados.md`](docs/banco-de-dados.md) - tabelas, relacionamentos e enums
7. [`docs/instalacao-configuracao-operacao.md`](docs/instalacao-configuracao-operacao.md) - instalacao, configuracao e rotina operacional
8. [`docs/integracoes-automacoes-e-manutencao.md`](docs/integracoes-automacoes-e-manutencao.md) - passo a passo das integracoes, operacao e manutencao futura
9. [`docs/integracao-wordpress.md`](docs/integracao-wordpress.md) - guia tecnico completo para integrar publicacao do Solis com WordPress, com analise sobre plugin dedicado
10. [`docs/plano-comercial-e-monetizacao.md`](docs/plano-comercial-e-monetizacao.md) - plano de negocios completo com monetizacao, super admin, planos e pagamentos
11. [`docs/seguranca-e-sanitizacao-producao.md`](docs/seguranca-e-sanitizacao-producao.md) - bateria de testes de seguranca, endurecimentos aplicados e checklist para producao
12. [`docs/deploy-producao-apache-nginx-env.md`](docs/deploy-producao-apache-nginx-env.md) - roteiro pronto de deploy com variaveis de ambiente no host (Apache/Nginx)

## Fluxo Rapido De Operacao

1. Time admin configura base estrategica (`/admin`): campanhas, canais, sugestoes, feriados e hierarquia.
2. Time admin governa operacoes (`/admin/operations`): feature flags, webhooks, jobs e observabilidade.
3. Time cliente opera estrategia (`/client`): cria planos, atualiza status (individual/lote), usa calendario e exporta CSV.
4. Time cliente conecta e publica (`/client/social`): OAuth/manual, drafts, presets e fila de publicacao.
5. Time cliente rastreia campanhas (`/client/tracking`): links UTM/MTM, short links e cliques.

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

## Variaveis de Ambiente

O `config.php` carrega automaticamente o arquivo `.env` na raiz do projeto (quando presente).
Em producao, a precedencia e: variaveis do host/processo > `.env`.

Use `.env.example` como base e configure:

- `TOKEN_CIPHER_KEY`: segredo forte para criptografia de tokens.
- `TOKEN_CIPHER_KEY_PREVIOUS`: chave(s) anterior(es) para decrypt durante rotacao (opcional, separado por virgula).
- `TRUSTED_PROXIES`: lista separada por virgula de IPs/CIDRs confiaveis.
- `AUTOMATION_ALLOW_PRIVATE_WEBHOOK_ENDPOINTS`: `0` (recomendado) ou `1`.

Gerar chave forte:

```bash
php tools/security/generate-token-cipher-key.php
```

## Instalacao Rapida

1. Acesse `http://localhost/nosfirsolis/install`
2. Preencha banco + administrador.
3. Conclua instalacao.
4. Acesse:
   - Cliente: `http://localhost/nosfirsolis` ou `http://localhost/nosfirsolis/client`
   - Admin: `http://localhost/nosfirsolis/admin`

## Composer

O Composer e utilizado dentro de `system/` com vendor em `system/Vendor`.

```bash
cd system
composer install
```

## Testes de Seguranca

Suite automatizada de hardening e sanitizacao:

```bash
php tests/security/run-security-suite.php
```
