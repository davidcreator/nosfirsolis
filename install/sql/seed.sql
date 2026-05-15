INSERT INTO user_groups (name, description, hierarchy_level, permissions_json, status, created_at, updated_at)
VALUES
('Administradores', 'Acesso total ao painel administrativo', 10, '["*"]', 1, NOW(), NOW()),
('Clientes', 'Acesso ao planejamento e calendario', 90, '["client.*"]', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    description = VALUES(description),
    hierarchy_level = VALUES(hierarchy_level),
    permissions_json = VALUES(permissions_json),
    status = VALUES(status),
    updated_at = VALUES(updated_at);

INSERT INTO subscription_plans (
    slug,
    name,
    description,
    currency,
    price_monthly_cents,
    price_yearly_cents,
    is_free,
    ad_supported,
    is_public,
    status,
    sort_order,
    created_at,
    updated_at
)
VALUES
('gratuito', 'Basico Gratuito', 'Plano de entrada com propaganda, recursos essenciais e limites de uso.', 'BRL', 0, 0, 1, 1, 1, 1, 10, NOW(), NOW()),
('bronze', 'Plano Bronze', 'Menos restricoes para operacao diaria, com mais postagens e integracoes.', 'BRL', 7900, 75840, 0, 0, 1, 1, 20, NOW(), NOW()),
('prata', 'Plano Prata', 'Plano intermediario com mais recursos avancados, volume de posts e escalabilidade.', 'BRL', 15900, 152640, 0, 0, 1, 1, 30, NOW(), NOW()),
('ouro', 'Plano Ouro', 'Todos os recursos liberados, sem limite de postagens e sem propaganda.', 'BRL', 32900, 315840, 0, 0, 1, 1, 40, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    currency = VALUES(currency),
    price_monthly_cents = VALUES(price_monthly_cents),
    price_yearly_cents = VALUES(price_yearly_cents),
    is_free = VALUES(is_free),
    ad_supported = VALUES(ad_supported),
    is_public = VALUES(is_public),
    status = VALUES(status),
    sort_order = VALUES(sort_order),
    updated_at = VALUES(updated_at);

SET @plan_gratuito_id = (SELECT id FROM subscription_plans WHERE slug = 'gratuito' LIMIT 1);
SET @plan_bronze_id = (SELECT id FROM subscription_plans WHERE slug = 'bronze' LIMIT 1);
SET @plan_prata_id = (SELECT id FROM subscription_plans WHERE slug = 'prata' LIMIT 1);
SET @plan_ouro_id = (SELECT id FROM subscription_plans WHERE slug = 'ouro' LIMIT 1);

INSERT INTO plan_limits (plan_id, limit_key, value_type, int_value, bool_value, text_value, created_at, updated_at)
VALUES
(@plan_gratuito_id, 'max_editorial_plans_per_month', 'int', 2, NULL, NULL, NOW(), NOW()),
(@plan_gratuito_id, 'max_social_publications_per_month', 'int', 20, NULL, NULL, NOW(), NOW()),
(@plan_gratuito_id, 'max_social_accounts', 'int', 1, NULL, NULL, NOW(), NOW()),
(@plan_gratuito_id, 'max_tracking_links_per_month', 'int', 15, NULL, NULL, NOW(), NOW()),
(@plan_gratuito_id, 'max_calendar_extra_events_per_month', 'int', 12, NULL, NULL, NOW(), NOW()),
(@plan_gratuito_id, 'ads_enabled', 'bool', NULL, 1, NULL, NOW(), NOW()),
(@plan_gratuito_id, 'allow_template_plans', 'bool', NULL, 0, NULL, NOW(), NOW()),
(@plan_gratuito_id, 'allow_ai_draft_generator', 'bool', NULL, 0, NULL, NOW(), NOW()),
(@plan_gratuito_id, 'allow_format_presets', 'bool', NULL, 0, NULL, NOW(), NOW()),
(@plan_gratuito_id, 'allow_publish_hub', 'bool', NULL, 1, NULL, NOW(), NOW()),
(@plan_gratuito_id, 'allow_queue_processing', 'bool', NULL, 0, NULL, NOW(), NOW()),
(@plan_gratuito_id, 'allow_tracking_links', 'bool', NULL, 1, NULL, NOW(), NOW()),
(@plan_gratuito_id, 'allow_social_connections', 'bool', NULL, 1, NULL, NOW(), NOW()),
(@plan_bronze_id, 'max_editorial_plans_per_month', 'int', 8, NULL, NULL, NOW(), NOW()),
(@plan_bronze_id, 'max_social_publications_per_month', 'int', 120, NULL, NULL, NOW(), NOW()),
(@plan_bronze_id, 'max_social_accounts', 'int', 4, NULL, NULL, NOW(), NOW()),
(@plan_bronze_id, 'max_tracking_links_per_month', 'int', 120, NULL, NULL, NOW(), NOW()),
(@plan_bronze_id, 'max_calendar_extra_events_per_month', 'int', 60, NULL, NULL, NOW(), NOW()),
(@plan_bronze_id, 'ads_enabled', 'bool', NULL, 0, NULL, NOW(), NOW()),
(@plan_bronze_id, 'allow_template_plans', 'bool', NULL, 1, NULL, NOW(), NOW()),
(@plan_bronze_id, 'allow_ai_draft_generator', 'bool', NULL, 1, NULL, NOW(), NOW()),
(@plan_bronze_id, 'allow_format_presets', 'bool', NULL, 1, NULL, NOW(), NOW()),
(@plan_bronze_id, 'allow_publish_hub', 'bool', NULL, 1, NULL, NOW(), NOW()),
(@plan_bronze_id, 'allow_queue_processing', 'bool', NULL, 1, NULL, NOW(), NOW()),
(@plan_bronze_id, 'allow_tracking_links', 'bool', NULL, 1, NULL, NOW(), NOW()),
(@plan_bronze_id, 'allow_social_connections', 'bool', NULL, 1, NULL, NOW(), NOW()),
(@plan_prata_id, 'max_editorial_plans_per_month', 'int', 20, NULL, NULL, NOW(), NOW()),
(@plan_prata_id, 'max_social_publications_per_month', 'int', 400, NULL, NULL, NOW(), NOW()),
(@plan_prata_id, 'max_social_accounts', 'int', 10, NULL, NULL, NOW(), NOW()),
(@plan_prata_id, 'max_tracking_links_per_month', 'int', 400, NULL, NULL, NOW(), NOW()),
(@plan_prata_id, 'max_calendar_extra_events_per_month', 'int', 200, NULL, NULL, NOW(), NOW()),
(@plan_prata_id, 'ads_enabled', 'bool', NULL, 0, NULL, NOW(), NOW()),
(@plan_prata_id, 'allow_template_plans', 'bool', NULL, 1, NULL, NOW(), NOW()),
(@plan_prata_id, 'allow_ai_draft_generator', 'bool', NULL, 1, NULL, NOW(), NOW()),
(@plan_prata_id, 'allow_format_presets', 'bool', NULL, 1, NULL, NOW(), NOW()),
(@plan_prata_id, 'allow_publish_hub', 'bool', NULL, 1, NULL, NOW(), NOW()),
(@plan_prata_id, 'allow_queue_processing', 'bool', NULL, 1, NULL, NOW(), NOW()),
(@plan_prata_id, 'allow_tracking_links', 'bool', NULL, 1, NULL, NOW(), NOW()),
(@plan_prata_id, 'allow_social_connections', 'bool', NULL, 1, NULL, NOW(), NOW()),
(@plan_ouro_id, 'max_editorial_plans_per_month', 'int', -1, NULL, NULL, NOW(), NOW()),
(@plan_ouro_id, 'max_social_publications_per_month', 'int', -1, NULL, NULL, NOW(), NOW()),
(@plan_ouro_id, 'max_social_accounts', 'int', -1, NULL, NULL, NOW(), NOW()),
(@plan_ouro_id, 'max_tracking_links_per_month', 'int', -1, NULL, NULL, NOW(), NOW()),
(@plan_ouro_id, 'max_calendar_extra_events_per_month', 'int', -1, NULL, NULL, NOW(), NOW()),
(@plan_ouro_id, 'ads_enabled', 'bool', NULL, 0, NULL, NOW(), NOW()),
(@plan_ouro_id, 'allow_template_plans', 'bool', NULL, 1, NULL, NOW(), NOW()),
(@plan_ouro_id, 'allow_ai_draft_generator', 'bool', NULL, 1, NULL, NOW(), NOW()),
(@plan_ouro_id, 'allow_format_presets', 'bool', NULL, 1, NULL, NOW(), NOW()),
(@plan_ouro_id, 'allow_publish_hub', 'bool', NULL, 1, NULL, NOW(), NOW()),
(@plan_ouro_id, 'allow_queue_processing', 'bool', NULL, 1, NULL, NOW(), NOW()),
(@plan_ouro_id, 'allow_tracking_links', 'bool', NULL, 1, NULL, NOW(), NOW()),
(@plan_ouro_id, 'allow_social_connections', 'bool', NULL, 1, NULL, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    value_type = VALUES(value_type),
    int_value = VALUES(int_value),
    bool_value = VALUES(bool_value),
    text_value = VALUES(text_value),
    updated_at = VALUES(updated_at);

INSERT INTO settings (key_name, value_text, autoload, status, created_at, updated_at)
VALUES
('billing.currency', 'BRL', 1, 1, NOW(), NOW()),
('billing.validation_mode', 'manual', 1, 1, NOW(), NOW()),
('billing.mock_auto_approve', '0', 1, 1, NOW(), NOW()),
('billing.method.pix', '1', 1, 1, NOW(), NOW()),
('billing.method.boleto', '1', 1, 1, NOW(), NOW()),
('billing.method.card', '1', 1, 1, NOW(), NOW()),
('billing.method.transfer', '0', 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    value_text = VALUES(value_text),
    autoload = VALUES(autoload),
    status = VALUES(status),
    updated_at = VALUES(updated_at);

INSERT INTO languages (code, name, is_default, status, created_at, updated_at)
VALUES
('en-us', 'English US', 1, 1, NOW(), NOW()),
('pt-br', 'Portugues Brasil', 0, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    is_default = VALUES(is_default),
    status = VALUES(status),
    updated_at = VALUES(updated_at);

INSERT INTO settings (key_name, value_text, autoload, status, created_at, updated_at)
VALUES
('site_name', 'Solis', 1, 1, NOW(), NOW()),
('default_timezone', 'America/Sao_Paulo', 1, 1, NOW(), NOW()),
('default_language', 'en-us', 1, 1, NOW(), NOW()),
('feature_export_pdf', '0', 1, 1, NOW(), NOW()),
('feature_export_csv', '0', 1, 1, NOW(), NOW()),
('feature_print', '1', 1, 1, NOW(), NOW()),
('feature_api', '1', 1, 1, NOW(), NOW()),
('feature_webhook', '1', 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    value_text = VALUES(value_text),
    autoload = VALUES(autoload),
    status = VALUES(status),
    updated_at = VALUES(updated_at);

INSERT INTO content_categories (name, slug, description, status, created_at, updated_at)
VALUES
('Imagem', 'imagem', 'Conteudos em formato de imagem estatica', 1, NOW(), NOW()),
('Carrossel', 'carrossel', 'Sequencia de cards para storytelling', 1, NOW(), NOW()),
('Story', 'story', 'Conteudo rapido e efemero', 1, NOW(), NOW()),
('Reel', 'reel', 'Video curto vertical', 1, NOW(), NOW()),
('Short', 'short', 'Video curto para YouTube', 1, NOW(), NOW()),
('Video Longo', 'video-longo', 'Conteudo aprofundado em video', 1, NOW(), NOW()),
('Live', 'live', 'Transmissao ao vivo', 1, NOW(), NOW()),
('Artigo', 'artigo', 'Conteudo textual para blog e SEO', 1, NOW(), NOW()),
('Promocional', 'postagem-promocional', 'Foco em oferta e conversao', 1, NOW(), NOW()),
('Institucional', 'postagem-institucional', 'Posicionamento e marca', 1, NOW(), NOW()),
('Educativa', 'postagem-educativa', 'Conteudo de valor e aprendizado', 1, NOW(), NOW()),
('Enquete', 'enquete', 'Pesquisa com audiencia', 1, NOW(), NOW()),
('Pergunta', 'pergunta', 'Chamada interativa', 1, NOW(), NOW()),
('Citacao', 'citacao', 'Frases e inspiracao', 1, NOW(), NOW()),
('Sazonal', 'conteudo-sazonal', 'Conteudo contextual de data', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    status = VALUES(status),
    updated_at = VALUES(updated_at);

INSERT INTO content_pillars (name, slug, description, status, created_at, updated_at)
VALUES
('Promova-se', 'promova-se', 'Mostre resultados e diferenciais', 1, NOW(), NOW()),
('Faca uma pergunta', 'faca-uma-pergunta', 'Ative interacao com perguntas', 1, NOW(), NOW()),
('Citacoes inspiradoras', 'citacoes-inspiradoras', 'Mensagens de impacto e reflexao', 1, NOW(), NOW()),
('Seja o especialista', 'seja-o-especialista', 'Educacao e autoridade', 1, NOW(), NOW()),
('Inspiracao', 'inspiracao', 'Conteudo aspiracional', 1, NOW(), NOW()),
('Seja pessoal', 'seja-pessoal', 'Bastidores e humanizacao', 1, NOW(), NOW()),
('Coisas favoritas', 'coisas-favoritas', 'Preferencias e recomendacoes', 1, NOW(), NOW()),
('Hashtag do dia', 'hashtag-do-dia', 'Aproveitamento de tendencia', 1, NOW(), NOW()),
('Compartilhe o relacionamento', 'compartilhe-o-relacionamento', 'Provas sociais e historias reais', 1, NOW(), NOW()),
('Compartilhe o amor', 'compartilhe-o-amor', 'Agradecimento e comunidade', 1, NOW(), NOW()),
('Pesquisa', 'pesquisa', 'Coleta ativa de opinioes', 1, NOW(), NOW()),
('O que esta em alta', 'o-que-esta-em-alta', 'Conteudo alinhado ao momento', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    status = VALUES(status),
    updated_at = VALUES(updated_at);

INSERT INTO content_objectives (name, slug, description, status, created_at, updated_at)
VALUES
('Reconhecimento de marca', 'reconhecimento', 'Ampliar visibilidade da marca', 1, NOW(), NOW()),
('Engajamento', 'engajamento', 'Estimular interacao e alcance organico', 1, NOW(), NOW()),
('Captacao de leads', 'captacao-de-leads', 'Gerar novos contatos qualificados', 1, NOW(), NOW()),
('Conversao', 'conversao', 'Transformar interesse em venda', 1, NOW(), NOW()),
('Relacionamento', 'relacionamento', 'Fortalecer vinculo com audiencia', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    status = VALUES(status),
    updated_at = VALUES(updated_at);

INSERT INTO social_channels (name, slug, description, status, created_at, updated_at)
VALUES
('Instagram', 'instagram', 'Rede social visual', 1, NOW(), NOW()),
('Facebook', 'facebook', 'Rede social generalista', 1, NOW(), NOW()),
('LinkedIn', 'linkedin', 'Rede profissional', 1, NOW(), NOW()),
('TikTok', 'tiktok', 'Rede de videos curtos', 1, NOW(), NOW()),
('X/Twitter', 'x-twitter', 'Microblog e tempo real', 1, NOW(), NOW()),
('Pinterest', 'pinterest', 'Rede de descoberta visual', 1, NOW(), NOW()),
('Threads', 'threads', 'Rede social conversacional', 1, NOW(), NOW()),
('Blog', 'blog', 'Canal proprio de conteudo', 1, NOW(), NOW()),
('Podcast', 'podcast', 'Audio sob demanda', 1, NOW(), NOW()),
('E-mail marketing', 'email-marketing', 'Canal de relacionamento direto', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    status = VALUES(status),
    updated_at = VALUES(updated_at);

INSERT INTO video_channels (name, slug, description, status, created_at, updated_at)
VALUES
('YouTube', 'youtube', 'Plataforma de video', 1, NOW(), NOW()),
('Vimeo', 'vimeo', 'Plataforma de video profissional', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    status = VALUES(status),
    updated_at = VALUES(updated_at);

INSERT INTO content_platforms (name, slug, platform_type, source, status, created_at, updated_at)
VALUES
('Instagram', 'instagram', 'social', 'social_channels', 1, NOW(), NOW()),
('Facebook', 'facebook', 'social', 'social_channels', 1, NOW(), NOW()),
('LinkedIn', 'linkedin', 'social', 'social_channels', 1, NOW(), NOW()),
('TikTok', 'tiktok', 'social', 'social_channels', 1, NOW(), NOW()),
('X/Twitter', 'x-twitter', 'social', 'social_channels', 1, NOW(), NOW()),
('Pinterest', 'pinterest', 'social', 'social_channels', 1, NOW(), NOW()),
('Threads', 'threads', 'social', 'social_channels', 1, NOW(), NOW()),
('YouTube', 'youtube', 'video', 'video_channels', 1, NOW(), NOW()),
('Vimeo', 'vimeo', 'video', 'video_channels', 1, NOW(), NOW()),
('Blog', 'blog', 'blog', 'social_channels', 1, NOW(), NOW()),
('Podcast', 'podcast', 'podcast', 'social_channels', 1, NOW(), NOW()),
('E-mail marketing', 'email-marketing', 'email', 'social_channels', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    platform_type = VALUES(platform_type),
    source = VALUES(source),
    status = VALUES(status),
    updated_at = VALUES(updated_at);

INSERT INTO holiday_regions (name, country_code, state_code, region_type, status, created_at, updated_at)
SELECT 'Brasil', 'BR', NULL, 'country', 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM holiday_regions
    WHERE name = 'Brasil' AND country_code = 'BR' AND state_code IS NULL AND region_type = 'country'
);

INSERT INTO holiday_regions (name, country_code, state_code, region_type, status, created_at, updated_at)
SELECT 'Internacional', 'XX', NULL, 'international', 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM holiday_regions
    WHERE name = 'Internacional' AND country_code = 'XX' AND state_code IS NULL AND region_type = 'international'
);

INSERT INTO holidays (name, holiday_date, month_day, is_fixed, is_movable, movable_rule, holiday_type, holiday_region_id, country_code, status, created_at, updated_at)
SELECT
    'Confraternizacao Universal',
    '2026-01-01',
    '01-01',
    1,
    0,
    NULL,
    'national',
    (SELECT id FROM holiday_regions WHERE name = 'Brasil' AND country_code = 'BR' LIMIT 1),
    'BR',
    1,
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM holidays WHERE name = 'Confraternizacao Universal' AND holiday_date = '2026-01-01' AND country_code = 'BR'
);

INSERT INTO holidays (name, holiday_date, month_day, is_fixed, is_movable, movable_rule, holiday_type, holiday_region_id, country_code, status, created_at, updated_at)
SELECT
    'Tiradentes',
    '2026-04-21',
    '04-21',
    1,
    0,
    NULL,
    'national',
    (SELECT id FROM holiday_regions WHERE name = 'Brasil' AND country_code = 'BR' LIMIT 1),
    'BR',
    1,
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM holidays WHERE name = 'Tiradentes' AND holiday_date = '2026-04-21' AND country_code = 'BR'
);

INSERT INTO holidays (name, holiday_date, month_day, is_fixed, is_movable, movable_rule, holiday_type, holiday_region_id, country_code, status, created_at, updated_at)
SELECT
    'Natal',
    '2026-12-25',
    '12-25',
    1,
    0,
    NULL,
    'national',
    (SELECT id FROM holiday_regions WHERE name = 'Brasil' AND country_code = 'BR' LIMIT 1),
    'BR',
    1,
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM holidays WHERE name = 'Natal' AND holiday_date = '2026-12-25' AND country_code = 'BR'
);

INSERT INTO holidays (name, holiday_date, month_day, is_fixed, is_movable, movable_rule, holiday_type, holiday_region_id, country_code, status, created_at, updated_at)
SELECT
    'Dia Internacional da Mulher',
    '2026-03-08',
    '03-08',
    1,
    0,
    NULL,
    'international',
    (SELECT id FROM holiday_regions WHERE name = 'Internacional' AND country_code = 'XX' LIMIT 1),
    'XX',
    1,
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM holidays WHERE name = 'Dia Internacional da Mulher' AND holiday_date = '2026-03-08' AND country_code = 'XX'
);

INSERT INTO commemorative_dates (name, event_date, month_day, recurrence_type, context_type, country_code, description, status, created_at, updated_at)
SELECT
    'Dia do Consumidor',
    '2026-03-15',
    '03-15',
    'yearly',
    'commercial',
    'BR',
    'Data para campanhas promocionais e relacionamento.',
    1,
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM commemorative_dates WHERE name = 'Dia do Consumidor' AND event_date = '2026-03-15' AND country_code = 'BR'
);

INSERT INTO commemorative_dates (name, event_date, month_day, recurrence_type, context_type, country_code, description, status, created_at, updated_at)
SELECT
    'Dia Mundial do Meio Ambiente',
    '2026-06-05',
    '06-05',
    'yearly',
    'institutional',
    'XX',
    'Data para posicionamento institucional e ESG.',
    1,
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM commemorative_dates WHERE name = 'Dia Mundial do Meio Ambiente' AND event_date = '2026-06-05' AND country_code = 'XX'
);

INSERT INTO commemorative_dates (name, event_date, month_day, recurrence_type, context_type, country_code, description, status, created_at, updated_at)
SELECT
    'Black Friday',
    '2026-11-27',
    '11-27',
    'yearly',
    'commercial',
    'BR',
    'Campanha de alta conversao no varejo.',
    1,
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM commemorative_dates WHERE name = 'Black Friday' AND event_date = '2026-11-27' AND country_code = 'BR'
);

INSERT INTO tags (name, slug, status, created_at, updated_at)
VALUES
('engajamento', 'engajamento', 1, NOW(), NOW()),
('vendas', 'vendas', 1, NOW(), NOW()),
('autoridade', 'autoridade', 1, NOW(), NOW()),
('sazonal', 'sazonal', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    status = VALUES(status),
    updated_at = VALUES(updated_at);

INSERT INTO campaigns (name, description, objective, start_date, end_date, status, created_at, updated_at)
SELECT
    'Aquecimento de Marca Q1',
    'Campanha para reconhecimento e crescimento de audiencia.',
    'Reconhecimento de marca',
    '2026-01-01',
    '2026-03-31',
    'active',
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM campaigns
    WHERE name = 'Aquecimento de Marca Q1'
      AND start_date = '2026-01-01'
      AND end_date = '2026-03-31'
);

INSERT INTO campaigns (name, description, objective, start_date, end_date, status, created_at, updated_at)
SELECT
    'Campanha Sazonal Meio do Ano',
    'Acoes estrategicas para datas comemorativas de inverno.',
    'Engajamento',
    '2026-06-01',
    '2026-07-31',
    'planned',
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM campaigns
    WHERE name = 'Campanha Sazonal Meio do Ano'
      AND start_date = '2026-06-01'
      AND end_date = '2026-07-31'
);

INSERT INTO content_suggestions (
    title,
    description,
    suggestion_date,
    month_day,
    is_recurring,
    recurrence_type,
    content_category_id,
    content_pillar_id,
    content_objective_id,
    campaign_id,
    format_type,
    context_type,
    channel_priority,
    status,
    created_at,
    updated_at
)
SELECT
    'Bastidores da sua operacao',
    'Mostre um processo interno em formato story para aproximar a audiencia.',
    '2026-01-10',
    '01-10',
    1,
    'yearly',
    (SELECT id FROM content_categories WHERE slug = 'story' LIMIT 1),
    (SELECT id FROM content_pillars WHERE slug = 'seja-pessoal' LIMIT 1),
    (SELECT id FROM content_objectives WHERE slug = 'engajamento' LIMIT 1),
    (
        SELECT id
        FROM campaigns
        WHERE name = 'Aquecimento de Marca Q1'
          AND start_date = '2026-01-01'
          AND end_date = '2026-03-31'
        ORDER BY id ASC
        LIMIT 1
    ),
    'story',
    'editorial',
    'instagram,threads',
    1,
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM content_suggestions WHERE title = 'Bastidores da sua operacao' AND suggestion_date = '2026-01-10'
);

INSERT INTO content_suggestions (
    title,
    description,
    suggestion_date,
    month_day,
    is_recurring,
    recurrence_type,
    content_category_id,
    content_pillar_id,
    content_objective_id,
    campaign_id,
    format_type,
    context_type,
    channel_priority,
    status,
    created_at,
    updated_at
)
SELECT
    'Pergunta da semana para gerar debate',
    'Publique uma pergunta aberta sobre dores do publico.',
    '2026-02-14',
    '02-14',
    1,
    'yearly',
    (SELECT id FROM content_categories WHERE slug = 'pergunta' LIMIT 1),
    (SELECT id FROM content_pillars WHERE slug = 'faca-uma-pergunta' LIMIT 1),
    (SELECT id FROM content_objectives WHERE slug = 'engajamento' LIMIT 1),
    (
        SELECT id
        FROM campaigns
        WHERE name = 'Aquecimento de Marca Q1'
          AND start_date = '2026-01-01'
          AND end_date = '2026-03-31'
        ORDER BY id ASC
        LIMIT 1
    ),
    'pergunta',
    'editorial',
    'linkedin,instagram',
    1,
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM content_suggestions WHERE title = 'Pergunta da semana para gerar debate' AND suggestion_date = '2026-02-14'
);

