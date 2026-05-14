<?php

namespace Client\Controller;

use Client\Controller\Concerns\SocialConnectionFlowTrait;
use Client\Controller\Concerns\SocialContentActionsTrait;
use Client\Controller\Concerns\SocialPublishingActionsTrait;

class SocialController extends BaseController
{
    use SocialConnectionFlowTrait;
    use SocialContentActionsTrait;
    use SocialPublishingActionsTrait;

    public function index(): void
    {
        $this->boot('client.social');

        $user = $this->auth->user();
        $userId = (int) ($user['id'] ?? 0);

        $registry = $this->socialPlatformRegistry();
        $platforms = $registry->all();
        $accessKeyDocs = $this->accessKeyDocsByPlatform();

        $socialModel = $this->loader->model('social');
        $connections = $socialModel->connectionsByUser($userId);
        $savedPresets = $socialModel->formatPresetsByUser($userId);

        $connectionsBySlug = [];
        foreach ($connections as $connection) {
            $metadata = json_decode((string) ($connection['metadata_json'] ?? '{}'), true);
            $connection['metadata'] = is_array($metadata) ? $metadata : [];
            $connectionsBySlug[(string) $connection['platform_slug']] = $connection;
        }

        $standardsService = $this->socialFormatStandardsService();
        $matrixRows = $standardsService->matrixRows();
        $defaultPlatform = (string) ($matrixRows[0]['slug'] ?? 'instagram');

        $selectedPlatform = strtolower(trim((string) $this->request->get('std_platform', $defaultPlatform)));
        $selectedFormat = strtolower(trim((string) $this->request->get('std_format', 'post')));
        if (!in_array($selectedFormat, ['post', 'carousel'], true)) {
            $selectedFormat = 'post';
        }

        $selectedPreset = $standardsService->presetFor($selectedPlatform, $selectedFormat);
        if ($selectedPreset === null) {
            $selectedPlatform = $defaultPlatform;
            $selectedFormat = 'post';
            $selectedPreset = $standardsService->presetFor($selectedPlatform, $selectedFormat);
        }

        $resolvedSources = [];
        if (is_array($selectedPreset)) {
            $resolvedSources = $standardsService->resolveSources((array) ($selectedPreset['source_keys'] ?? []));
        }

        $security = $this->registry->get('security');
        $events = is_object($security) ? $security->recentEvents($userId, 'client', 20) : [];

        $publishService = $this->socialPublishingService();
        $publishService->ensureTables();
        $publicationQueue = $publishService->listByUser($userId, 120);

        $trackingService = $this->campaignTrackingService();
        $publishPlanItems = $trackingService->availablePlanItems($userId, 120);
        $bulkFailedPlatforms = array_values(array_unique(array_filter(array_map(
            static fn ($slug): string => strtolower(trim((string) $slug)),
            (array) $this->session->get('social_oauth_bulk_failed', [])
        ), static fn (string $slug): bool => $slug !== '')));

        $this->render('social/index', [
            'title' => $this->t('social.title_index', 'Central Social'),
            'platforms' => $platforms,
            'connections' => $connectionsBySlug,
            'drafts' => $socialModel->recentDrafts($userId, 12),
            'security_events' => $events,
            'standards_matrix' => $matrixRows,
            'standards_selected_platform' => $selectedPlatform,
            'standards_selected_format' => $selectedFormat,
            'standards_selected_preset' => $selectedPreset,
            'standards_selected_sources' => $resolvedSources,
            'saved_format_presets' => $savedPresets,
            'publication_queue' => $publicationQueue,
            'publish_plan_items' => $publishPlanItems,
            'access_key_docs' => $accessKeyDocs,
            'bulk_failed_platforms' => $bulkFailedPlatforms,
        ]);
    }
}
