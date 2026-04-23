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
    status TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_users_group FOREIGN KEY (user_group_id) REFERENCES user_groups(id)
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
