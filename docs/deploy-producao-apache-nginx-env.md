# Deploy Producao com Variaveis de Ambiente (Apache e Nginx)

## Objetivo

Padronizar deploy do NosfirSolis em producao sem depender de `.env` no servidor.

## Variaveis necessarias

- `TOKEN_CIPHER_KEY`
- `TOKEN_CIPHER_KEY_PREVIOUS` (somente durante rotacao)
- `TRUSTED_PROXIES`
- `ALLOWED_HOSTS`
- `AUTOMATION_ALLOW_PRIVATE_WEBHOOK_ENDPOINTS`

Exemplo para multiplos dominios:

- `ALLOWED_HOSTS=app.exemplo.com,www.exemplo.com,painel.exemplo.com`

## Gerar chave forte

```bash
php tools/security/generate-token-cipher-key.php
```

## 1) Apache (VirtualHost)

Use o exemplo:

- `docs/examples/apache/vhost-nosfirsolis.conf`

Passos comuns (Debian/Ubuntu):

```bash
sudo cp docs/examples/apache/vhost-nosfirsolis.conf /etc/apache2/sites-available/nosfirsolis.conf
sudo a2ensite nosfirsolis.conf
sudo a2enmod rewrite headers
sudo systemctl reload apache2
```

## 2) Nginx + PHP-FPM

Use o exemplo:

- `docs/examples/nginx/nosfirsolis.conf`

Passos comuns (Debian/Ubuntu):

```bash
sudo cp docs/examples/nginx/nosfirsolis.conf /etc/nginx/sites-available/nosfirsolis
sudo ln -s /etc/nginx/sites-available/nosfirsolis /etc/nginx/sites-enabled/nosfirsolis
sudo nginx -t
sudo systemctl reload nginx
```

## 3) Opcional: variaveis no pool do PHP-FPM

Se preferir definir no pool do PHP-FPM (em vez de `fastcgi_param`):

Arquivo exemplo: `/etc/php/8.2/fpm/pool.d/www.conf`

```ini
clear_env = no
env[TOKEN_CIPHER_KEY] = replace_with_strong_key
env[TOKEN_CIPHER_KEY_PREVIOUS] =
env[TRUSTED_PROXIES] = 127.0.0.1,::1
env[ALLOWED_HOSTS] = nosfirsolis.example.com
env[AUTOMATION_ALLOW_PRIVATE_WEBHOOK_ENDPOINTS] = 0
```

Depois:

```bash
sudo systemctl restart php8.2-fpm
sudo systemctl reload nginx
```

## 4) Recomendacao para producao

- Nao manter `.env` no servidor de producao.
- Manter segredos em variaveis do host/orquestrador.
- Configurar `ALLOWED_HOSTS` com os dominios oficiais do ambiente.
- Garantir `AUTOMATION_ALLOW_PRIVATE_WEBHOOK_ENDPOINTS=0` por padrao.

## 5) Rotacao sem downtime

1. Definir nova chave em `TOKEN_CIPHER_KEY`.
2. Mover chave antiga para `TOKEN_CIPHER_KEY_PREVIOUS`.
3. Recarregar servicos web/PHP.
4. Validar fluxos criticos.
5. Remover `TOKEN_CIPHER_KEY_PREVIOUS` apos janela de estabilizacao.

## 6) Validacao pos-deploy

```bash
php tests/security/run-security-suite.php
```

Validacao manual minima:

1. Login admin e cliente.
2. Logout admin e cliente.
3. Exclusao de registro no admin (confirma CSRF/POST).
4. Publicacao social (dry-run ou real).
5. Redirecionamento de short link (`tracking/redirect/{shortCode}`).
6. Teste de host nao permitido (esperado: `400`):
   - Exemplo: requisicao com header `Host` fora da allowlist.
