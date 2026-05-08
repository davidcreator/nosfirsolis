# Sincronizacao Local Pos-Rewrite (Preservando Mudancas)

## Quando Usar

Use este guia se seu clone local ficou com status parecido com:

- `main...origin/main [ahead X, behind Y]`

e voce tem trabalho local que nao pode perder.

## Caminho Recomendado (Seguro)

### 1) Salvar mudancas locais antes de alinhar

Opcao A - commit de seguranca em branch temporaria:

```bash
git checkout -b backup/pre-rewrite-2026-05-07
git add -A
git commit -m "backup local antes de alinhar historico reescrito"
```

Opcao B - stash:

```bash
git stash push -u -m "backup pre-rewrite 2026-05-07"
```

Opcao C - patch local:

```bash
git diff > backup-pre-rewrite.patch
git diff --cached >> backup-pre-rewrite.patch
```

### 2) Alinhar main com remoto reescrito

```bash
git fetch origin --prune
git checkout main
git reset --hard origin/main
```

### 3) Reaplicar seu trabalho

Se usou branch de backup:

```bash
git checkout main
git cherry-pick <commit-do-backup>
```

Se usou stash:

```bash
git stash list
git stash pop
```

Se usou patch:

```bash
git apply --reject --whitespace=fix backup-pre-rewrite.patch
```

### 4) Validar

```bash
php tests/security/run-security-suite.php
```

## Dicas

- Prefira branch de backup quando houver muitas alteracoes.
- Se houver conflitos apos reaplicar, resolva por modulo e rode validacao por etapa.
- Evite continuar trabalho em clones ainda desalinhados com `origin/main` reescrita.
