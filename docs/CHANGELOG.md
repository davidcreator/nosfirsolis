# Changelog

Todas as mudancas relevantes de codigo e seguranca registradas neste diretorio de documentacao.

## 2026-04-17

### Corrigido

- Fatal em `FeatureFlagService` ao resolver flags de estrategia `permission` sem necessidade de consultar hierarquia de grupos.
- Compatibilidade com esquema legado sem coluna `user_groups.hierarchy_level`:
  - validacao de existencia da coluna
  - fallback seguro para evitar `PDOException` em runtime

### Seguranca

- Bloqueio de acesso HTTP direto a arquivos e caminhos internos via `.htaccess`:
  - `system/`
  - `system/Storage/`
  - `config.php`
  - `.env` e `.env.example`
  - `install/sql/`
- Hardening contra Host Header Injection:
  - nova camada `HostGuard`
  - validacao por allowlist (`ALLOWED_HOSTS` / `security.allowed_hosts`)
  - resposta `400` para host nao permitido
- Padronizacao de geracao de URLs absolutas para usar host efetivo validado.

### Configuracao e Deploy

- Inclusao de `ALLOWED_HOSTS` em:
  - `config.php` (merge de ambiente)
  - `system/Config/app.php` (default)
  - `.env.example`
  - exemplos de deploy Apache/Nginx/PHP-FPM
  - guias de seguranca e operacao

### Testes e Validacao

- `php tests/security/run-security-suite.php`: `PASS` (9/9)
- Lint PHP completo: `PASS`
- Validacoes HTTP locais:
  - hosts permitidos (`localhost`, `127.0.0.1`, `nosfirsolis.example.com`) retornando `200`
  - host forjado fora da allowlist retornando `400`
