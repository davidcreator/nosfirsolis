<?php

namespace Client\Controller\Concerns;

trait SocialContentActionsTrait
{
    public function generateDraft(): void
    {
        $this->boot('client.social');
        $this->ensurePostWithCsrf();

        $userId = (int) ($this->auth->user()['id'] ?? 0);
        $subscription = $this->subscriptionService();
        $feature = $subscription->evaluateFeature($userId, 'allow_ai_draft_generator');
        if (empty($feature['allowed'])) {
            flash('error', (string) ($feature['message'] ?? 'Recurso indisponivel no seu plano atual.'));
            $this->redirectToRoute('billing/index');
        }

        $input = [
            'theme' => (string) $this->request->post('theme', ''),
            'objective' => (string) $this->request->post('objective', ''),
            'pillar' => (string) $this->request->post('pillar', ''),
            'tone' => (string) $this->request->post('tone', ''),
            'audience' => (string) $this->request->post('audience', ''),
            'frequency' => (string) $this->request->post('frequency', 'semanal'),
            'cta' => (string) $this->request->post('cta', $this->t('social.default_cta', 'Comente sua opiniao')),
            'channels' => (array) $this->request->post('channels', []),
        ];

        $service = $this->contentStrategistService();
        $draft = $service->buildPack($input);

        $this->loader->model('social')->saveDraft($userId, $draft);

        flash('success', $this->t('social.flash_draft_generated', 'Novo conteudo estrategico gerado com sucesso.'));
        $this->redirectToRoute('social/index');
    }

    public function saveFormatPreset(): void
    {
        $this->boot('client.social');
        $this->ensurePostWithCsrf();

        $userId = (int) ($this->auth->user()['id'] ?? 0);
        $subscription = $this->subscriptionService();
        $feature = $subscription->evaluateFeature($userId, 'allow_format_presets');
        if (empty($feature['allowed'])) {
            flash('error', (string) ($feature['message'] ?? 'Recurso indisponivel no seu plano atual.'));
            $this->redirectToRoute('billing/index');
        }

        $standards = $this->socialFormatStandardsService();
        $platformSlug = strtolower(trim((string) $this->request->post('platform_slug', '')));
        $formatType = strtolower(trim((string) $this->request->post('format_type', 'post')));
        $presetName = trim((string) $this->request->post('preset_name', ''));
        $widthPx = (int) $this->request->post('width_px', 0);
        $heightPx = (int) $this->request->post('height_px', 0);
        $aspectRatio = trim((string) $this->request->post('aspect_ratio', ''));
        $safeAreaText = trim((string) $this->request->post('safe_area_text', ''));
        $colorHex = strtoupper(trim((string) $this->request->post('color_hex', '')));
        $notes = trim((string) $this->request->post('notes', ''));
        $sourceKeysCsv = trim((string) $this->request->post('source_keys', ''));

        $selectedPreset = $standards->presetFor($platformSlug, $formatType);
        if ($selectedPreset === null) {
            flash('error', $this->t('social.flash_preset_official_not_found', 'Preset oficial nao encontrado para a plataforma/formato selecionados.'));
            $this->redirectToRoute('social/index');
        }

        if ($widthPx <= 0 || $heightPx <= 0) {
            flash('error', $this->t('social.flash_preset_invalid_dimensions', 'Informe largura e altura validas para salvar o preset.'));
            $this->redirectToRoute('social/index?std_platform=' . rawurlencode($platformSlug) . '&std_format=' . rawurlencode($formatType));
        }

        if ($colorHex !== '' && preg_match('/^#[0-9A-F]{6}$/', $colorHex) !== 1) {
            flash('error', $this->t('social.flash_preset_invalid_color', 'Cor invalida. Use o formato hexadecimal #RRGGBB.'));
            $this->redirectToRoute('social/index?std_platform=' . rawurlencode($platformSlug) . '&std_format=' . rawurlencode($formatType));
        }

        $sourceKeys = [];
        if ($sourceKeysCsv !== '') {
            $sourceKeys = array_values(array_filter(array_map('trim', explode(',', $sourceKeysCsv)), static fn ($key): bool => $key !== ''));
        }

        $sources = $standards->resolveSources($sourceKeys);
        $sourceLinks = [];
        foreach ($sources as $source) {
            $sourceLinks[] = [
                'label' => (string) ($source['label'] ?? ''),
                'url' => (string) ($source['url'] ?? ''),
                'checked_at' => (string) ($source['checked_at'] ?? ''),
            ];
        }

        $this->loader->model('social')->createFormatPreset($userId, [
            'platform_slug' => $platformSlug,
            'format_type' => $formatType,
            'preset_name' => $presetName,
            'width_px' => $widthPx,
            'height_px' => $heightPx,
            'aspect_ratio' => $aspectRatio,
            'safe_area_text' => $safeAreaText,
            'color_hex' => $colorHex,
            'notes' => $notes,
            'source_links' => $sourceLinks,
        ]);

        flash('success', $this->t('social.flash_preset_saved', 'Preset personalizado salvo com sucesso.'));
        $this->redirectToRoute('social/index?std_platform=' . rawurlencode($platformSlug) . '&std_format=' . rawurlencode($formatType));
    }

    public function deleteFormatPreset(int $presetId): void
    {
        $this->boot('client.social');
        $this->ensurePostWithCsrf();

        if ($presetId <= 0) {
            flash('error', $this->t('social.flash_preset_invalid_for_delete', 'Preset invalido para exclusao.'));
            $this->redirectToRoute('social/index');
        }

        $userId = (int) ($this->auth->user()['id'] ?? 0);
        $this->loader->model('social')->deleteFormatPreset($userId, $presetId);

        flash('success', $this->t('social.flash_preset_deleted', 'Preset personalizado removido.'));
        $this->redirectToRoute('social/index');
    }
}
