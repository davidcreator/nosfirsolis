# Relatorio Tecnico Formal - Hardening de Seguranca do Solis

Data: 2026-05-07  
Escopo: arquitetura de seguranca, superficie HTTP, sessao, configuracao, secrets hygiene e auditoria operacional.

## 1. Resumo Executivo

Foi executado um ciclo adicional de hardening com foco em seguranca sistemica (nao documental).  
O ambiente local ficou com baseline tecnico mais consistente para operacao segura:

- deteccao HTTPS unificada e consciente de proxy confiavel;
- CSP padrao endurecida (sem `unsafe-eval` por default);
- migracao de segredo de banco para `.env` no instalador e em ambiente legado;
- auditoria operacional alinhada ao runtime efetivo (overrides por variavel de ambiente).

Estado final desta rodada:

- `tests/security/run-security-suite.php`: **PASS** (24 passes, 0 warnings, 0 failures).
- `tools/security/run-operational-audit.php`: **PASS** (17 passes, 0 warnings, 0 failures).

Observacao: o warning residual de grants foi encerrado com a adocao de conta local de aplicacao sem privilegios DDL.

## 2. Intervencoes Tecnicas Aplicadas

## 2.1 HTTPS e proxy confiavel (edge security)

Arquivos afetados:

- `config.php`
- `index.php`
- `system/Engine/Application.php`
- `system/Engine/Session.php`

Melhorias:

- criada deteccao central `nosfir_request_is_https()` com suporte a:
  - `HTTPS`, `SERVER_PORT`, `REQUEST_SCHEME`;
  - `X-Forwarded-Proto`, `X-Forwarded-Ssl`, `Forwarded`;
  - validacao de proxy confiavel por IP/CIDR.
- `index.php` e `Application` passam a usar a mesma logica para:
  - schema efetivo (`http`/`https`);
  - emissao correta de HSTS;
  - cookie de sessao `Secure` atras de proxy confiavel.

Impacto:

- reduz falso negativo de HTTPS em arquitetura com reverse proxy/CDN;
- evita degradacao de protecoes (HSTS/cookie secure) por deteccao incompleta.

## 2.2 CSP padrao mais restritiva

Arquivos afetados:

- `config.php`
- `system/Config/app.php`
- `.env.example`

Melhorias:

- CSP default removendo `unsafe-eval`;
- adicionada flag de compatibilidade `CSP_ALLOW_UNSAFE_EVAL` (default `0`);
- log explicito quando `CSP_ALLOW_UNSAFE_EVAL=1` em producao.

Impacto:

- reduz superficie para execucao dinamica de script;
- preserva fallback controlado para compatibilidade legada.

## 2.3 Secrets hygiene (instalacao e legado)

Arquivos afetados:

- `install/Model/InstallerModel.php`
- `tools/security/harden-runtime-storage-config.php` (novo)

Melhorias:

- instalador passa a gravar `DB_*` no `.env`;
- `system/Storage/config.php` deixa de persistir senha de banco em claro (`password` vazio no runtime file);
- utilitario de hardening para ambientes ja instalados:
  - migra `DB_*` do runtime config para `.env`;
  - remove senha explicita do runtime config;
  - sincroniza `app.environment` com `APP_ENV` quando divergente.

Impacto:

- reduz exposicao de credenciais em arquivo runtime;
- melhora governanca de segredo por ambiente.

## 2.4 Auditoria operacional alinhada ao runtime efetivo

Arquivo afetado:

- `tools/security/run-operational-audit.php`

Melhoria:

- auditoria passa a aplicar overrides de `APP_ENV` e `DB_*` antes de avaliar baseline, refletindo comportamento real de runtime.

Impacto:

- reduz falsos positivos de ambiente;
- aumenta confiabilidade do diagnostico operacional.

## 2.5 Cobertura de teste ampliada

Arquivo afetado:

- `tests/security/run-security-suite.php`

Melhorias:

- nova verificacao de cobertura HTTPS proxy-aware na landing (`index.php`);
- verificacoes reforcadas para uso de `nosfir_request_is_https`;
- alerta para CSP contendo `unsafe-eval`.

## 3. Evidencias de Validacao

## 3.1 Security Suite

Comando:

```bash
php tests/security/run-security-suite.php
```

Resultado:

- Passes: 24
- Warnings: 0
- Failures: 0
- Status: PASS

## 3.2 Operational Audit

Comando:

```bash
php tools/security/run-operational-audit.php
```

Resultado:

- Passes: 17
- Warnings: 0
- Failures: 0
- Status: PASS

Estado atual:

- sem falhas e sem warnings na auditoria operacional local.

## 4. Avaliacao de Maturidade (Seguranca do Sistema)

Classificacao atual sugerida: **Nivel 3 alto, em transicao para Nivel 4**.

Justificativa:

- controles tecnicos de borda e sessao estao mais padronizados;
- baseline de configuracao em producao esta mais defensiva;
- higiene de segredos melhorou (instalacao + legado);
- a separacao de conta local sem DDL ja foi aplicada, aproximando a postura operacional ao padrao de producao.

## 5. Risco Residual e Acao Imediata Necessaria

Risco residual principal (baixo, ambiente local):

- manter governanca de segredos e rotacao de credenciais em ciclos de manutencao.

Acao de continuidade recomendada:

1. preservar o usuario local de menor privilegio para execucao da aplicacao;
2. manter `runtime_schema_mutations=false` fora de janelas controladas;
3. incluir auditoria operacional na rotina de release local/homolog.

## 6. Conclusao

O sistema evoluiu de forma concreta na camada tecnica de seguranca (arquitetura de borda, sessao, configuracao e secrets).  
No estado atual, nao ha falhas nem warnings na auditoria operacional local.  
Com os ajustes aplicados, o ambiente converge para baseline operacional aderente ao padrao de seguranca definido.