INSERT INTO content_suggestions (
    title,
    description,
    suggestion_date,
    month_day,
    is_recurring,
    recurrence_type,
    content_category_id,
    content_pillar_id,
    content_objective_id,
    campaign_id,
    format_type,
    context_type,
    channel_priority,
    status,
    created_at,
    updated_at
)
SELECT
    'Conteudo educativo em carrossel',
    'Explique um conceito-chave do seu nicho em passos simples.',
    '2026-03-18',
    '03-18',
    1,
    'yearly',
    (SELECT id FROM content_categories WHERE slug = 'carrossel' LIMIT 1),
    (SELECT id FROM content_pillars WHERE slug = 'seja-o-especialista' LIMIT 1),
    (SELECT id FROM content_objectives WHERE slug = 'reconhecimento' LIMIT 1),
    (
        SELECT id
        FROM campaigns
        WHERE name = 'Aquecimento de Marca Q1'
          AND start_date = '2026-01-01'
          AND end_date = '2026-03-31'
        ORDER BY id ASC
        LIMIT 1
    ),
    'carrossel',
    'institutional',
    'linkedin,instagram',
    1,
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM content_suggestions WHERE title = 'Conteudo educativo em carrossel' AND suggestion_date = '2026-03-18'
);

INSERT INTO content_suggestions (
    title,
    description,
    suggestion_date,
    month_day,
    is_recurring,
    recurrence_type,
    content_category_id,
    content_pillar_id,
    content_objective_id,
    campaign_id,
    format_type,
    context_type,
    channel_priority,
    status,
    created_at,
    updated_at
)
SELECT
    'Video curto de tendencia',
    'Aplique um tema em alta ao seu mercado com CTA de engajamento.',
    '2026-04-07',
    '04-07',
    1,
    'yearly',
    (SELECT id FROM content_categories WHERE slug = 'reel' LIMIT 1),
    (SELECT id FROM content_pillars WHERE slug = 'o-que-esta-em-alta' LIMIT 1),
    (SELECT id FROM content_objectives WHERE slug = 'engajamento' LIMIT 1),
    NULL,
    'reel',
    'seasonal',
    'tiktok,instagram',
    1,
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM content_suggestions WHERE title = 'Video curto de tendencia' AND suggestion_date = '2026-04-07'
);
