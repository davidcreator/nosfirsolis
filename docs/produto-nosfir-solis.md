# Produto: Nosfir x Solis

## Contexto De Produto

O **NosfirSolis** nao e um sistema isolado de escopo universal.
Ele representa o **Solis**, um sub-sistema dentro do ecossistema **Nosfir**.

## Papel Do Solis

Resolver operacao de conteudo ponta a ponta em um escopo tatico:

- planejamento anual/mensal por calendario
- organizacao de campanhas, datas e sugestoes editoriais
- execucao orientada por status e rotina diaria
- apoio a distribuicao social em multiplos canais
- tracking de campanha com links rastreaveis
- gestao basica de planos e cobranca no proprio produto

## Escopo Atual (Implementado)

O Solis cobre hoje:

- planejamento editorial e operacional
- gestao de base estrategica (feriados, comemorativas, sugestoes, campanhas)
- central social para conexoes, drafts, presets e fila de publicacao
- tracking de campanhas (UTM/MTM, short links e cliques)
- billing interno (planos, limites, promocoes, anuncios, faturas e pagamento mock)
- governanca operacional (feature flags, webhooks, monitores de jobs e observabilidade)

## Limites De Escopo (Nao Coberto Nativamente)

No estado atual, o Solis ainda nao cobre nativamente:

- gateway real de pagamentos com conciliacao bancaria completa
- billing multi-tenant corporativo com repasse fiscal complexo
- analytics avancado em stack de BI externa
- operacao omnichannel completa alem do foco social/editorial

## Publicos Do Sistema

- **Equipe de operacao de conteudo**: usa modulo cliente para auth, dashboard, calendario, planos, social, tracking e billing.
- **Equipe administrativa**: usa modulo admin para governanca de base, usuarios/hierarquia, operacoes e monetizacao.
- **Equipe tecnica**: mantem instalacao, seguranca, configuracao e evolucao do core.
