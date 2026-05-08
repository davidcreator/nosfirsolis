# Comunicado Operacional (Slack E WhatsApp) - 2026-05-07

## Objetivo

Template pronto para comunicar a reescrita de historico e coordenar a sincronizacao da equipe com comandos por perfil.

## Janela Recomendada

- Inicio: `2026-05-07 19:00` (America/Sao_Paulo)
- Congelamento de push na `main`: `19:00` ate `19:30`
- Sincronizacao dos clones: `19:30` ate `20:00`
- Validacao final: ate `20:30`

## Mensagem Curta (WhatsApp)

Time, aplicamos reescrita de historico no repo hoje (2026-05-07) para remover arquivos sensiveis.
`main` mudou de `93cee3a` para `57d9e6d` via `push --force --mirror`.
Por favor, sincronizem seus clones hoje na janela combinada.
Guia rapido:
1) salvar mudancas locais
2) `git fetch origin --prune`
3) `git checkout main`
4) `git reset --hard origin/main`
5) reaplicar mudancas locais

Se preferirem, reclonem o repo.

## Mensagem Completa (Slack)

### Aviso Importante - Reescrita De Historico Concluida

Realizamos em **2026-05-07** uma reescrita de historico Git para remover segredos/sessoes versionados por engano.

- Branch: `main`
- Hash antigo: `93cee3a`
- Hash novo: `57d9e6d`
- Operacao: `push --force --mirror`

Impacto:

- clones antigos podem ficar `ahead/behind` simultaneamente.
- historico local antigo da `main` nao deve mais ser base para novos pushes.

Acao obrigatoria hoje:

1. sincronizar clone local (ou reclonar).
2. validar build/testes basicos apos sincronizacao.
3. evitar push para `main` antes de alinhar com `origin/main`.

### Comandos Por Perfil

#### Dev

Se tem mudancas locais:

```bash
git checkout -b backup/pre-rewrite-2026-05-07
git add -A
git commit -m "backup local antes de alinhar historico reescrito"
git fetch origin --prune
git checkout main
git reset --hard origin/main
git cherry-pick <commit-do-backup>
```

Se nao tem mudancas locais:

```bash
git fetch origin --prune
git checkout main
git reset --hard origin/main
```

#### QA

```bash
git fetch origin --prune
git checkout main
git reset --hard origin/main
php tests/security/run-security-suite.php
```

Resultado esperado da suite:

- `PASS` com `12 PASS`, `0 FAIL`, `0 WARN`

#### Infra/Release

```bash
git ls-remote origin refs/heads/main
```

Esperado:

- `57d9e6df1131cc43fa221e0919515b8db74e88f0    refs/heads/main`

Checklist de infra:

1. confirmar que pipelines usam novo historico.
2. confirmar que nenhum job de deploy referencia hash antigo.
3. reforcar rotacao de credenciais pendentes (DB/chaves/tokens).

## Referencias

- `docs/relatorio-analise-profunda-quotia-2026-05-07.md`
- `docs/limpeza-historico-segredos-git.md`
- `docs/checklist-rotacao-segredos-2026-05-07.md`
- `docs/sincronizacao-local-pos-rewrite-2026-05-07.md`
