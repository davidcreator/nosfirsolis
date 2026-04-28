INSERT INTO user_groups (name, description, hierarchy_level, permissions_json, status, created_at, updated_at)
VALUES
('Administradores', 'Acesso total ao painel administrativo', 10, '["*"]', 1, NOW(), NOW()),
('Clientes', 'Acesso ao planejamento e calendário', 90, '["client.*"]', 1, NOW(), NOW());

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
('ouro', 'Plano Ouro', 'Todos os recursos liberados, sem limite de postagens e sem propaganda.', 'BRL', 32900, 315840, 0, 0, 1, 1, 40, NOW(), NOW());

INSERT INTO plan_limits (plan_id, limit_key, value_type, int_value, bool_value, text_value, created_at, updated_at)
VALUES
((SELECT id FROM subscription_plans WHERE slug = 'gratuito' LIMIT 1), 'max_editorial_plans_per_month', 'int', 2, NULL, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'gratuito' LIMIT 1), 'max_social_publications_per_month', 'int', 20, NULL, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'gratuito' LIMIT 1), 'max_social_accounts', 'int', 1, NULL, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'gratuito' LIMIT 1), 'max_tracking_links_per_month', 'int', 15, NULL, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'gratuito' LIMIT 1), 'max_calendar_extra_events_per_month', 'int', 12, NULL, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'gratuito' LIMIT 1), 'ads_enabled', 'bool', NULL, 1, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'gratuito' LIMIT 1), 'allow_template_plans', 'bool', NULL, 0, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'gratuito' LIMIT 1), 'allow_ai_draft_generator', 'bool', NULL, 0, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'gratuito' LIMIT 1), 'allow_format_presets', 'bool', NULL, 0, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'gratuito' LIMIT 1), 'allow_publish_hub', 'bool', NULL, 1, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'gratuito' LIMIT 1), 'allow_queue_processing', 'bool', NULL, 0, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'gratuito' LIMIT 1), 'allow_tracking_links', 'bool', NULL, 1, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'gratuito' LIMIT 1), 'allow_social_connections', 'bool', NULL, 1, NULL, NOW(), NOW()),

((SELECT id FROM subscription_plans WHERE slug = 'bronze' LIMIT 1), 'max_editorial_plans_per_month', 'int', 8, NULL, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'bronze' LIMIT 1), 'max_social_publications_per_month', 'int', 120, NULL, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'bronze' LIMIT 1), 'max_social_accounts', 'int', 4, NULL, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'bronze' LIMIT 1), 'max_tracking_links_per_month', 'int', 120, NULL, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'bronze' LIMIT 1), 'max_calendar_extra_events_per_month', 'int', 60, NULL, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'bronze' LIMIT 1), 'ads_enabled', 'bool', NULL, 0, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'bronze' LIMIT 1), 'allow_template_plans', 'bool', NULL, 1, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'bronze' LIMIT 1), 'allow_ai_draft_generator', 'bool', NULL, 1, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'bronze' LIMIT 1), 'allow_format_presets', 'bool', NULL, 1, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'bronze' LIMIT 1), 'allow_publish_hub', 'bool', NULL, 1, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'bronze' LIMIT 1), 'allow_queue_processing', 'bool', NULL, 1, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'bronze' LIMIT 1), 'allow_tracking_links', 'bool', NULL, 1, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'bronze' LIMIT 1), 'allow_social_connections', 'bool', NULL, 1, NULL, NOW(), NOW()),

((SELECT id FROM subscription_plans WHERE slug = 'prata' LIMIT 1), 'max_editorial_plans_per_month', 'int', 20, NULL, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'prata' LIMIT 1), 'max_social_publications_per_month', 'int', 400, NULL, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'prata' LIMIT 1), 'max_social_accounts', 'int', 10, NULL, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'prata' LIMIT 1), 'max_tracking_links_per_month', 'int', 400, NULL, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'prata' LIMIT 1), 'max_calendar_extra_events_per_month', 'int', 200, NULL, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'prata' LIMIT 1), 'ads_enabled', 'bool', NULL, 0, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'prata' LIMIT 1), 'allow_template_plans', 'bool', NULL, 1, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'prata' LIMIT 1), 'allow_ai_draft_generator', 'bool', NULL, 1, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'prata' LIMIT 1), 'allow_format_presets', 'bool', NULL, 1, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'prata' LIMIT 1), 'allow_publish_hub', 'bool', NULL, 1, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'prata' LIMIT 1), 'allow_queue_processing', 'bool', NULL, 1, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'prata' LIMIT 1), 'allow_tracking_links', 'bool', NULL, 1, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'prata' LIMIT 1), 'allow_social_connections', 'bool', NULL, 1, NULL, NOW(), NOW()),

