# Changelog

Todas as mudancas relevantes de codigo e seguranca registradas neste diretorio de documentacao.

## 2026-04-28

### Configuracao e Deploy

- Padronizacao de ambiente com `APP_ENV` (`development`/`production`) no bootstrap.
- Instalador atualizado para receber ambiente e hosts permitidos e gravar `.env` automaticamente.
- Runtime de instalacao agora persiste `app.environment` e `security.allowed_hosts` em `system/Storage/config.php`.
- `index.php` passou a reconhecer instalacao ativa via `system/Storage/config.php`, reduzindo dependencia de sobrescrever `config.php` raiz em reinstalacoes.
- Merge de configuracao aprimorado para listas (`array_is_list`) com substituicao completa (evita mistura de `allowed_hosts` entre defaults e runtime).

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
