# Plano Comercial e Monetizacao - NosfirSolis

## Objetivo

Definir um plano de negocio completo para monetizar o Solis como SaaS B2B, com governanca centralizada por um `super_admin` para administrar clientes, usuarios, planos e pagamentos.

## Contexto de Produto

- `Nosfir`: ecossistema maior com frentes amplas de operacao digital.
- `Solis`: modulo tatico do Nosfir para estrategia e execucao de campanhas em redes sociais.

## 1) Tese de Negocio

O Solis gera valor em tres pontos:

- planejamento estrategico com calendario, campanhas e planos editoriais
- execucao operacional com hub social e automacoes
- governanca com visibilidade de performance, custos e risco por cliente

Hipotese principal:

- agencias e times de marketing de PMEs pagam por previsibilidade, padronizacao e ganho de produtividade

## 2) ICP e Segmentacao

### ICP 1 - Agencias pequenas

- time de 2 a 10 pessoas
- operacao multi-cliente
- dor principal: retrabalho e falta de padrao

### ICP 2 - PME com time interno

- 1 a 5 pessoas de marketing
- dor principal: baixa previsibilidade e pouca visibilidade de resultado

### ICP 3 - Operacoes com multiplas marcas

- grupos e franquias
- dor principal: governanca, compliance e controle de permissao

## 3) Oferta Comercial

### Planos base

| Plano | Preco sugerido | Perfil | Limites principais |
| --- | ---: | --- | --- |
| Start | R$ 99/mes | PME em inicio | 1 workspace, 3 usuarios, 10 perfis sociais |
| Growth | R$ 299/mes | Time em expansao | 1 workspace, 10 usuarios, 30 perfis, automacoes |
| Agency | R$ 799/mes | Agencia multi-cliente | 5 workspaces, 25 usuarios, 100 perfis, prioridade |
| Enterprise | Sob consulta | Operacao complexa | SSO, SLA dedicado, limites customizados |

### Add-ons

- usuario extra: R$ 29/mes
- pacote extra de 10 perfis sociais: R$ 49/mes
- automacoes premium: R$ 99/mes
- suporte prioritario: R$ 399/mes

### Regras comerciais

- trial: 14 dias
- plano anual: desconto de 20%
- upgrade imediato com prorata
- downgrade no proximo ciclo
- cancelamento com politica clara de aviso e retencao de dados

## 4) Estrutura de Hierarquia e Permissoes

### Escopo de contas

- `super_admin`: operacao dona da plataforma
- `tenant`: conta cliente pagante
- `tenant_user`: usuario interno do cliente

### Papeis recomendados

- `super_admin`: acesso global, billing, risco, auditoria, configuracoes de plataforma
- `billing_admin`: gestao financeira sem poder tecnico total
- `support_admin`: suporte, operacao e saude da carteira
- `tenant_owner`: dono da conta cliente
- `tenant_admin`: administracao da equipe do cliente
- `operator`: uso diario de campanha e publicacao
- `finance`: leitura de plano, faturas e pagamentos

### Matriz de governanca minima

- somente `super_admin` pode criar/editar plano comercial
- somente `super_admin` e `billing_admin` podem estornar, conceder credito e perdoar fatura
- `tenant_owner` pode trocar plano e adicionar usuarios
- `tenant_admin` pode gerenciar operacao, sem mexer em configuracao global de faturamento

## 5) Console do Super Admin (Funcionalidades Necessarias)

### A) Gestao de clientes

- criar, suspender, reativar e cancelar tenants
- visualizar historico de plano e de uso
- score de saude por cliente (ativacao, uso, risco de churn, inadimplencia)

### B) Gestao de usuarios

- convidar, bloquear, redefinir papel e resetar acesso
- aplicar politicas de seguranca por perfil
- acompanhar log de alteracoes por usuario

### C) Catalogo de planos e precos

- criar versao de plano
- definir limites, add-ons, periodo e moeda
- cupons, desconto e regra promocional com validade

### D) Assinaturas

- estados: `trial`, `active`, `past_due`, `suspended`, `canceled`
- upgrade/downgrade com prorata
- pausa e retomada controlada

### E) Faturas e cobranca

- emissao de invoices
- consolidacao de itens (plano, add-ons, ajustes)
- nota de credito/debito
- exportacao CSV e relatorio mensal

### F) Pagamentos e conciliacao

- captura de evento por webhook
- conciliacao automatica por `payment_transaction_id`
- fila de revisao manual para divergencias
- estorno e disputa com trilha de aprovacao

### G) Dunning e recuperacao

- retentativas configuraveis
- notificacoes por email e in-app
- bloqueio progressivo por atraso
- reativacao automatica apos confirmacao de pagamento

### H) Auditoria e compliance

- trilha imutavel de eventos financeiros
- log de alteracao de preco/plano
- log de acoes de acesso administrativo

## 6) Fluxo Operacional de Receita

1. Lead vira trial.
2. Trial ativa conta com primeiro plano e publica primeira campanha.
3. Conversao para assinatura paga.
4. Cobranca recorrente e conciliacao de pagamento.
5. Monitoramento de uso para expansion (upgrade/add-on).
6. Em caso de atraso: dunning, suspensao e recuperacao.
7. Renovacao anual e estrategia de retencao.

