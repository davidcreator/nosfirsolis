# Docs NosfirSolis

Este diretorio concentra a documentacao funcional e tecnica do Solis dentro do ecossistema Nosfir.
Atualizado em: **2026-05-08**.

## Como Navegar

1. [Produto: Nosfir x Solis](produto-nosfir-solis.md)
2. [Arquitetura MVCL](arquitetura-mvcl.md)
3. [Modulo Cliente](modulo-cliente.md)
4. [Modulo Admin](modulo-admin.md)
5. [Seguranca e Autenticacao](seguranca-e-autenticacao.md)
6. [Banco de Dados](banco-de-dados.md)
7. [Instalacao, Configuracao e Operacao](instalacao-configuracao-operacao.md)
8. [Integracoes, Automacoes e Manutencao](integracoes-automacoes-e-manutencao.md)
9. [Integracao com WordPress](integracao-wordpress.md)
10. [Plano Comercial e Monetizacao](plano-comercial-e-monetizacao.md)
11. [Seguranca e Sanitizacao para Producao](seguranca-e-sanitizacao-producao.md)
12. [Deploy Producao com Apache e Nginx](deploy-producao-apache-nginx-env.md)
13. [Limpeza de Historico Git (Segredos)](limpeza-historico-segredos-git.md)
14. [Comunicado de Reescrita de Historico](comunicado-reescrita-historico-2026-05-07.md)
15. [Checklist de Rotacao de Segredos](checklist-rotacao-segredos-2026-05-07.md)
16. [Sincronizacao Local Pos-Rewrite](sincronizacao-local-pos-rewrite-2026-05-07.md)
17. [Comunicado Operacional (Slack/WhatsApp)](comunicado-operacional-slack-whatsapp-2026-05-07.md)
18. [Mensagens Prontas Para Disparo](mensagens-prontas-disparo-2026-05-07.md)
19. [Mensagens Ultra Curta e Executiva](mensagens-ultra-curta-e-executiva-2026-05-07.md)
20. [Mensagem Final Formal](mensagem-final-formal-2026-05-07.md)
21. [Ata de Incidente de Seguranca](ata-incidente-seguranca-2026-05-07.md)
22. [Ata de Incidente (Assinatura)](ata-incidente-seguranca-assinatura-2026-05-07.md)
23. [Ata Curta para Diretoria](ata-curta-diretoria-2026-05-07.md)
24. [Checklist Formal de Homologacao de Seguranca](checklist-homologacao-seguranca-solis-2026-05-07.md)
25. [Pacote de Evidencias de Homologacao](pacote-evidencias-homologacao-seguranca-2026-05-07.md)
26. [Relatorio de Auditoria MVCL Estrutural](relatorio-auditoria-mvcl-estrutural-2026-05-07.md)
27. [Changelog](CHANGELOG.md)
28. [Checklist Formal de Fechamento para Producao](checklist-fechamento-producao-solis-2026-05-08.md)
29. [Homologacao Operacional Final de Producao](homologacao-operacional-final-producao-solis-2026-05-08.md)
30. [Release Notes de Producao](release-notes-producao-solis-2026-05-08.md)
31. [Pacote Formal de PR - Fechamento de Producao](pacote-formal-pr-fechamento-producao-solis-2026-05-08.md)
32. [PR Formal - Fechamento Tecnico de Producao](pr-formal-fechamento-producao-solis-2026-05-08.md)

## Guias Complementares (Fora Deste Diretorio)

- [Suite de seguranca: resumo de execucao](../tests/security/README.md)
- [Plugin WordPress MVP (Nosfir Solis Bridge)](../tools/wordpress-plugin/nosfir-solis-bridge/README.md)
- [Extensoes opcionais do instalador](../install/extensions/README.md)

## Objetivo Deste Pacote

- Registrar o que ja existe em producao no codigo.
- Facilitar onboarding tecnico e funcional.
- Dar visibilidade de responsabilidades por area (`client`, `admin`, `install`, `system`).
- Reduzir risco de regressao em futuras evolucoes.

## Nota De Estado

Na validacao local de 2026-05-07:

- `tests/security/run-security-suite.php`: `PASS` (24 passes, 0 falhas, 0 alertas)
- `tools/security/run-operational-audit.php`: `PASS` (17 passes, 0 falhas, 0 alertas)

Consulte os detalhes em `seguranca-e-sanitizacao-producao.md`, `checklist-homologacao-seguranca-solis-2026-05-07.md` e `pacote-evidencias-homologacao-seguranca-2026-05-07.md`.
