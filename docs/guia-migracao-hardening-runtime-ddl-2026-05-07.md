# Guia Formal - Migracao de Hardening de DDL Runtime

Data: 2026-05-07  
Escopo: remover dependencia de mutacao de schema em runtime para fluxos de autenticacao, hierarquia, planner e servicos operacionais.

## 1. Objetivo

Aplicar ajustes de banco para que o sistema nao dependa de `ALTER TABLE`/`CREATE TABLE` em tempo de requisicao HTTP nos pontos:

- `users.language_code`
- `user_groups.hierarchy_level`
- `calendar_extra_events` (+ `color_hex`)
- `user_calendar_colors`

Complemento de consolidacao (sem DDL runtime no request path):

- `social_publications` / `social_publication_logs`
- `campaign_tracking_links`
- `feature_flags`
- `automations_webhooks` / `automation_dispatch_logs`
- `observability_events` / `observability_spans`
- `job_monitors` / `job_checkins` / `job_alerts`
- `security_login_attempts` / `security_audit_logs`
- `subscription_plans`, `plan_limits`, `user_subscriptions`, `billing_invoices`,
  `payment_transactions`, `subscription_events`, `billing_promotions`,
  `billing_announcements`, `user_feature_overrides`

## 2. Comando de Execucao

No diretorio raiz do projeto:

```bash
php tools/database/run-runtime-ddl-hardening-migration.php
```

## 3. Resultado Esperado

O script imprime status por item:

- `[APPLY]` quando aplicou ajuste de schema.
- `[SKIP]` quando o item ja estava conforme.
- `[FAIL]` quando houve erro (execucao deve ser interrompida e revisada).

Resumo final esperado:

- `Status: PASS` quando nao houver falhas.

## 4. Evidencia da Execucao Local (2026-05-07)

- Applied: 2
- Skipped: 4
- Failed: 0
- Status: PASS

## 5. Observacoes Operacionais

1. Executar em janela controlada quando houver ambientes legados.
2. Realizar backup antes de qualquer alteracao de schema em producao.
3. Reexecutar auditorias apos a migracao:
   - `php tools/architecture/run-mvcl-audit.php`
   - `php tests/security/run-security-suite.php`
   - `php tools/security/run-operational-audit.php`

4. Para ambientes legados, validar presenca das tabelas operacionais no schema antes do go-live.