((SELECT id FROM subscription_plans WHERE slug = 'ouro' LIMIT 1), 'max_editorial_plans_per_month', 'int', -1, NULL, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'ouro' LIMIT 1), 'max_social_publications_per_month', 'int', -1, NULL, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'ouro' LIMIT 1), 'max_social_accounts', 'int', -1, NULL, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'ouro' LIMIT 1), 'max_tracking_links_per_month', 'int', -1, NULL, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'ouro' LIMIT 1), 'max_calendar_extra_events_per_month', 'int', -1, NULL, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'ouro' LIMIT 1), 'ads_enabled', 'bool', NULL, 0, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'ouro' LIMIT 1), 'allow_template_plans', 'bool', NULL, 1, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'ouro' LIMIT 1), 'allow_ai_draft_generator', 'bool', NULL, 1, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'ouro' LIMIT 1), 'allow_format_presets', 'bool', NULL, 1, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'ouro' LIMIT 1), 'allow_publish_hub', 'bool', NULL, 1, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'ouro' LIMIT 1), 'allow_queue_processing', 'bool', NULL, 1, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'ouro' LIMIT 1), 'allow_tracking_links', 'bool', NULL, 1, NULL, NOW(), NOW()),
((SELECT id FROM subscription_plans WHERE slug = 'ouro' LIMIT 1), 'allow_social_connections', 'bool', NULL, 1, NULL, NOW(), NOW());

INSERT INTO settings (key_name, value_text, autoload, status, created_at, updated_at)
VALUES
('billing.currency', 'BRL', 1, 1, NOW(), NOW()),
('billing.validation_mode', 'automatic', 1, 1, NOW(), NOW()),
('billing.mock_auto_approve', '1', 1, 1, NOW(), NOW()),
('billing.method.pix', '1', 1, 1, NOW(), NOW()),
('billing.method.boleto', '1', 1, 1, NOW(), NOW()),
('billing.method.card', '1', 1, 1, NOW(), NOW()),
('billing.method.transfer', '0', 1, 1, NOW(), NOW());

INSERT INTO languages (code, name, is_default, status, created_at, updated_at)
VALUES
('en-us', 'English US', 1, 1, NOW(), NOW()),
('pt-br', 'Português Brasil', 0, 1, NOW(), NOW());

INSERT INTO settings (key_name, value_text, autoload, status, created_at, updated_at)
VALUES
('site_name', 'Solis', 1, 1, NOW(), NOW()),
('default_timezone', 'America/Sao_Paulo', 1, 1, NOW(), NOW()),
('default_language', 'en-us', 1, 1, NOW(), NOW()),
('feature_export_pdf', '0', 1, 1, NOW(), NOW()),
('feature_export_csv', '0', 1, 1, NOW(), NOW()),
('feature_print', '1', 1, 1, NOW(), NOW()),
('feature_api', '1', 1, 1, NOW(), NOW()),
('feature_webhook', '1', 1, 1, NOW(), NOW());

