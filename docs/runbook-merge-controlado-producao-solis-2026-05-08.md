# Runbook Formal - Merge Controlado para Producao Solis

**Data:** 2026-05-08  
**Objetivo:** conduzir o merge da branch de fechamento tecnico com governanca, rastreabilidade e criterio de rollback.

## 1. Escopo e branch

1. **Branch de origem:** `feat/critical-flow-maturity-gate-clean`
2. **Branch de destino:** `main`
3. **Estado atual:** branch remota sincronizada e apta para abertura de PR.

## 2. Precondicoes obrigatorias

1. Garantir que a PR use o corpo formal de:
   - `docs/pr-formal-fechamento-producao-solis-2026-05-08.md`
2. Confirmar evidencias tecnicas:
   - `docs/pacote-formal-pr-fechamento-producao-solis-2026-05-08.md`
   - `docs/homologacao-operacional-final-producao-solis-2026-05-08.md`
   - `docs/release-notes-producao-solis-2026-05-08.md`
3. Confirmar `quality gates`, `security suite`, `operational audit` e `critical flow suite` em `PASS`.

## 3. Abertura da PR

1. Abrir:
   - `https://github.com/davidcreator/nosfirsolis/compare/main...feat/critical-flow-maturity-gate-clean?expand=1`
2. Titulo recomendado:
   - `release: fechamento tecnico de producao (MVCL, seguranca, composicao e homologacao operacional)`
3. Corpo:
   - copiar integralmente de `docs/pr-formal-fechamento-producao-solis-2026-05-08.md`

## 4. Fluxo de aprovacao formal

1. Revisao de arquitetura MVCL.
2. Revisao de seguranca e controles de ambiente.
3. Revisao operacional (build, smoke e rollback).
4. Aprovar somente com checklist completo e sem bloqueios de risco alto.

## 5. Procedimento de merge

1. Realizar merge da PR para `main` sem commits adicionais fora do escopo aprovado.
2. Criar tag de release (recomendado):
   - `v2026.05.08-prod-closure`
3. Sincronizar ambiente local apos merge:

```bash
git checkout main
git pull origin main
```

## 6. Validacoes pos-merge (obrigatorias)

Executar:

```bash
php tools/quality/run-quality-gates.php
php tests/security/run-security-suite.php
php tools/security/run-operational-audit.php
php tests/critical/run-critical-flow-suite.php
composer --working-dir=system build
composer --working-dir=prod/system build
```

Critero de aceite:

1. Todos os comandos acima em `PASS` ou sucesso tecnico equivalente.
2. Smoke HTTP funcional:
   - `/`
   - `/client/auth/login`
   - `/admin/auth/login`

## 7. Janela de deploy assistido (producao)

1. Aplicar variaveis reais de ambiente:
   - `APP_ENV=production`
   - `ALLOWED_HOSTS`
   - `TOKEN_CIPHER_KEY` e rotacao quando aplicavel
   - credenciais SMTP e DB de producao
2. Publicar espelho de artefatos em `prod/`.
3. Executar smoke no ambiente alvo apos publicacao.

## 8. Rollback controlado

1. Reverter para commit/tag estavel anterior em producao.
2. Restaurar build anterior de `prod/`.
3. Reexecutar smoke e auditorias minimas para confirmar estabilizacao.

## 9. Decisao final

**Status tecnico recomendado:** `GO` para merge controlado e inicio de deploy assistido, condicionado ao preenchimento seguro das variaveis de ambiente no destino.
