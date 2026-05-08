# Checklist de Execucao Go-Live - Producao Solis

**Data:** 2026-05-08  
**Objetivo:** executar o go-live em ambiente de producao com controle operacional, criterio de aceite e plano de rollback.

## 1. Estado de referencia (baseline)

1. `origin/main`: `2246cb9`
2. Tag de release: `v2026.05.08-prod-closure`
3. Quality Gates no baseline: `PASS` (`6/6`)

## 2. Janela de mudanca

1. [ ] Janela formal aprovada por operacoes/negocio.
2. [ ] Responsaveis definidos:
   - [ ] Aplicacao
   - [ ] Infraestrutura
   - [ ] Banco de dados
   - [ ] Suporte/monitoracao
3. [ ] Canal de comunicacao ativo para incidentes (war-room).

## 3. Pre-go-live (T-60 a T-15)

1. [ ] Confirmar backup atual de banco e artefatos.
2. [ ] Confirmar `APP_ENV=production`.
3. [ ] Confirmar `ALLOWED_HOSTS` com dominios oficiais.
4. [ ] Confirmar `TOKEN_CIPHER_KEY` e politica de rotacao.
5. [ ] Confirmar `TRUSTED_PROXIES` conforme topologia real.
6. [ ] Confirmar SMTP real (`MAIL_DRIVER=smtp`) com credenciais validas.
7. [ ] Confirmar credenciais `DB_MIGRATION_*` para operacoes de schema quando aplicavel.
8. [ ] Confirmar `AUTOMATION_ALLOW_PRIVATE_WEBHOOK_ENDPOINTS=0`.

## 4. Build e publicacao (T-15 a T-5)

Executar:

```bash
composer --working-dir=system build
composer --working-dir=prod/system build
```

Checklist:

1. [ ] Build `system/` concluido sem erro.
2. [ ] Build `prod/system/` concluido sem erro.
3. [ ] Conteudo de `prod/` sincronizado no destino de producao.
4. [ ] Permissoes de escrita validadas em `system/Storage` (cache/sessions/logs/exports).

## 5. Validacoes de go-live (T0 a T+15)

Executar:

```bash
php tools/quality/run-quality-gates.php
php tests/security/run-security-suite.php
php tools/security/run-operational-audit.php
php tests/critical/run-critical-flow-suite.php
```

Smoke HTTP obrigatorio:

1. [ ] `/` -> `200`
2. [ ] `/client/auth/login` -> `200`
3. [ ] `/admin/auth/login` -> `200`
4. [ ] Fluxo "Esqueci minha senha" interno ao Solis (sem redirecionamento externo).
5. [ ] Fluxo "Esqueci meu email" interno ao Solis (sem redirecionamento externo).

## 6. Critero de aceite

Go-live aprovado somente se:

1. [ ] Todos os comandos de validacao em `PASS`.
2. [ ] Sem erro 5xx nos endpoints criticos.
3. [ ] Sem falha de envio SMTP nos fluxos de recuperacao.
4. [ ] Sem alerta critico de seguranca no audit operacional.

## 7. Monitoracao pos-go-live (T+15 a T+120)

1. [ ] Monitorar taxa de erro HTTP (4xx/5xx) por 2 horas.
2. [ ] Monitorar latencia de login cliente/admin.
3. [ ] Monitorar tentativas de reset de senha e fila de e-mail.
4. [ ] Registrar ocorrencias no log operacional.

## 8. Rollback (se criterio de aceite falhar)

1. [ ] Reverter para commit/tag estavel anterior.
2. [ ] Restaurar build anterior de `prod/`.
3. [ ] Reexecutar smoke de `/`, `/client/auth/login`, `/admin/auth/login`.
4. [ ] Comunicar status e causa raiz inicial no canal de incidentes.

## 9. Encerramento formal

1. [ ] Registrar horario de inicio/fim da janela.
2. [ ] Registrar hash final em producao.
3. [ ] Registrar decisao final: `GO` ou `ROLLBACK`.
4. [ ] Publicar ata curta de encerramento para diretoria/operacoes.
