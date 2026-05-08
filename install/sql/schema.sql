SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS user_groups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    hierarchy_level INT UNSIGNED NOT NULL DEFAULT 50,
    permissions_json LONGTEXT NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_group_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    avatar VARCHAR(255) NULL,
    language_code VARCHAR(10) NOT NULL DEFAULT 'en-us',
    status TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_users_group FOREIGN KEY (user_group_id) REFERENCES user_groups(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    email VARCHAR(190) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_password_resets_user (user_id),
    INDEX idx_password_resets_email (email),
    INDEX idx_password_resets_token (token_hash),
    INDEX idx_password_resets_expires (expires_at),
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS subscription_plans (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(40) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    currency CHAR(3) NOT NULL DEFAULT 'BRL',
    price_monthly_cents INT UNSIGNED NOT NULL DEFAULT 0,
    price_yearly_cents INT UNSIGNED NOT NULL DEFAULT 0,
    is_free TINYINT(1) NOT NULL DEFAULT 0,
    ad_supported TINYINT(1) NOT NULL DEFAULT 0,
    is_public TINYINT(1) NOT NULL DEFAULT 1,
    status TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS plan_limits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plan_id INT UNSIGNED NOT NULL,
    limit_key VARCHAR(120) NOT NULL,
    value_type ENUM('int', 'bool', 'text') NOT NULL DEFAULT 'int',
    int_value INT NULL,
    bool_value TINYINT(1) NULL,
    text_value VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY ux_plan_limit_key (plan_id, limit_key),
    CONSTRAINT fk_plan_limits_plan FOREIGN KEY (plan_id) REFERENCES subscription_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_subscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    plan_id INT UNSIGNED NOT NULL,
    status ENUM('trial', 'active', 'past_due', 'suspended', 'canceled') NOT NULL DEFAULT 'active',
    billing_cycle ENUM('monthly', 'yearly') NOT NULL DEFAULT 'monthly',
    started_at DATETIME NOT NULL,
    current_period_start DATETIME NULL,
    current_period_end DATETIME NULL,
    next_billing_at DATETIME NULL,
    canceled_at DATETIME NULL,
    provider VARCHAR(40) NULL,
    provider_subscription_id VARCHAR(120) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY ux_user_subscriptions_user (user_id),
    INDEX idx_user_subscriptions_plan (plan_id, status),
    CONSTRAINT fk_user_subscriptions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_subscriptions_plan FOREIGN KEY (plan_id) REFERENCES subscription_plans(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS billing_invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    subscription_id INT UNSIGNED NULL,
    plan_id INT UNSIGNED NOT NULL,
    invoice_number VARCHAR(40) NULL UNIQUE,
    status ENUM('open', 'paid', 'void', 'failed') NOT NULL DEFAULT 'open',
    currency CHAR(3) NOT NULL DEFAULT 'BRL',
    subtotal_cents INT UNSIGNED NOT NULL DEFAULT 0,
    total_cents INT UNSIGNED NOT NULL DEFAULT 0,
    payment_method VARCHAR(40) NULL,
    provider VARCHAR(40) NOT NULL DEFAULT 'mock',
    provider_invoice_id VARCHAR(120) NULL,
    description VARCHAR(255) NULL,
    due_at DATETIME NULL,
    paid_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_billing_invoices_user (user_id, status, created_at),
    CONSTRAINT fk_billing_invoices_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_billing_invoices_subscription FOREIGN KEY (subscription_id) REFERENCES user_subscriptions(id) ON DELETE SET NULL,
    CONSTRAINT fk_billing_invoices_plan FOREIGN KEY (plan_id) REFERENCES subscription_plans(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payment_transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    invoice_id INT UNSIGNED NOT NULL,
    status ENUM('pending', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    provider VARCHAR(40) NOT NULL DEFAULT 'mock',
    payment_method VARCHAR(40) NULL,
    amount_cents INT UNSIGNED NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'BRL',
    provider_transaction_id VARCHAR(120) NULL,
    payload_json LONGTEXT NULL,
    processed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_payment_transactions_user (user_id, status, created_at),
    INDEX idx_payment_transactions_invoice (invoice_id),
    CONSTRAINT fk_payment_transactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_payment_transactions_invoice FOREIGN KEY (invoice_id) REFERENCES billing_invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS subscription_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    subscription_id INT UNSIGNED NULL,
    event_key VARCHAR(80) NOT NULL,
    message VARCHAR(255) NULL,
    payload_json LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_subscription_events_user (user_id, created_at),
    CONSTRAINT fk_subscription_events_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_subscription_events_subscription FOREIGN KEY (subscription_id) REFERENCES user_subscriptions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS billing_promotions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(140) NOT NULL,
    code VARCHAR(60) NULL UNIQUE,
    description VARCHAR(255) NULL,
    plan_id INT UNSIGNED NULL,
    discount_type ENUM('percent', 'amount') NOT NULL DEFAULT 'percent',
    discount_value INT UNSIGNED NOT NULL DEFAULT 0,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    is_public TINYINT(1) NOT NULL DEFAULT 1,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_billing_promotions_status (status, starts_at, ends_at),
    CONSTRAINT fk_billing_promotions_plan FOREIGN KEY (plan_id) REFERENCES subscription_plans(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS billing_announcements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    announcement_type ENUM('discount', 'reajuste', 'informativo') NOT NULL DEFAULT 'informativo',
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_billing_announcements_status (status, starts_at, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS social_channels (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS video_channels (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS content_platforms (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    platform_type ENUM('social', 'video', 'blog', 'podcast', 'email', 'other') NOT NULL DEFAULT 'social',
    source VARCHAR(32) NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS content_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    description TEXT NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS content_pillars (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(140) NOT NULL,
    slug VARCHAR(160) NOT NULL UNIQUE,
    description TEXT NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS content_objectives (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    description TEXT NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS holiday_regions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    country_code CHAR(2) NOT NULL,
    state_code VARCHAR(12) NULL,
    region_type ENUM('country', 'state', 'city', 'international') NOT NULL DEFAULT 'country',
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS holidays (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    holiday_date DATE NOT NULL,
    month_day CHAR(5) NULL,
    is_fixed TINYINT(1) NOT NULL DEFAULT 1,
    is_movable TINYINT(1) NOT NULL DEFAULT 0,
    movable_rule VARCHAR(120) NULL,
    holiday_type ENUM('national', 'regional', 'international') NOT NULL DEFAULT 'national',
    holiday_region_id INT UNSIGNED NULL,
    country_code CHAR(2) NULL,
    state_code VARCHAR(12) NULL,
    description TEXT NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_holidays_date (holiday_date),
    INDEX idx_holidays_month_day (month_day),
    CONSTRAINT fk_holidays_region FOREIGN KEY (holiday_region_id) REFERENCES holiday_regions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS commemorative_dates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(180) NOT NULL,
    event_date DATE NOT NULL,
    month_day CHAR(5) NULL,
    recurrence_type ENUM('none', 'yearly') NOT NULL DEFAULT 'yearly',
    context_type ENUM('commercial', 'institutional', 'seasonal', 'editorial') NOT NULL DEFAULT 'editorial',
    country_code CHAR(2) NULL,
    description TEXT NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_commemorative_date (event_date),
    INDEX idx_commemorative_month_day (month_day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS campaigns (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    description TEXT NULL,
    objective VARCHAR(160) NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    status ENUM('planned', 'active', 'completed', 'archived') NOT NULL DEFAULT 'planned',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS content_suggestions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    suggestion_date DATE NOT NULL,
    month_day CHAR(5) NULL,
    is_recurring TINYINT(1) NOT NULL DEFAULT 1,
    recurrence_type ENUM('none', 'yearly', 'monthly') NOT NULL DEFAULT 'yearly',
    content_category_id INT UNSIGNED NULL,
    content_pillar_id INT UNSIGNED NULL,
    content_objective_id INT UNSIGNED NULL,
    campaign_id INT UNSIGNED NULL,
    format_type VARCHAR(80) NOT NULL,
    context_type ENUM('commercial', 'institutional', 'seasonal', 'editorial') NOT NULL DEFAULT 'editorial',
    channel_priority VARCHAR(140) NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_suggestions_date (suggestion_date),
    INDEX idx_suggestions_month_day (month_day),
    CONSTRAINT fk_suggestions_category FOREIGN KEY (content_category_id) REFERENCES content_categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_suggestions_pillar FOREIGN KEY (content_pillar_id) REFERENCES content_pillars(id) ON DELETE SET NULL,
    CONSTRAINT fk_suggestions_objective FOREIGN KEY (content_objective_id) REFERENCES content_objectives(id) ON DELETE SET NULL,
    CONSTRAINT fk_suggestions_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS content_suggestion_channels (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    content_suggestion_id INT UNSIGNED NOT NULL,
    content_platform_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY ux_suggestion_channel (content_suggestion_id, content_platform_id),
    CONSTRAINT fk_sc_suggestion FOREIGN KEY (content_suggestion_id) REFERENCES content_suggestions(id) ON DELETE CASCADE,
    CONSTRAINT fk_sc_platform FOREIGN KEY (content_platform_id) REFERENCES content_platforms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS content_suggestion_tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    content_suggestion_id INT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY ux_suggestion_tag (content_suggestion_id, tag_id),
    CONSTRAINT fk_st_suggestion FOREIGN KEY (content_suggestion_id) REFERENCES content_suggestions(id) ON DELETE CASCADE,
    CONSTRAINT fk_st_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS content_plans (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    campaign_id INT UNSIGNED NULL,
    name VARCHAR(180) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    year_ref SMALLINT NOT NULL,
    month_ref TINYINT NULL,
    filters_json LONGTEXT NULL,
    status ENUM('draft', 'active', 'archived') NOT NULL DEFAULT 'draft',
    notes TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_plans_period (start_date, end_date),
    CONSTRAINT fk_plans_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_plans_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS content_plan_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    content_plan_id INT UNSIGNED NOT NULL,
    planned_date DATE NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    content_suggestion_id INT UNSIGNED NULL,
    campaign_id INT UNSIGNED NULL,
    content_objective_id INT UNSIGNED NULL,
    format_type VARCHAR(80) NULL,
    channels_json LONGTEXT NULL,
    status ENUM('planned', 'scheduled', 'published', 'skipped') NOT NULL DEFAULT 'planned',
    manual_note TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_plan_items_date (planned_date),
    CONSTRAINT fk_plan_items_plan FOREIGN KEY (content_plan_id) REFERENCES content_plans(id) ON DELETE CASCADE,
    CONSTRAINT fk_plan_items_suggestion FOREIGN KEY (content_suggestion_id) REFERENCES content_suggestions(id) ON DELETE SET NULL,
    CONSTRAINT fk_plan_items_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE SET NULL,
    CONSTRAINT fk_plan_items_objective FOREIGN KEY (content_objective_id) REFERENCES content_objectives(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS content_day_notes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    note_date DATE NOT NULL,
    context_type ENUM('commercial', 'institutional', 'seasonal', 'editorial') NOT NULL DEFAULT 'editorial',
    note_text TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY ux_user_note_date (user_id, note_date, context_type),
    CONSTRAINT fk_day_notes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS calendar_extra_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    event_date DATE NOT NULL,
    title VARCHAR(190) NOT NULL,
    event_type VARCHAR(80) NOT NULL DEFAULT 'extra',
    description TEXT NULL,
    color_hex VARCHAR(7) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_calendar_extra_user_date (user_id, event_date),
    CONSTRAINT fk_calendar_extra_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_calendar_colors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    color_key VARCHAR(80) NOT NULL,
    color_hex VARCHAR(7) NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY ux_user_color (user_id, color_key),
    CONSTRAINT fk_user_calendar_colors_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS social_connections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    platform_slug VARCHAR(80) NOT NULL,
    account_name VARCHAR(190) NULL,
    platform_user_id VARCHAR(190) NULL,
    access_token_enc LONGTEXT NULL,
    refresh_token_enc LONGTEXT NULL,
    scopes_text TEXT NULL,
    token_expires_at DATETIME NULL,
    status ENUM('connected', 'manual', 'revoked') NOT NULL DEFAULT 'connected',
    metadata_json LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY ux_social_user_platform (user_id, platform_slug),
    INDEX idx_social_connection_user (user_id),
    CONSTRAINT fk_social_connections_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS social_content_drafts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(220) NOT NULL,
    goal VARCHAR(140) NULL,
    pillar VARCHAR(140) NULL,
    frequency VARCHAR(60) NULL,
    channels_json LONGTEXT NULL,
    base_text LONGTEXT NULL,
    hooks_json LONGTEXT NULL,
    hashtags_json LONGTEXT NULL,
    cta_text TEXT NULL,
    variants_json LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_social_drafts_user (user_id, created_at),
    CONSTRAINT fk_social_drafts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS social_format_presets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    platform_slug VARCHAR(80) NOT NULL,
    format_type ENUM('post', 'carousel') NOT NULL DEFAULT 'post',
    preset_name VARCHAR(150) NOT NULL,
    width_px SMALLINT UNSIGNED NOT NULL,
    height_px SMALLINT UNSIGNED NOT NULL,
    aspect_ratio VARCHAR(20) NOT NULL,
    safe_area_text VARCHAR(140) NULL,
    color_hex VARCHAR(7) NULL,
    notes TEXT NULL,
    source_links_json LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_social_format_user (user_id, platform_slug, format_type),
    CONSTRAINT fk_social_format_presets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS social_publications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    plan_id INT UNSIGNED NULL,
    plan_item_id INT UNSIGNED NULL,
    platform_slug VARCHAR(80) NOT NULL,
    connection_id INT UNSIGNED NULL,
    title VARCHAR(220) NULL,
    message_text LONGTEXT NULL,
    media_url VARCHAR(1000) NULL,
    payload_json LONGTEXT NULL,
    status ENUM('queued', 'processing', 'published', 'failed', 'manual_review') NOT NULL DEFAULT 'queued',
    provider_post_id VARCHAR(190) NULL,
    scheduled_at DATETIME NULL,
    published_at DATETIME NULL,
    error_message VARCHAR(255) NULL,
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_social_publications_user (user_id, status, scheduled_at),
    INDEX idx_social_publications_item (plan_item_id),
    CONSTRAINT fk_social_publications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_social_publications_plan FOREIGN KEY (plan_id) REFERENCES content_plans(id) ON DELETE SET NULL,
    CONSTRAINT fk_social_publications_item FOREIGN KEY (plan_item_id) REFERENCES content_plan_items(id) ON DELETE SET NULL,
    CONSTRAINT fk_social_publications_connection FOREIGN KEY (connection_id) REFERENCES social_connections(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS social_publication_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    publication_id INT UNSIGNED NOT NULL,
    log_level ENUM('info', 'warning', 'error') NOT NULL DEFAULT 'info',
    message VARCHAR(255) NOT NULL,
    context_json LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_social_publication_logs_pub (publication_id, created_at),
    CONSTRAINT fk_social_publication_logs_pub FOREIGN KEY (publication_id) REFERENCES social_publications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS campaign_tracking_links (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    campaign_id INT UNSIGNED NULL,
    plan_item_id INT UNSIGNED NULL,
    channel_slug VARCHAR(80) NULL,
    destination_url VARCHAR(1000) NOT NULL,
    tracking_url VARCHAR(1600) NOT NULL,
    short_code VARCHAR(24) NOT NULL UNIQUE,
    short_url VARCHAR(1000) NOT NULL,
    external_short_url VARCHAR(1000) NULL,
    short_provider VARCHAR(40) NOT NULL DEFAULT 'internal',
    utm_source VARCHAR(120) NULL,
    utm_medium VARCHAR(120) NULL,
    utm_campaign VARCHAR(160) NULL,
    utm_content VARCHAR(160) NULL,
    utm_term VARCHAR(160) NULL,
    mtm_campaign VARCHAR(160) NULL,
    mtm_keyword VARCHAR(160) NULL,
    clicks INT UNSIGNED NOT NULL DEFAULT 0,
    last_clicked_at DATETIME NULL,
    status ENUM('active', 'archived') NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_tracking_user (user_id, created_at),
    INDEX idx_tracking_campaign (campaign_id),
    INDEX idx_tracking_item (plan_item_id),
    CONSTRAINT fk_tracking_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_tracking_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE SET NULL,
    CONSTRAINT fk_tracking_plan_item FOREIGN KEY (plan_item_id) REFERENCES content_plan_items(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS feature_flags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    flag_key VARCHAR(120) NOT NULL UNIQUE,
    label VARCHAR(180) NOT NULL,
    description TEXT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    target_area ENUM('all', 'admin', 'client') NOT NULL DEFAULT 'all',
    rollout_strategy ENUM('all', 'admins_only', 'clients_only', 'min_hierarchy', 'permission') NOT NULL DEFAULT 'all',
    min_hierarchy_level INT UNSIGNED NULL,
    required_permission VARCHAR(160) NULL,
    payload_json LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_feature_flags_area (enabled, target_area)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS automations_webhooks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    event_key VARCHAR(120) NOT NULL,
    endpoint_url VARCHAR(500) NOT NULL,
    http_method ENUM('POST', 'PUT', 'PATCH') NOT NULL DEFAULT 'POST',
    auth_type ENUM('none', 'bearer', 'basic', 'header') NOT NULL DEFAULT 'none',
    auth_username VARCHAR(190) NULL,
    auth_secret VARCHAR(255) NULL,
    header_name VARCHAR(120) NULL,
    header_value VARCHAR(255) NULL,
    signing_secret VARCHAR(255) NULL,
    timeout_seconds TINYINT UNSIGNED NOT NULL DEFAULT 8,
    retries TINYINT UNSIGNED NOT NULL DEFAULT 1,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_automation_webhooks_event (enabled, event_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS automation_dispatch_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    webhook_id INT UNSIGNED NOT NULL,
    event_key VARCHAR(120) NOT NULL,
    status ENUM('success', 'failed') NOT NULL DEFAULT 'failed',
    http_status SMALLINT NULL,
    duration_ms INT UNSIGNED NULL,
    response_body TEXT NULL,
    error_message VARCHAR(255) NULL,
    payload_json LONGTEXT NULL,
    attempted_at DATETIME NOT NULL,
    INDEX idx_automation_dispatch_event (event_key, attempted_at),
    INDEX idx_automation_dispatch_webhook (webhook_id, attempted_at),
    CONSTRAINT fk_automation_dispatch_webhook FOREIGN KEY (webhook_id) REFERENCES automations_webhooks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS observability_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    level ENUM('debug', 'info', 'warning', 'error', 'critical') NOT NULL DEFAULT 'info',
    category VARCHAR(80) NOT NULL,
    message VARCHAR(255) NOT NULL,
    area VARCHAR(20) NOT NULL,
    user_id INT UNSIGNED NULL,
    trace_id VARCHAR(64) NULL,
    context_json LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_observability_level (level, created_at),
    INDEX idx_observability_category (category, created_at),
    INDEX idx_observability_area (area, created_at),
    INDEX idx_observability_user (user_id, created_at),
    CONSTRAINT fk_observability_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS observability_spans (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trace_id VARCHAR(64) NOT NULL,
    span_key VARCHAR(120) NOT NULL,
    area VARCHAR(20) NOT NULL,
    user_id INT UNSIGNED NULL,
    status ENUM('running', 'ok', 'warning', 'error') NOT NULL DEFAULT 'running',
    context_json LONGTEXT NULL,
    started_at DATETIME NOT NULL,
    ended_at DATETIME NULL,
    duration_ms INT UNSIGNED NULL,
    INDEX idx_observability_spans_trace (trace_id),
    INDEX idx_observability_spans_key (span_key, started_at),
    CONSTRAINT fk_observability_spans_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS job_monitors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_key VARCHAR(140) NOT NULL UNIQUE,
    name VARCHAR(180) NOT NULL,
    description TEXT NULL,
    expected_interval_minutes INT UNSIGNED NOT NULL DEFAULT 60,
    max_runtime_seconds INT UNSIGNED NOT NULL DEFAULT 300,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    last_checkin_at DATETIME NULL,
    last_status ENUM('ok', 'warning', 'error', 'stale') NOT NULL DEFAULT 'stale',
    last_duration_ms INT UNSIGNED NULL,
    last_error VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_job_monitors_status (enabled, last_status, last_checkin_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS job_checkins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    monitor_id INT UNSIGNED NOT NULL,
    status ENUM('ok', 'warning', 'error') NOT NULL DEFAULT 'ok',
    duration_ms INT UNSIGNED NULL,
    error_message VARCHAR(255) NULL,
    payload_json LONGTEXT NULL,
    checked_at DATETIME NOT NULL,
    INDEX idx_job_checkins_monitor (monitor_id, checked_at),
    CONSTRAINT fk_job_checkins_monitor FOREIGN KEY (monitor_id) REFERENCES job_monitors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS job_alerts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    monitor_id INT UNSIGNED NOT NULL,
    alert_type ENUM('failure', 'stale', 'slow') NOT NULL,
    status ENUM('open', 'resolved') NOT NULL DEFAULT 'open',
    message VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    resolved_at DATETIME NULL,
    INDEX idx_job_alerts_status (status, created_at),
    INDEX idx_job_alerts_monitor (monitor_id, status),
    CONSTRAINT fk_job_alerts_monitor FOREIGN KEY (monitor_id) REFERENCES job_monitors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_feature_overrides (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    feature_key VARCHAR(120) NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY ux_user_feature_overrides_user_feature (user_id, feature_key),
    INDEX idx_user_feature_overrides_feature (feature_key),
    CONSTRAINT fk_user_feature_overrides_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS security_login_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    area VARCHAR(20) NOT NULL,
    email VARCHAR(190) NULL,
    ip_address VARCHAR(64) NOT NULL,
    user_agent VARCHAR(255) NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    reason_code VARCHAR(80) NULL,
    user_id INT UNSIGNED NULL,
    attempted_at DATETIME NOT NULL,
    INDEX idx_security_login_ip (area, ip_address, attempted_at),
    INDEX idx_security_login_email (area, email, attempted_at),
    INDEX idx_security_login_user (user_id),
    CONSTRAINT fk_security_login_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS security_audit_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(80) NOT NULL,
    severity ENUM('info', 'warning', 'critical') NOT NULL DEFAULT 'info',
    area VARCHAR(20) NOT NULL,
    user_id INT UNSIGNED NULL,
    ip_address VARCHAR(64) NOT NULL,
    user_agent VARCHAR(255) NULL,
    payload_json LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_security_audit_user (user_id, area, created_at),
    INDEX idx_security_audit_event (event_type, created_at),
    CONSTRAINT fk_security_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    key_name VARCHAR(180) NOT NULL UNIQUE,
    value_text LONGTEXT NULL,
    autoload TINYINT(1) NOT NULL DEFAULT 1,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS languages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(15) NOT NULL UNIQUE,
    name VARCHAR(80) NOT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
