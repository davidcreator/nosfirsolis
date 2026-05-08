# Contribuicao Tecnica - NosfirSolis

Este documento estabelece o fluxo oficial de contribuicao para manter o repositorio organizado, seguro e aderente ao padrao MVCL.

## 1. Principios

- Toda alteracao deve ter escopo tecnico claro e rastreavel.
- Commits devem ser pequenos, coerentes e ordenados por assunto.
- Nenhum segredo, sessao, exportacao ou artefato sensivel pode ser versionado.
- Toda mudanca deve preservar conformidade MVCL, seguranca e operacao.

## 2. Estrategia De Branch

- `main`: branch protegida para codigo estavel.
- `feat/<tema>`: novas funcionalidades.
- `fix/<tema>`: correcao de defeitos.
- `refactor/<tema>`: reorganizacao de arquitetura sem alterar regra de negocio.
- `security/<tema>`: hardening e correcoes de seguranca.
- `docs/<tema>`: documentacao tecnica.

Regras:

- Sempre criar branch a partir de `main` atualizada.
- Evitar commits diretos em `main`.
- Antes de abrir PR, rebase ou sincronizacao com `main` para reduzir conflitos.

## 3. Padrao De Commit

Formato:

```text
tipo: descricao objetiva
```

Tipos aceitos:

- `feat`
- `fix`
- `refactor`
- `security`
- `docs`
- `chore`
- `test`

Exemplos:

- `security: harden session cookie flags for production`
- `refactor: split planner lifecycle into dedicated traits`
- `docs: update mvcl audit evidence after quality gates`

## 4. Ordem Recomendada De Commits

Quando a entrega envolver mais de um eixo, seguir a ordem:

1. `refactor` (estrutura e separacao de responsabilidades)
2. `security` (hardening e sanitizacao)
3. `feat` ou `fix` (comportamento funcional)
4. `test` (cobertura e suites)
5. `docs` (evidencias e operacao)

## 5. Validacoes Obrigatorias Antes Do Push

Executar no diretorio raiz:

```bash
php tools/quality/run-quality-gates.php --exit-mode=bitmap
php tests/security/run-security-suite.php
```

Critério:

- Nao abrir PR com `FAIL`.
- Qualquer `WARN` deve ser descrito e justificado no PR.

## 6. Regras De Seguranca E Sanitizacao

- Nunca versionar arquivos de `system/Storage/` com dados de runtime.
- Manter somente placeholders `.gitignore` e `config-dist.php`.
- Nao incluir credenciais reais em codigo, docs, seeds, logs ou scripts.
- Qualquer mudanca de autenticacao, sessao, cookies, host guard ou proxies exige evidencia de teste.

## 7. Regras De Revisao (PR)

- PR deve ter objetivo, escopo, risco e plano de rollback.
- PR deve listar comandos executados e resultados.
- PR deve indicar impacto em:
  - MVCL
  - seguranca
  - banco/migracao
  - operacao/deploy

## 8. Estrategia De Merge

- Preferencia: merge preservando historico de commits logicos.
- Evitar squash quando a branch ja estiver organizada por camadas tecnicas.
- Em hotfix critico, permitir fluxo acelerado com posterior normalizacao documental.

## 9. Responsabilidade Tecnica

- Quem abre o PR e responsavel por conformidade tecnica integral.
- Aprovadores validam risco sistemico, nao apenas sintaxe.
- Decisoes de excecao devem ficar registradas em `docs/`.