INSERT INTO content_categories (name, slug, description, status, created_at, updated_at)
VALUES
('Imagem', 'imagem', 'Conteúdos em formato de imagem estática', 1, NOW(), NOW()),
('Carrossel', 'carrossel', 'Sequência de cards para storytelling', 1, NOW(), NOW()),
('Story', 'story', 'Conteúdo rápido e efêmero', 1, NOW(), NOW()),
('Reel', 'reel', 'Vídeo curto vertical', 1, NOW(), NOW()),
('Short', 'short', 'Vídeo curto para YouTube', 1, NOW(), NOW()),
('Vídeo Longo', 'video-longo', 'Conteúdo aprofundado em vídeo', 1, NOW(), NOW()),
('Live', 'live', 'Transmissão ao vivo', 1, NOW(), NOW()),
('Artigo', 'artigo', 'Conteúdo textual para blog e SEO', 1, NOW(), NOW()),
('Promocional', 'postagem-promocional', 'Foco em oferta e conversão', 1, NOW(), NOW()),
('Institucional', 'postagem-institucional', 'Posicionamento e marca', 1, NOW(), NOW()),
('Educativa', 'postagem-educativa', 'Conteúdo de valor e aprendizado', 1, NOW(), NOW()),
('Enquete', 'enquete', 'Pesquisa com audiência', 1, NOW(), NOW()),
('Pergunta', 'pergunta', 'Chamada interativa', 1, NOW(), NOW()),
('Citação', 'citacao', 'Frases e inspiração', 1, NOW(), NOW()),
('Sazonal', 'conteudo-sazonal', 'Conteúdo contextual de data', 1, NOW(), NOW());

INSERT INTO content_pillars (name, slug, description, status, created_at, updated_at)
VALUES
('Promova-se', 'promova-se', 'Mostre resultados e diferenciais', 1, NOW(), NOW()),
('Faça uma pergunta', 'faca-uma-pergunta', 'Ative interação com perguntas', 1, NOW(), NOW()),
('Citações inspiradoras', 'citacoes-inspiradoras', 'Mensagens de impacto e reflexão', 1, NOW(), NOW()),
('Seja o especialista', 'seja-o-especialista', 'Educação e autoridade', 1, NOW(), NOW()),
('Inspiração', 'inspiracao', 'Conteúdo aspiracional', 1, NOW(), NOW()),
('Seja pessoal', 'seja-pessoal', 'Bastidores e humanização', 1, NOW(), NOW()),
('Coisas favoritas', 'coisas-favoritas', 'Preferências e recomendações', 1, NOW(), NOW()),
('Hashtag do dia', 'hashtag-do-dia', 'Aproveitamento de tendência', 1, NOW(), NOW()),
('Compartilhe o relacionamento', 'compartilhe-o-relacionamento', 'Provas sociais e histórias reais', 1, NOW(), NOW()),
('Compartilhe o amor', 'compartilhe-o-amor', 'Agradecimento e comunidade', 1, NOW(), NOW()),
('Pesquisa', 'pesquisa', 'Coleta ativa de opiniões', 1, NOW(), NOW()),
('O que está em alta', 'o-que-esta-em-alta', 'Conteúdo alinhado ao momento', 1, NOW(), NOW());

INSERT INTO content_objectives (name, slug, description, status, created_at, updated_at)
VALUES
('Reconhecimento de marca', 'reconhecimento', 'Ampliar visibilidade da marca', 1, NOW(), NOW()),
('Engajamento', 'engajamento', 'Estimular interação e alcance orgânico', 1, NOW(), NOW()),
('Captação de leads', 'captacao-de-leads', 'Gerar novos contatos qualificados', 1, NOW(), NOW()),
('Conversão', 'conversao', 'Transformar interesse em venda', 1, NOW(), NOW()),
('Relacionamento', 'relacionamento', 'Fortalecer vínculo com audiência', 1, NOW(), NOW());

INSERT INTO social_channels (name, slug, description, status, created_at, updated_at)
VALUES
('Instagram', 'instagram', 'Rede social visual', 1, NOW(), NOW()),
('Facebook', 'facebook', 'Rede social generalista', 1, NOW(), NOW()),
('LinkedIn', 'linkedin', 'Rede profissional', 1, NOW(), NOW()),
('TikTok', 'tiktok', 'Rede de vídeos curtos', 1, NOW(), NOW()),
('X/Twitter', 'x-twitter', 'Microblog e tempo real', 1, NOW(), NOW()),
('Pinterest', 'pinterest', 'Rede de descoberta visual', 1, NOW(), NOW()),
('Threads', 'threads', 'Rede social conversacional', 1, NOW(), NOW()),
('Blog', 'blog', 'Canal próprio de conteúdo', 1, NOW(), NOW()),
('Podcast', 'podcast', 'Áudio sob demanda', 1, NOW(), NOW()),
('E-mail marketing', 'email-marketing', 'Canal de relacionamento direto', 1, NOW(), NOW());

