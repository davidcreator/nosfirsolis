# Relatório Técnico de Segurança do Sistema Solis

Data de emissão: 07/05/2026  
Responsável técnico: Engenharia de Software (análise assistida)

## 1. Objetivo

Este relatório consolida a avaliação técnica de segurança do Solis com foco em:

- arquitetura e aderência ao padrão MVCL;
- controles de autenticação, sessão, headers e roteamento;
- áreas de sanitização de entrada/saída;
- maturidade de segurança operacional;
- riscos residuais e plano de evolução.

## 2. Escopo e método

Escopo analisado:

- camada de entrada e bootstrap (`index.php`, `system/Engine/*`);
- autenticação e sessão (`system/Library/Auth.php`, `system/Engine/Session.php`);
- serviços e modelos com persistência de dados e automações;
- controladores client/admin e pontos de mutação de estado;
- suíte técnica de segurança (`tests/security/run-security-suite.php`).

Método aplicado:

- revisão de código estática dirigida a vetores OWASP (A01, A03, A05, A07);
- validação de hardening por configuração;
- execução de suíte de segurança local.
- auditoria operacional dedicada (`tools/security/run-operational-audit.php`) para baseline + banco.

Resultado de validação executada em 07/05/2026:

- Passes: 21
- Warnings: 0
- Failures: 0
- Status: PASS

## 3. Arquitetura e padrão MVCL

### 3.1 Aderência estrutural

O sistema mantém separação coerente entre:

- **Model**: regras de persistência e consultas;
- **View**: renderização de interface;
- **Controller**: orquestração de fluxo e autorização;
- **Library** (camada de serviço): capacidades transversais (segurança, observabilidade, automação, social, assinatura).

Conclusão: há aderência funcional ao padrão MVCL com evolução para um estilo híbrido (MVC + camada de serviços), adequado para crescimento modular.

### 3.2 Pontos fortes de arquitetura

- controle de acesso por área (admin/client) e permissões;
- centralização de segurança em serviços (`SecurityService`, `HostGuard`, `Auth`);
- resposta HTTP com headers de segurança aplicados de forma global;
- suíte de segurança para prevenir regressões.

## 4. Controles de segurança implementados

### 4.1 Autenticação e sessão

- login com `password_hash/password_verify`;
- proteção contra brute force e trilha de auditoria;
- rotação de sessão em login bem-sucedido;
- fingerprint e TTL de sessão;
- política de fail-open/fail-closed configurável para indisponibilidade de serviço de segurança.

### 4.2 Proteções de borda HTTP

- validação de host permitido com `HostGuard` (incluindo landing);
- proteção contra open redirect/CRLF em `Response::redirect`;
- baseline de headers de segurança com CSP e HSTS condicional por ambiente HTTPS.

### 4.3 CSRF e mutação de estado

- ações mutáveis protegidas por POST + verificação CSRF;
- formulários POST com inclusão de token CSRF;
- ausência de links GET para ações sensíveis de mutação.

### 4.4 Persistência e schema runtime

Foi consolidada a política de **não executar DDL em runtime por padrão**:

- serviços/modelos com `CREATE TABLE`/`ALTER TABLE` agora respeitam `security.runtime_schema_mutations`;
- quando a flag está desativada, o sistema não tenta mutar schema automaticamente.

Impacto de segurança:

- reduz necessidade de privilégios DDL na conta da aplicação;
- fortalece princípio de menor privilégio em produção;
- reduz risco de alterações estruturais não controladas em tempo de execução.

Complemento estrutural aplicado:

- o `install/sql/schema.sql` foi atualizado para incluir tabelas operacionais de social hub, tracking, automações, observabilidade, monitoramento de jobs, feature flags e overrides de recursos por usuário, reduzindo dependência de auto-criação em tempo de execução.

## 5. Áreas de sanitização

### 5.1 Entrada

- normalização e validação em controladores para parâmetros de rota, IDs, status, datas e cores;
- validação de URLs em componentes de tracking/webhook;
- restrição de domínios/host em pontos de construção de URL absoluta.

### 5.2 Saída

- uso predominante de escape HTML via helper `e()` nas views;
- sanitização de redirecionamentos;
- escape aplicado em atributos de interface e parâmetros refletidos.

### 5.3 Banco de dados

- uso majoritário de queries parametrizadas (`prepare/execute`);
- SQL dinâmico com listas controladas por normalização de tipos/valores.

## 6. Maturidade de segurança (estado atual)

Classificação proposta: **Nível 3 (Definido/Padronizado), em transição para Nível 4 (Gerenciado)**.

Evidências de maturidade:

- controles preventivos já padronizados em autenticação, CSRF e headers;
- política explícita para mutação de schema runtime;
- auditoria e telemetria de eventos de segurança;
- suíte automatizada de verificação com cobertura de pontos críticos.

Lacunas para avançar ao Nível 4:

- pipeline formal de migração de banco segregado da aplicação;
- hardening complementar de sessão/cookies por ambiente;
- testes dinâmicos recorrentes (DAST) e revisão periódica de permissões DB.

## 7. Riscos residuais e prioridades

### Prioridade Alta

- garantir processo formal de migrações (CI/CD) para evitar dependência de auto-provisionamento local;
- validar em produção que a conta da aplicação não possui privilégios DDL.

### Prioridade Média

- ampliar cobertura de testes para cenários de ausência de tabelas com `runtime_schema_mutations=false`;
- revisar periodicamente políticas de webhook (endpoint allowlist e monitoramento SSRF).

### Prioridade Baixa

- incrementar métricas de segurança operacional (tempo de resposta a alertas, taxa de bloqueio por brute force, etc.).

## 8. Recomendações executivas

1. Manter `security.runtime_schema_mutations=false` em produção e homologação.
2. Executar migrações apenas via processo controlado (janela de mudança + auditoria).
3. Aplicar revisão trimestral de permissões da conta de banco da aplicação.
4. Instituir rotina mensal da suíte de segurança no pipeline.

## 9. Conclusão

O Solis apresenta evolução sólida de segurança sistêmica, com base técnica consistente em autenticação, proteção de borda, sanitização e governança de schema runtime.  
No estado atual, o sistema está operacionalmente mais resiliente e alinhado a práticas de menor privilégio, com condição favorável para avançar para um patamar de segurança gerenciado em ambiente produtivo.
