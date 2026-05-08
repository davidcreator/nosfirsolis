## Resumo Executivo

Descreva em 3-6 linhas o objetivo e o resultado tecnico desta PR.

## Escopo Tecnico

- Modulos/camadas impactadas:
- Tipo principal da alteracao (`feat`, `fix`, `refactor`, `security`, `docs`, `test`, `chore`):
- Mudancas fora de escopo (se houver):

## Evidencias De Validacao

Liste comandos e resultados relevantes.

```bash
php tools/quality/run-quality-gates.php --exit-mode=bitmap
php tests/security/run-security-suite.php
```

- Resultado quality gates:
- Resultado security suite:
- Outros testes:

## Impacto Arquitetural (MVCL)

- [ ] Controllers mantem orquestracao sem regra de negocio pesada
- [ ] Models/Services concentram regra de negocio e acesso a dados
- [ ] Views sem logica sensivel
- [ ] Nao houve regressao de separacao por camadas

## Checklist De Seguranca

- [ ] Nenhum segredo/sessao/runtime sensivel foi versionado
- [ ] Entradas de usuario novas/alteradas estao sanitizadas/validadas
- [ ] Fluxos de autenticacao/autorizacao impactados foram testados
- [ ] Mudancas de host/proxy/cookies/sessao foram validadas

## Banco De Dados E Migracao

- [ ] Sem alteracao de schema
- [ ] Com alteracao de schema (descrever)
- [ ] Migracao reversivel documentada
- [ ] Sem DDL no caminho de requisicao

## Risco E Rollback

- Nivel de risco: `baixo` | `medio` | `alto`
- Plano de rollback:
- Sinais de monitoracao pos-merge:

## Documentacao

- [ ] Nao aplicavel
- [ ] README atualizado
- [ ] `docs/` atualizado com evidencias tecnicas