INSERT INTO video_channels (name, slug, description, status, created_at, updated_at)
VALUES
('YouTube', 'youtube', 'Plataforma de vídeo', 1, NOW(), NOW()),
('Vimeo', 'vimeo', 'Plataforma de vídeo profissional', 1, NOW(), NOW());

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
('E-mail marketing', 'email-marketing', 'email', 'social_channels', 1, NOW(), NOW());

INSERT INTO holiday_regions (name, country_code, state_code, region_type, status, created_at, updated_at)
VALUES
('Brasil', 'BR', NULL, 'country', 1, NOW(), NOW()),
('Internacional', 'XX', NULL, 'international', 1, NOW(), NOW());

INSERT INTO holidays (name, holiday_date, month_day, is_fixed, is_movable, movable_rule, holiday_type, holiday_region_id, country_code, status, created_at, updated_at)
VALUES
('Confraternização Universal', '2026-01-01', '01-01', 1, 0, NULL, 'national', 1, 'BR', 1, NOW(), NOW()),
('Tiradentes', '2026-04-21', '04-21', 1, 0, NULL, 'national', 1, 'BR', 1, NOW(), NOW()),
('Natal', '2026-12-25', '12-25', 1, 0, NULL, 'national', 1, 'BR', 1, NOW(), NOW()),
('Dia Internacional da Mulher', '2026-03-08', '03-08', 1, 0, NULL, 'international', 2, 'XX', 1, NOW(), NOW());

INSERT INTO commemorative_dates (name, event_date, month_day, recurrence_type, context_type, country_code, description, status, created_at, updated_at)
VALUES
('Dia do Consumidor', '2026-03-15', '03-15', 'yearly', 'commercial', 'BR', 'Data para campanhas promocionais e relacionamento.', 1, NOW(), NOW()),
('Dia Mundial do Meio Ambiente', '2026-06-05', '06-05', 'yearly', 'institutional', 'XX', 'Data para posicionamento institucional e ESG.', 1, NOW(), NOW()),
('Black Friday', '2026-11-27', '11-27', 'yearly', 'commercial', 'BR', 'Campanha de alta conversão no varejo.', 1, NOW(), NOW());

INSERT INTO tags (name, slug, status, created_at, updated_at)
VALUES
('engajamento', 'engajamento', 1, NOW(), NOW()),
('vendas', 'vendas', 1, NOW(), NOW()),
('autoridade', 'autoridade', 1, NOW(), NOW()),
('sazonal', 'sazonal', 1, NOW(), NOW());

INSERT INTO campaigns (name, description, objective, start_date, end_date, status, created_at, updated_at)
VALUES
('Aquecimento de Marca Q1', 'Campanha para reconhecimento e crescimento de audiência.', 'Reconhecimento de marca', '2026-01-01', '2026-03-31', 'active', NOW(), NOW()),
('Campanha Sazonal Meio do Ano', 'Ações estratégicas para datas comemorativas de inverno.', 'Engajamento', '2026-06-01', '2026-07-31', 'planned', NOW(), NOW());

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
VALUES
('Bastidores da sua operação', 'Mostre um processo interno em formato story para aproximar a audiência.', '2026-01-10', '01-10', 1, 'yearly', 3, 6, 2, 1, 'story', 'editorial', 'instagram,threads', 1, NOW(), NOW()),
('Pergunta da semana para gerar debate', 'Publique uma pergunta aberta sobre dores do público.', '2026-02-14', '02-14', 1, 'yearly', 13, 2, 2, 1, 'pergunta', 'editorial', 'linkedin,instagram', 1, NOW(), NOW()),
('Conteúdo educativo em carrossel', 'Explique um conceito-chave do seu nicho em passos simples.', '2026-03-18', '03-18', 1, 'yearly', 2, 4, 1, 1, 'carrossel', 'institutional', 'linkedin,instagram', 1, NOW(), NOW()),
('Vídeo curto de tendência', 'Aplique um tema em alta ao seu mercado com CTA de engajamento.', '2026-04-07', '04-07', 1, 'yearly', 4, 12, 2, NULL, 'reel', 'seasonal', 'tiktok,instagram', 1, NOW(), NOW());