## 7) Arquitetura de Billing e Pagamento

### Principios

- gateway plugavel para evitar dependencia unica
- tokenizacao no provedor de pagamento
- nenhum dado sensivel de cartao armazenado localmente

### Stack sugerida

- gateway primario: Stripe (billing maduro, subscriptions, portal)
- gateway secundario BR: Asaas ou Mercado Pago (pix, boleto, cartao)

### Componentes tecnicos

- `billing_service`: assinatura, ciclo e fatura
- `payment_service`: captura de pagamento, estorno e conciliacao
- `provider_adapter`: camada de integracao por gateway
- `event_log`: trilha de eventos financeiros e operacionais
- `notification_service`: notificacoes de ciclo e inadimplencia

## 8) Modelo de Dados Minimo (Backlog Tecnico)

Tabelas recomendadas:

- `tenants`
- `tenant_users`
- `roles`
- `subscription_plans`
- `plan_limits`
- `tenant_subscriptions`
- `subscription_items`
- `invoices`
- `invoice_items`
- `payment_transactions`
- `payment_webhook_events`
- `credit_notes`
- `coupons`
- `audit_logs`

Campos chave recomendados:

- `provider` e `provider_reference_id` para rastrear origem
- `status` padronizado para assinatura, fatura e pagamento
- `occurred_at` e `processed_at` para confiabilidade de evento

## 9) KPIs do Super Admin

### Receita

- MRR e ARR
- ARPA por segmento
- churn de receita e churn de clientes
- LTV/CAC

### Operacao de produto

- tempo medio de onboarding
- taxa de ativacao (primeiro plano criado + primeira publicacao)
- uso semanal por tenant
- consumo de limites por plano

### Financeiro

- taxa de inadimplencia
- taxa de recuperacao de dunning
- tempo medio de conciliacao
- taxa de chargeback/disputa

### Sucesso do cliente

- NRR (net revenue retention)
- expansao por add-on
- saude por cohort

## 10) Projecao Financeira Base (12 meses)

Premissa de carteira no mes 12:

- 70 clientes Start
- 40 clientes Growth
- 10 clientes Agency

Calculo:

- MRR base = `70*99 + 40*299 + 10*799 = R$ 26.880`
- MRR add-ons (20%) = `R$ 5.376`
- MRR total estimado = `R$ 32.256`
- ARR estimado = `R$ 387.072`

Metas recomendadas:

- churn mensal < 3,5%
- LTV/CAC >= 3
- CAC payback <= 8 meses
- margem bruta > 75%

## 11) Go-To-Market

### Canais

- inbound com conteudo estrategico por nicho
- outbound para agencias e consultorias
- parcerias com especialistas em automacao e social media
- programa de indicacao com comissao recorrente

### Funil

1. Visitante
2. Lead
3. Diagnostico
4. Trial
5. Ativacao
6. Pagante
7. Expansao

## 12) Roadmap de Execucao (90 dias)

### Fase 1 (Semanas 1 a 3) - Fundacao comercial

- definir grade de planos e limites
- criar papeis e permissoes do super admin
- implementar cadastro de tenant com owner

### Fase 2 (Semanas 4 a 6) - Billing core

- assinatura recorrente
- invoices e itens de cobranca
- webhooks de pagamento
- estados de ciclo e prorata

### Fase 3 (Semanas 7 a 9) - Operacao financeira

- dunning com retentativa automatica
- conciliacao e fila manual de divergencia
- estorno e disputa com aprovacao por papel

### Fase 4 (Semanas 10 a 12) - Escala e governanca

- dashboard executivo do super admin
- relatorios de receita e inadimplencia
- auditoria completa e playbook de suporte

## 13) Operacao e Manutencao Continua

### Rotina semanal

- revisar clientes em risco
- revisar falhas de webhook e conciliacao
- revisar tendencia de upgrade/downgrade

### Rotina mensal

- fechamento de receita e inadimplencia
- revisar rentabilidade por plano
- revisar politicas de preco, limite e desconto

### Rotina trimestral

- revisar unit economics
- revisar estrategia GTM
- revisar contratos e compliance

## 14) Riscos e Mitigacoes

- churn alto em SMB:
  - mitigar com onboarding assistido, templates prontos e plano anual
- dependencia de gateway unico:
  - mitigar com provider secundario e abstraction layer
- falhas de integracao externa:
  - mitigar com fila, retentativa, circuit breaker e observabilidade
- risco de governanca:
  - mitigar com RBAC, auditoria e revisao periodica de acesso

## 15) Checklist de Decisao Imediata

1. Confirmar mercado inicial (Brasil) e moeda principal (BRL).
2. Escolher gateway primario e secundario.
3. Validar precificacao com 10 clientes piloto.
4. Definir metas de 12 meses (MRR, churn, payback).
5. Priorizar backlog tecnico de super admin billing.
6. Definir responsavel por operacao financeira e compliance.

## Referencias

- Stripe Billing: https://docs.stripe.com/billing
- Stripe Connect: https://docs.stripe.com/connect
- Asaas Docs: https://docs.asaas.com
- Mercado Pago Developers: https://www.mercadopago.com.br/developers
- Buffer Pricing: https://buffer.com/pricing
- Hootsuite Plans: https://www.hootsuite.com/plans
- Later Pricing: https://later.com/pricing
