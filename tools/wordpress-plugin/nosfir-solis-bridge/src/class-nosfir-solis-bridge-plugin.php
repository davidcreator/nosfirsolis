<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Nosfir_Solis_Bridge_Plugin
{
    private const OPTION_KEY = 'nosfir_solis_bridge_options';
    private const META_DELIVERY_ID = '_nosfir_delivery_id';
    private const META_SOURCE = '_nosfir_source';
    private const META_EVENT = '_nosfir_event';

    private static ?Nosfir_Solis_Bridge_Plugin $instance = null;

    /** @var array<string, mixed> */
    private array $options = [];

    public static function instance(): Nosfir_Solis_Bridge_Plugin
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function activate(): void
    {
        $stored = get_option(self::OPTION_KEY, []);
        $stored = is_array($stored) ? $stored : [];
        $defaults = self::defaults();

        if (!isset($stored['default_author_id']) || (int) $stored['default_author_id'] <= 0) {
            $stored['default_author_id'] = self::detectDefaultAuthorId();
        }

        update_option(self::OPTION_KEY, array_merge($defaults, $stored));
    }

    public static function uninstall(): void
    {
        delete_option(self::OPTION_KEY);
    }

    private function __construct()
    {
        $this->options = $this->getOptions();
        $this->registerHooks();
    }

    private function registerHooks(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
        add_action('admin_menu', [$this, 'registerAdminMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
    }

    public function registerRoutes(): void
    {
        register_rest_route('nosfir/v1', '/health', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'healthEndpoint'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('nosfir/v1', '/publish', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'publishEndpoint'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function healthEndpoint(WP_REST_Request $request): WP_REST_Response
    {
        $authMode = (string) ($this->options['auth_mode'] ?? 'hmac');
        $source = trim((string) $request->get_header('x-nosfir-source'));

        return new WP_REST_Response([
            'ok' => true,
            'plugin' => 'nosfir-solis-bridge',
            'version' => NOSFIR_SOLIS_BRIDGE_VERSION,
            'auth_mode' => $authMode,
            'source' => $source !== '' ? $source : null,
            'server_time' => gmdate('c'),
        ], 200);
    }

    public function publishEndpoint(WP_REST_Request $request)
    {
        $body = (string) $request->get_body();
        $auth = $this->validateAuth($request, $body);
        if ($auth !== true) {
            return $auth;
        }

        $json = $request->get_json_params();
        if (!is_array($json)) {
            return new WP_Error(
                'nosfir_invalid_payload',
                'Payload JSON invalido.',
                ['status' => 400]
            );
        }

        $payload = $this->extractPayload($json);
        $title = sanitize_text_field((string) ($payload['title'] ?? ''));
        $content = (string) ($payload['content'] ?? $payload['message_text'] ?? '');
        $content = wp_kses_post($content);

        if ($title === '' && $content === '') {
            return new WP_Error(
                'nosfir_missing_content',
                'Informe title ou content para criar a publicacao.',
                ['status' => 400]
            );
        }

        if ($title === '') {
            $title = wp_trim_words(wp_strip_all_tags($content), 12, '...');
            if ($title === '') {
                $title = 'Publicacao Solis';
            }
        }

        $postType = sanitize_key((string) ($payload['post_type'] ?? $this->options['default_post_type'] ?? 'post'));
        if ($postType === '' || !post_type_exists($postType)) {
            $postType = 'post';
        }

        $status = $this->normalizeStatus((string) ($payload['status'] ?? $this->options['default_post_status'] ?? 'draft'));
        $authorId = $this->normalizeAuthorId($payload);
        $deliveryId = $this->resolveDeliveryId($request, $json, $payload);
        $source = sanitize_text_field((string) ($json['source'] ?? $json['meta']['source'] ?? 'solis'));
        $event = sanitize_text_field((string) ($json['event'] ?? ''));

        $enforceIdempotency = !empty($this->options['enforce_idempotency']);
        if ($enforceIdempotency && $deliveryId !== '') {
            $existingPostId = $this->findPostByDeliveryId($deliveryId, $postType);
            if ($existingPostId > 0) {
                return new WP_REST_Response([
                    'ok' => true,
                    'duplicate' => true,
                    'message' => 'Delivery ja processado anteriormente.',
                    'post_id' => $existingPostId,
                    'edit_url' => get_edit_post_link($existingPostId, 'raw'),
                    'view_url' => get_permalink($existingPostId),
                ], 200);
            }
        }

        $postData = [
            'post_type' => $postType,
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => $status,
            'post_author' => $authorId,
        ];

        $slug = sanitize_title((string) ($payload['slug'] ?? ''));
        if ($slug !== '') {
            $postData['post_name'] = $slug;
        }

        $excerpt = sanitize_textarea_field((string) ($payload['excerpt'] ?? ''));
        if ($excerpt !== '') {
            $postData['post_excerpt'] = $excerpt;
        }

        $dateIso = trim((string) ($payload['date'] ?? $payload['scheduled_at'] ?? ''));
        $timestamp = $dateIso !== '' ? strtotime($dateIso) : false;
        if ($timestamp !== false) {
            $postData['post_date_gmt'] = gmdate('Y-m-d H:i:s', $timestamp);
            $postData['post_date'] = get_date_from_gmt($postData['post_date_gmt']);

            if ($status !== 'future' && $timestamp > (time() + 30)) {
                $postData['post_status'] = 'future';
            }
        }

        $postId = wp_insert_post($postData, true, false);
        if (is_wp_error($postId)) {
            return new WP_Error(
                'nosfir_insert_failed',
                $postId->get_error_message(),
                [
                    'status' => 500,
                    'details' => $postId->get_error_data(),
                ]
            );
        }
        $postId = (int) $postId;

        $categories = $this->toIntArray($payload['categories'] ?? []);
        if (!empty($categories)) {
            wp_set_post_terms($postId, $categories, 'category', false);
        }

        $tags = $payload['tags'] ?? [];
        if (is_array($tags) && !empty($tags)) {
            $tagsInput = [];
            foreach ($tags as $tag) {
                if (is_int($tag) || ctype_digit((string) $tag)) {
                    $tagsInput[] = (int) $tag;
                } elseif (is_string($tag)) {
                    $clean = sanitize_text_field($tag);
                    if ($clean !== '') {
                        $tagsInput[] = $clean;
                    }
                }
            }
            if (!empty($tagsInput)) {
                wp_set_post_terms($postId, $tagsInput, 'post_tag', false);
            }
        }

        $featuredMedia = (int) ($payload['featured_media'] ?? 0);
        if ($featuredMedia > 0 && get_post($featuredMedia) instanceof WP_Post) {
            set_post_thumbnail($postId, $featuredMedia);
        }

        if ($deliveryId !== '') {
            update_post_meta($postId, self::META_DELIVERY_ID, $deliveryId);
        }
        if ($source !== '') {
            update_post_meta($postId, self::META_SOURCE, $source);
        }
        if ($event !== '') {
            update_post_meta($postId, self::META_EVENT, $event);
        }

        if (isset($payload['meta']) && is_array($payload['meta'])) {
            $this->persistCustomMeta($postId, $payload['meta']);
        }

        return new WP_REST_Response([
            'ok' => true,
            'post_id' => $postId,
            'status' => get_post_status($postId),
            'delivery_id' => $deliveryId !== '' ? $deliveryId : null,
            'duplicate' => false,
            'edit_url' => get_edit_post_link($postId, 'raw'),
            'view_url' => get_permalink($postId),
        ], 201);
    }

    public function registerAdminMenu(): void
    {
        add_options_page(
            'Nosfir Solis Bridge',
            'Solis Bridge',
            'manage_options',
            'nosfir-solis-bridge',
            [$this, 'renderSettingsPage']
        );
    }

    public function registerSettings(): void
    {
        register_setting(
            'nosfir_solis_bridge',
            self::OPTION_KEY,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitizeOptions'],
                'default' => self::defaults(),
            ]
        );
    }

    public function sanitizeOptions($raw): array
    {
        $raw = is_array($raw) ? $raw : [];
        $current = $this->getOptions();

        $authMode = strtolower(trim((string) ($raw['auth_mode'] ?? $current['auth_mode'] ?? 'hmac')));
        if (!in_array($authMode, ['none', 'hmac', 'bearer'], true)) {
            $authMode = 'hmac';
        }

        $defaultStatus = strtolower(trim((string) ($raw['default_post_status'] ?? $current['default_post_status'] ?? 'draft')));
        $allowedStatuses = ['draft', 'publish', 'pending', 'private', 'future'];
        if (!in_array($defaultStatus, $allowedStatuses, true)) {
            $defaultStatus = 'draft';
        }

        $defaultPostType = sanitize_key((string) ($raw['default_post_type'] ?? $current['default_post_type'] ?? 'post'));
        if ($defaultPostType === '') {
            $defaultPostType = 'post';
        }

        $defaultAuthorId = (int) ($raw['default_author_id'] ?? $current['default_author_id'] ?? 0);
        if ($defaultAuthorId <= 0 || !get_user_by('id', $defaultAuthorId)) {
            $defaultAuthorId = self::detectDefaultAuthorId();
        }

        return [
            'auth_mode' => $authMode,
            'shared_secret' => trim((string) ($raw['shared_secret'] ?? $current['shared_secret'] ?? '')),
            'bearer_token' => trim((string) ($raw['bearer_token'] ?? $current['bearer_token'] ?? '')),
            'default_post_status' => $defaultStatus,
            'default_post_type' => $defaultPostType,
            'default_author_id' => $defaultAuthorId,
            'enforce_idempotency' => !empty($raw['enforce_idempotency']) ? 1 : 0,
        ];
    }

    public function renderSettingsPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $this->options = $this->getOptions();
        $restBase = esc_url_raw(rest_url('nosfir/v1'));
        ?>
        <div class="wrap">
            <h1>Nosfir Solis Bridge</h1>
            <p>Configure a autenticacao e os defaults para o endpoint de publicacao do Solis.</p>

            <p><strong>Health:</strong> <code><?php echo esc_html($restBase . '/health'); ?></code></p>
            <p><strong>Publish:</strong> <code><?php echo esc_html($restBase . '/publish'); ?></code></p>

            <form method="post" action="options.php">
                <?php settings_fields('nosfir_solis_bridge'); ?>
                <?php $optionName = esc_attr(self::OPTION_KEY); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="nosfir_auth_mode">Auth mode</label></th>
                        <td>
                            <select id="nosfir_auth_mode" name="<?php echo $optionName; ?>[auth_mode]">
                                <?php foreach (['hmac' => 'HMAC', 'bearer' => 'Bearer token', 'none' => 'None (somente dev)'] as $value => $label): ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($this->options['auth_mode'], $value); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="nosfir_shared_secret">Shared secret (HMAC)</label></th>
                        <td>
                            <input id="nosfir_shared_secret" type="password" class="regular-text" name="<?php echo $optionName; ?>[shared_secret]" value="<?php echo esc_attr((string) $this->options['shared_secret']); ?>">
                            <p class="description">Usado para validar <code>X-Nosfir-Signature: sha256=...</code>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="nosfir_bearer_token">Bearer token</label></th>
                        <td>
                            <input id="nosfir_bearer_token" type="password" class="regular-text" name="<?php echo $optionName; ?>[bearer_token]" value="<?php echo esc_attr((string) $this->options['bearer_token']); ?>">
                            <p class="description">Usado quando <code>Authorization: Bearer ...</code> estiver habilitado.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="nosfir_default_post_status">Default post status</label></th>
                        <td>
                            <select id="nosfir_default_post_status" name="<?php echo $optionName; ?>[default_post_status]">
                                <?php foreach (['draft', 'publish', 'pending', 'private', 'future'] as $status): ?>
                                    <option value="<?php echo esc_attr($status); ?>" <?php selected($this->options['default_post_status'], $status); ?>>
                                        <?php echo esc_html($status); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="nosfir_default_post_type">Default post type</label></th>
                        <td>
                            <input id="nosfir_default_post_type" type="text" class="regular-text" name="<?php echo $optionName; ?>[default_post_type]" value="<?php echo esc_attr((string) $this->options['default_post_type']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="nosfir_default_author_id">Default author ID</label></th>
                        <td>
                            <input id="nosfir_default_author_id" type="number" min="1" step="1" name="<?php echo $optionName; ?>[default_author_id]" value="<?php echo esc_attr((string) $this->options['default_author_id']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Idempotency</th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo $optionName; ?>[enforce_idempotency]" value="1" <?php checked(!empty($this->options['enforce_idempotency'])); ?>>
                                Evitar duplicidade por <code>delivery_id</code>.
                            </label>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Salvar configuracoes'); ?>
            </form>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $json
     * @return array<string, mixed>
     */
    private function extractPayload(array $json): array
    {
        if (isset($json['payload']) && is_array($json['payload'])) {
            return $json['payload'];
        }

        return $json;
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));
        $allowed = ['draft', 'publish', 'pending', 'private', 'future'];
        if (!in_array($status, $allowed, true)) {
            return 'draft';
        }

        return $status;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function normalizeAuthorId(array $payload): int
    {
        $authorId = (int) ($payload['author_id'] ?? 0);
        if ($authorId > 0 && get_user_by('id', $authorId)) {
            return $authorId;
        }

        $optionAuthor = (int) ($this->options['default_author_id'] ?? 0);
        if ($optionAuthor > 0 && get_user_by('id', $optionAuthor)) {
            return $optionAuthor;
        }

        return self::detectDefaultAuthorId();
    }

    /**
     * @param array<string, mixed> $json
     * @param array<string, mixed> $payload
     */
    private function resolveDeliveryId(WP_REST_Request $request, array $json, array $payload): string
    {
        $deliveryId = trim((string) ($json['delivery_id'] ?? $payload['delivery_id'] ?? ''));
        if ($deliveryId !== '') {
            return substr($deliveryId, 0, 190);
        }

        $headerDelivery = trim((string) $request->get_header('x-nosfir-delivery'));
        if ($headerDelivery !== '') {
            return substr($headerDelivery, 0, 190);
        }

        return '';
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function persistCustomMeta(int $postId, array $meta): void
    {
        foreach ($meta as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $metaKey = sanitize_key($key);
            if ($metaKey === '' || str_starts_with($metaKey, '_')) {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                update_post_meta($postId, $metaKey, $value);
                continue;
            }

            update_post_meta($postId, $metaKey, wp_json_encode($value));
        }
    }

    private function findPostByDeliveryId(string $deliveryId, string $postType): int
    {
        $ids = get_posts([
            'post_type' => $postType,
            'post_status' => 'any',
            'fields' => 'ids',
            'posts_per_page' => 1,
            'no_found_rows' => true,
            'meta_key' => self::META_DELIVERY_ID,
            'meta_value' => $deliveryId,
        ]);

        if (!is_array($ids) || empty($ids)) {
            return 0;
        }

        return (int) $ids[0];
    }

    /**
     * @param array<string, mixed>|mixed $raw
     * @return array<int, int>
     */
    private function toIntArray($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $normalized = [];
        foreach ($raw as $item) {
            $id = (int) $item;
            if ($id > 0) {
                $normalized[$id] = $id;
            }
        }

        return array_values($normalized);
    }

    private function validateAuth(WP_REST_Request $request, string $rawBody)
    {
        $authMode = strtolower(trim((string) ($this->options['auth_mode'] ?? 'hmac')));
        if ($authMode === 'none') {
            return true;
        }

        if ($authMode === 'bearer') {
            $expected = trim((string) ($this->options['bearer_token'] ?? ''));
            if ($expected === '') {
                return new WP_Error(
                    'nosfir_auth_not_configured',
                    'Bearer token nao configurado no plugin.',
                    ['status' => 500]
                );
            }

            $authorization = trim((string) $request->get_header('authorization'));
            if (!str_starts_with(strtolower($authorization), 'bearer ')) {
                return new WP_Error(
                    'nosfir_auth_missing',
                    'Header Authorization Bearer ausente.',
                    ['status' => 401]
                );
            }

            $provided = trim(substr($authorization, 7));
            if ($provided === '' || !hash_equals($expected, $provided)) {
                return new WP_Error(
                    'nosfir_auth_invalid',
                    'Bearer token invalido.',
                    ['status' => 403]
                );
            }

            return true;
        }

        $secret = trim((string) ($this->options['shared_secret'] ?? ''));
        if ($secret === '') {
            return new WP_Error(
                'nosfir_auth_not_configured',
                'Shared secret HMAC nao configurado no plugin.',
                ['status' => 500]
            );
        }

        $signatureHeader = trim((string) $request->get_header('x-nosfir-signature'));
        if ($signatureHeader === '') {
            return new WP_Error(
                'nosfir_signature_missing',
                'Header X-Nosfir-Signature ausente.',
                ['status' => 401]
            );
        }

        $provided = $signatureHeader;
        if (str_starts_with(strtolower($provided), 'sha256=')) {
            $provided = substr($provided, 7);
        }
        $provided = trim($provided);
        if ($provided === '') {
            return new WP_Error(
                'nosfir_signature_invalid',
                'Assinatura HMAC vazia.',
                ['status' => 403]
            );
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);
        if (!hash_equals($expected, $provided)) {
            return new WP_Error(
                'nosfir_signature_invalid',
                'Assinatura HMAC invalida.',
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function getOptions(): array
    {
        $stored = get_option(self::OPTION_KEY, []);
        $stored = is_array($stored) ? $stored : [];
        return array_merge(self::defaults(), $stored);
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaults(): array
    {
        return [
            'auth_mode' => 'hmac',
            'shared_secret' => '',
            'bearer_token' => '',
            'default_post_status' => 'draft',
            'default_post_type' => 'post',
            'default_author_id' => self::detectDefaultAuthorId(),
            'enforce_idempotency' => 1,
        ];
    }

    private static function detectDefaultAuthorId(): int
    {
        $admins = get_users([
            'role' => 'administrator',
            'number' => 1,
            'orderby' => 'ID',
            'order' => 'ASC',
            'fields' => ['ID'],
        ]);

        if (is_array($admins) && !empty($admins)) {
            $first = $admins[0];
            if (is_object($first) && isset($first->ID)) {
                return (int) $first->ID;
            }
            if (is_array($first) && isset($first['ID'])) {
                return (int) $first['ID'];
            }
        }

        return 1;
    }
}
