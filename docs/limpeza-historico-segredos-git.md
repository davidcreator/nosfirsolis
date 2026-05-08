# Limpeza De Historico Git (Segredos E Sessoes)

## Objetivo

Remover de todo o historico Git arquivos sensiveis que ja foram versionados por engano, sem alterar o repositorio de trabalho atual durante a preparacao.

Escopo atual da limpeza:

- `system/Storage/config.php`
- `system/Storage/config-local.php`
- `system/Storage/config copy.php`
- `system/Storage/sessions/sess_*`

## Risco E Impacto

- Reescrever historico muda hashes de commits.
- Push exige `--force --mirror`.
- Quem colabora no repositorio precisa reclonar ou resetar branch local.

Use este fluxo em janela controlada.

## Script Oficial

Arquivo:

- `tools/security/rewrite-sensitive-history.ps1`

O script:

1. Cria clone espelho isolado (bare mirror).
2. Executa `git filter-repo` somente no mirror.
3. Valida que os caminhos sensiveis sumiram do historico.
4. Opcionalmente faz push forcado (`-PushMirror`).

## Pre-Requisitos

1. `git` instalado.
2. `git-filter-repo` instalado:
   - `pip install git-filter-repo`
3. Rotacao de segredos planejada (DB/chaves/tokens), pois o segredo ja pode ter sido exposto.

## Execucao Recomendada (Modo Seguro, Sem Push)

No raiz do projeto:

```powershell
powershell -ExecutionPolicy Bypass -File tools/security/rewrite-sensitive-history.ps1
```

Resultado esperado:

- mirror criado em `system/Storage/exports/history-rewrite/nosfirsolis-mirror.git`
- historico reescrito no mirror
- validacao final sem caminhos sensiveis

## Publicacao Da Reescrita (Quando Aprovado)

Opcao 1 (automatizada pelo script):

```powershell
powershell -ExecutionPolicy Bypass -File tools/security/rewrite-sensitive-history.ps1 -PushMirror
```

Opcao 2 (manual):

```powershell
git -C "system/Storage/exports/history-rewrite/nosfirsolis-mirror.git" remote add origin <URL_DO_REMOTE>
git -C "system/Storage/exports/history-rewrite/nosfirsolis-mirror.git" push --force --mirror origin
```

## Pos-Push (Equipe)

1. Avisar que houve reescrita de historico.
2. Recomendar reclone completo do repositorio.
3. Se nao for possivel reclonar, executar reset hard para o novo `origin/main` em cada clone local.

## Sincronizar Clone Local Atual (Sem Reclone)

Se um clone local ficou `ahead/behind` apos a reescrita:

1. Salvar trabalho pendente (commit local ou stash).
2. Buscar refs atualizadas:
   - `git fetch origin --prune`
3. Alinhar branch principal ao novo historico remoto:
   - `git checkout main`
   - `git reset --hard origin/main`
4. Reaplicar trabalho local salvo (cherry-pick, merge ou apply patch).

### Registro desta execucao

- Data: `2026-05-07`
- Reescrita publicada em `origin/main`
- Hash anterior: `93cee3a`
- Novo hash: `57d9e6d`

## Checklist Final

- [ ] Segredos rotacionados no ambiente real.
- [ ] Push forcado da historia limpa concluido.
- [ ] Equipe notificada sobre reescrita.
- [ ] Suite de seguranca executada novamente (`php tests/security/run-security-suite.php`).
