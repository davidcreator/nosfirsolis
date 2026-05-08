# Checklist De Rotacao De Segredos (2026-05-07)

## Objetivo

Executar rotacao de credenciais apos exposicao historica de arquivos sensiveis no Git.

## Prioridade Imediata (Hoje)

1. Banco de dados:
   - alterar senha do usuario de aplicacao.
   - se possivel, trocar tambem o usuario de aplicacao.
2. `security.reinstall_key`:
   - gerar novo valor aleatorio forte.
3. `TOKEN_CIPHER_KEY`:
   - gerar nova chave e iniciar rotacao segura.
4. Tokens e credenciais de integracoes:
   - social OAuth (client secrets, refresh tokens quando aplicavel).
   - webhook secrets.
5. Sessoes:
   - invalidar sessoes ativas (forcar relogin).

## Ordem Recomendada (Sem Queda)

1. Preparacao:
   - abrir janela de manutencao controlada.
   - registrar responsavel e horario.
2. Banco:
   - criar nova senha/usuario.
   - atualizar ambiente (`system/Storage/config.php` local e/ou segredos do servidor).
   - validar conexao da aplicacao.
3. Token cipher:
   - definir nova chave em `TOKEN_CIPHER_KEY`.
   - mover chave antiga para `TOKEN_CIPHER_KEY_PREVIOUS`.
   - deploy e validar login/publicacao.
4. Integracoes externas:
   - regenerar segredos no provedor.
   - atualizar configuracoes no sistema.
   - validar callbacks/publicacoes.
5. Reinstall key:
   - substituir chave antiga.
6. Sessoes:
   - limpar sessoes ativas e exigir novo login.
7. Fechamento:
   - remover `TOKEN_CIPHER_KEY_PREVIOUS` apos janela de estabilizacao.
   - executar suite de seguranca.

## Comandos Uteis

Gerar chave de criptografia forte:

```bash
php tools/security/generate-token-cipher-key.php
```

Executar suite de seguranca:

```bash
php tests/security/run-security-suite.php
```

## Evidencias Minimas A Registrar

- data/hora da rotacao
- segredos rotacionados (sem expor valores)
- responsavel pela execucao
- validacoes realizadas (login, social, billing, webhooks)
- resultado da suite de seguranca

## Checklist Final

- [ ] Senha/usuario de banco rotacionados
- [ ] `security.reinstall_key` rotacionada
- [ ] `TOKEN_CIPHER_KEY` nova aplicada
- [ ] `TOKEN_CIPHER_KEY_PREVIOUS` removida apos estabilizacao
- [ ] Secrets/tokens de integracoes rotacionados
- [ ] Sessoes antigas invalidadas
- [ ] Suite de seguranca executada e aprovada
