# Checklist Formal de Homologacao de Seguranca - Solis

Data de referencia: 2026-05-07  
Escopo: arquitetura, controles de borda HTTP, sessao, sanitizacao, banco e readiness operacional.

## 1. Objetivo

Estabelecer criterios tecnicos de aceite para homologacao de seguranca do sistema Solis, com evidencias objetivas e reexecucao padronizada.

## 2. Estado Atual Validado

Resultados obtidos nesta data:

- `php tests/security/run-security-suite.php`
  - Passes: 24
  - Warnings: 0
  - Failures: 0
  - Status: PASS
- `php tools/security/run-operational-audit.php`
  - Passes: 17
  - Warnings: 0
  - Failures: 0
  - Status: PASS

## 3. Criterios de Aceite (Go/No-Go)

Marcar como **GO** apenas quando todos os itens obrigatorios estiverem atendidos.

- [x] Security suite sem falhas (`Status: PASS`).
- [x] Auditoria operacional sem falhas (`Status: PASS`).
- [x] `security.runtime_schema_mutations=false` no ambiente homologado/produtivo.
- [x] `security.auth.fail_open_on_security_error=false` no ambiente homologado/produtivo.
- [x] `HOST_GUARD_COMPATIBILITY_MODE=0` em homologacao/producao.
- [x] `AUTOMATION_ALLOW_PRIVATE_WEBHOOK_ENDPOINTS=0` por padrao.
- [x] `TRUSTED_PROXIES` definido conforme topologia real.
- [x] `ALLOWED_HOSTS` restrito aos dominios oficiais do ambiente.
- [x] Runtime storage config sem senha de banco em texto claro.
- [x] Conta de aplicacao sem privilegios DDL em ambiente homologado/produtivo.
- [ ] Validacao manual de fluxos criticos (login, logout, reset de senha, exclusoes sensiveis, tracking redirect).

## 4. Procedimento de Revalidacao

Executar na raiz do projeto:

```bash
php tests/security/run-security-suite.php
php tools/security/run-operational-audit.php
```

Critico:

- Qualquer `FAIL` bloqueia liberacao.
- Qualquer `WARN` deve ter aceite formal de risco ou plano de remediacao com prazo.

## 5. Evidencias Minimas a Arquivar

- log completo da suite de seguranca;
- log completo da auditoria operacional;
- hash/identificacao do build liberado;
- snapshot de configuracao efetiva (sem expor segredos) para:
  - `APP_ENV`
  - `ALLOWED_HOSTS`
  - `TRUSTED_PROXIES`
  - flags de seguranca (`runtime_schema_mutations`, `fail_open_on_security_error`);
- registro de usuario de banco da aplicacao e grants aplicados.

## 6. Controles de Continuidade

- periodicidade recomendada:
  - suite de seguranca: a cada release;
  - auditoria operacional: a cada release e apos mudancas de infraestrutura;
- revisao trimestral de grants do banco;
- revisao trimestral de CSP/HSTS e dependencias frontend que impactem politica de scripts;
- rotacao planejada de segredos conforme politica interna.

## 7. Plano de Contingencia

Em caso de regressao de seguranca apos release:

1. congelar novas mudancas no ambiente impactado;
2. restaurar configuracao validada anterior;
3. reexecutar suite e auditoria;
4. abrir incidente tecnico com RCA e prazo de correcao;
5. liberar novamente apenas com `PASS` completo.

## 8. Decisao de Homologacao

Status desta avaliacao: **APTO TECNICAMENTE (condicionado a validacao manual dos fluxos criticos).**

