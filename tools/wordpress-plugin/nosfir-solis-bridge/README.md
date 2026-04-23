# Nosfir Solis Bridge (WordPress Plugin)

Plugin WordPress para receber publicacoes do Solis via REST API e criar posts no site.

## Versao

- `0.1.0` (MVP inicial)

## O que este MVP entrega

- endpoint de health:
  - `GET /wp-json/nosfir/v1/health`
- endpoint de publicacao:
  - `POST /wp-json/nosfir/v1/publish`
- autenticacao configuravel:
  - `HMAC` via `X-Nosfir-Signature`
  - `Bearer token`
  - `none` (somente dev)
- idempotencia opcional por `delivery_id`
- configuracao no admin:
  - `Settings -> Solis Bridge`

## Estrutura

- `nosfir-solis-bridge.php`: bootstrap do plugin
- `src/class-nosfir-solis-bridge-plugin.php`: logica principal

## Instalacao

1. Copie a pasta `nosfir-solis-bridge` para:
   - `wp-content/plugins/nosfir-solis-bridge`
2. Ative no painel WordPress:
   - `Plugins -> Nosfir Solis Bridge -> Activate`
3. Configure:
   - `Settings -> Solis Bridge`

## Configuracao recomendada

1. `Auth mode`: `HMAC`
2. `Shared secret`: mesmo segredo usado pelo Solis
3. `Default post status`: `draft` em homologacao, `publish` em producao (se desejado)
4. `Enforce idempotency`: habilitado

## Contrato de payload (direto)

Exemplo:

```json
{
  "delivery_id": "abc-123",
  "title": "Post vindo do Solis",
  "content": "<p>Conteudo...</p>",
  "status": "draft",
  "date": "2026-05-20T14:30:00",
  "slug": "post-vindo-do-solis",
  "categories": [1, 2],
  "tags": ["marketing", "solis"],
  "meta": {
    "nosfir_campaign": "campanha-q2"
  }
}
```

## Envelope opcional (compativel)

Tambem aceita um envelope no formato:

```json
{
  "event": "social.publication_queued",
  "delivery_id": "abc-123",
  "payload": {
    "title": "Post",
    "content": "..."
  }
}
```

## Exemplo de assinatura HMAC

Header esperado:

```text
X-Nosfir-Signature: sha256=<hash_hmac_do_body_raw>
```

## Exemplo de teste rapido com cURL

```bash
curl -X POST "https://seusite.com/wp-json/nosfir/v1/publish" \
  -H "Content-Type: application/json" \
  -H "X-Nosfir-Signature: sha256=SEU_HASH" \
  -d '{"delivery_id":"abc-123","title":"Teste","content":"<p>OK</p>","status":"draft"}'
```

## Proximos passos sugeridos

1. upload de midia por URL no proprio plugin
2. mapeamento avancado de taxonomias/CPT
3. endpoint de callback para reconciliacao com Solis
4. log administrativo de requests (com redacao de segredos)
