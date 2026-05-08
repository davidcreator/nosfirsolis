# Mensagens Prontas Para Disparo - 2026-05-07

## Contexto Rapido

- Reescrita de historico Git concluida em `2026-05-07`.
- `main`: `93cee3a` -> `57d9e6d`.
- Operacao aplicada: `push --force --mirror`.
- Horario de referencia: `America/Sao_Paulo`.

## 1) WhatsApp (curta)

Time, aviso rapido: hoje (**2026-05-07**) concluimos reescrita de historico no repo para remover arquivos sensiveis.
`main` mudou de `93cee3a` para `57d9e6d`.
Sincronizem seus clones hoje na janela **19:00-20:30**:
`git fetch origin --prune`
`git checkout main`
`git reset --hard origin/main`
Se tiver mudanca local, salvem antes (commit/stash) e reapliquem depois.

## 2) Slack (completa)

### Incidente Tratado - Reescrita De Historico Concluida

Pessoal, em **quinta-feira, 2026-05-07**, finalizamos a reescrita de historico para remocao de segredos/sessoes que estavam no historico Git.

- Branch: `main`
- Hash antigo: `93cee3a`
- Hash novo: `57d9e6d`
- Metodo: `push --force --mirror`

Janela operacional:

- congelamento de push em `main`: `19:00-19:30`
- sincronizacao de clones: `19:30-20:00`
- validacao final: ate `20:30`

Acao por perfil:

1. Dev
   - salvar trabalho local (commit/stash)
   - `git fetch origin --prune`
   - `git checkout main`
   - `git reset --hard origin/main`
   - reaplicar mudancas
2. QA
   - sincronizar clone
   - rodar `php tests/security/run-security-suite.php`
   - esperado: `PASS (12 PASS, 0 FAIL, 0 WARN)`
3. Infra
   - confirmar hash remoto:
     - `git ls-remote origin refs/heads/main`
   - esperado:
     - `57d9e6df1131cc43fa221e0919515b8db74e88f0  refs/heads/main`

## 3) E-mail (assunto + corpo)

Assunto:

`[Solis] Reescrita de historico concluida em 2026-05-07 - acao obrigatoria de sincronizacao`

Corpo:

Prezados,

Informamos que em 2026-05-07 foi concluida reescrita de historico do repositorio Solis para remocao de arquivos sensiveis.

Dados da alteracao:

- branch: main
- hash anterior: 93cee3a
- hash atual: 57d9e6d
- tipo de alteracao: force push de historico reescrito

Acoes obrigatorias:

1. atualizar clone local para `origin/main` atual.
2. nao reutilizar base antiga da branch principal.
3. validar operacao local apos sincronizacao.

Comandos basicos:

- `git fetch origin --prune`
- `git checkout main`
- `git reset --hard origin/main`

Atenciosamente,
Time de Engenharia

## 4) Links de Apoio

- `docs/comunicado-operacional-slack-whatsapp-2026-05-07.md`
- `docs/sincronizacao-local-pos-rewrite-2026-05-07.md`
- `docs/checklist-rotacao-segredos-2026-05-07.md`
