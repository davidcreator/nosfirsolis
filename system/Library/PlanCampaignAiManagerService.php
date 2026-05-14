<?php

namespace System\Library;

use System\Engine\Registry;

class PlanCampaignAiManagerService
{
    use TemporalClockTrait;

    private const DEFAULT_MANAGER = 'strategist_balanced';
    private const SETTING_DEFAULT_MANAGER = 'plans_ai_default_manager';
    private const SETTING_USER_PREFIX = 'plans_ai_user_';

    public function __construct(private readonly Registry $registry)
    {
    }

    public function availableManagers(): array
    {
        return [
            'strategist_balanced' => [
                'id' => 'strategist_balanced',
                'name' => 'Strategist Balanced',
                'description' => 'Equilibrio entre consistencia editorial e conversao comercial.',
                'cadence_factor' => 1.0,
            ],
            'strategist_growth' => [
                'id' => 'strategist_growth',
                'name' => 'Strategist Growth',
                'description' => 'Cadencia mais agressiva para acelerar alcance e geracao de demanda.',
                'cadence_factor' => 0.72,
            ],
            'strategist_authority' => [
                'id' => 'strategist_authority',
                'name' => 'Strategist Authority',
                'description' => 'Foco em autoridade tecnica, profundidade e educacao do mercado.',
                'cadence_factor' => 1.18,
            ],
        ];
    }

    public function defaultManagerId(): string
    {
        $stored = $this->normalizeManagerId((string) $this->getSettingValue(self::SETTING_DEFAULT_MANAGER, ''));
        return $stored !== '' ? $stored : self::DEFAULT_MANAGER;
    }

    public function defaultManager(): array
    {
        return $this->managerById($this->defaultManagerId());
    }

    public function userAssignmentId(int $userId): ?string
    {
        if ($userId <= 0) {
            return null;
        }

        $stored = $this->normalizeManagerId((string) $this->getSettingValue(self::SETTING_USER_PREFIX . $userId, ''));
        return $stored !== '' ? $stored : null;
    }

    public function resolveManagerForUser(int $userId): array
    {
        $assignment = $this->userAssignmentId($userId);
        if ($assignment !== null) {
            return [
                'manager' => $this->managerById($assignment),
                'source' => 'user',
            ];
        }

        return [
            'manager' => $this->defaultManager(),
            'source' => 'default',
        ];
    }

    public function setDefaultManager(string $managerId): bool
    {
        $normalized = $this->normalizeManagerId($managerId);
        if ($normalized === '') {
            return false;
        }

        $this->setSettingValue(self::SETTING_DEFAULT_MANAGER, $normalized);
        return true;
    }

    public function assignManagerToUser(int $userId, string $managerId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $normalized = $this->normalizeManagerId($managerId);
        if ($normalized === '') {
            return false;
        }

        $this->setSettingValue(self::SETTING_USER_PREFIX . $userId, $normalized);
        return true;
    }

    public function clearUserAssignment(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $this->deleteSettingValue(self::SETTING_USER_PREFIX . $userId);
    }

    public function buildPlanCampaignBlueprint(array $input): array
    {
        $userId = (int) ($input['user_id'] ?? 0);
        $explicitManager = $this->normalizeManagerId((string) ($input['manager_id'] ?? ''));

        if ($explicitManager !== '') {
            $manager = $this->managerById($explicitManager);
            $managerSource = 'manual';
        } else {
            $resolved = $this->resolveManagerForUser($userId);
            $manager = (array) ($resolved['manager'] ?? $this->defaultManager());
            $managerSource = (string) ($resolved['source'] ?? 'default');
        }

        $theme = $this->cleanText((string) ($input['theme'] ?? 'Crescimento de conteudo'), 120);
        $objective = $this->cleanText((string) ($input['objective'] ?? 'geracao de demanda'), 120);
        $audience = $this->cleanText((string) ($input['audience'] ?? 'publico qualificado'), 120);
        $tone = $this->cleanText((string) ($input['tone'] ?? 'consultivo e objetivo'), 120);
        $frequency = $this->normalizeFrequency((string) ($input['frequency'] ?? 'semanal'));
        $campaignFocus = $this->cleanText((string) ($input['campaign_focus'] ?? ''), 140);
        if ($campaignFocus === '') {
            $campaignFocus = $theme;
        }

        $startDate = $this->normalizeDate((string) ($input['start_date'] ?? ''));
        $endDate = $this->normalizeDate((string) ($input['end_date'] ?? ''));

        if ($startDate === null) {
            $startDate = $this->clockFormat('Y-m-d');
        }

        if ($endDate === null) {
            $fallbackEnd = $this->dateAfterDays($startDate, 60);
            $endDate = $fallbackEnd ?? $startDate;
        }

        if ($endDate < $startDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $channels = [];
        foreach ((array) ($input['channels'] ?? []) as $channel) {
            $slug = $this->channelSlug((string) $channel);
            if ($slug !== '') {
                $channels[$slug] = true;
            }
        }
        $channels = array_keys($channels);
        if ($channels === []) {
            $channels = $this->defaultChannelsForManager((string) ($manager['id'] ?? self::DEFAULT_MANAGER));
        }

        $items = $this->buildItems(
            $theme,
            $objective,
            $audience,
            $tone,
            $frequency,
            $startDate,
            $endDate,
            $channels,
            $manager
        );

        $campaignStatus = $this->campaignStatusByPeriod($startDate, $endDate);
        $campaignName = $this->buildCampaignName((string) ($manager['id'] ?? self::DEFAULT_MANAGER), $campaignFocus, $objective);
        $planName = $this->buildPlanName($theme, $startDate, $endDate);

        $planNotes = 'Plano criado por IA de gestao (' . (string) ($manager['name'] ?? 'Strategist') . ').'
            . "\n"
            . 'Objetivo principal: ' . $objective . '.'
            . "\n"
            . 'Publico foco: ' . $audience . '.'
            . "\n"
            . 'Tom editorial: ' . $tone . '.'
            . "\n"
            . 'Fonte da configuracao: ' . $managerSource . '.';

        return [
            'manager' => $manager,
            'manager_source' => $managerSource,
            'campaign' => [
                'name' => $campaignName,
                'objective' => $objective,
                'description' => 'Campanha orientada por IA para ' . strtolower($campaignFocus) . '.',
                'status' => $campaignStatus,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'plan' => [
                'name' => $planName,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'frequency' => $frequency,
                'channels' => $channels,
                'notes' => $planNotes,
            ],
            'items' => $items,
        ];
    }

    private function buildItems(
        string $theme,
        string $objective,
        string $audience,
        string $tone,
        string $frequency,
        string $startDate,
        string $endDate,
        array $channels,
        array $manager
    ): array {
        $managerId = (string) ($manager['id'] ?? self::DEFAULT_MANAGER);
        $templates = $this->managerTemplates($managerId);

        $baseInterval = $this->frequencyIntervalDays($frequency);
        $cadenceFactor = max(0.5, min(1.6, (float) ($manager['cadence_factor'] ?? 1.0)));
        $stepDays = max(1, (int) round($baseInterval * $cadenceFactor));

        $items = [];
        $cursor = $startDate;
        $index = 0;

        while ($cursor <= $endDate && $index < 180) {
            $titleTemplate = $templates['titles'][$index % count($templates['titles'])];
            $format = $templates['formats'][$index % count($templates['formats'])];
            $title = $this->renderTemplate($titleTemplate, $theme, $objective, $audience);

            $description = 'Foco: ' . $objective . '. '
                . 'Tema: ' . $theme . '. '
                . 'Publico: ' . $audience . '. '
                . 'Tom: ' . $tone . '.';

            $items[] = [
                'planned_date' => $cursor,
                'title' => $title,
                'description' => $description,
                'format_type' => $format,
                'channels' => $channels,
                'status' => 'planned',
            ];

            $next = $this->dateAfterDays($cursor, $stepDays);
            if ($next === null || $next <= $cursor) {
                break;
            }

            $cursor = $next;
            $index++;
        }

        return $items;
    }

    private function managerTemplates(string $managerId): array
    {
        return match ($managerId) {
            'strategist_growth' => [
                'titles' => [
                    'Oferta da semana: {theme} para destravar {objective}',
                    'Acao rapida: {theme} com foco em conversao',
                    'Conteudo de tracao: {theme} para {audience}',
                    'Checklist de crescimento: {theme} em execucao',
                ],
                'formats' => ['reel', 'carrossel', 'post', 'story'],
            ],
            'strategist_authority' => [
                'titles' => [
                    'Analise tecnica: {theme} aplicado a {objective}',
                    'Framework pratico: {theme} para {audience}',
                    'Guia aprofundado: {theme} e padroes de execucao',
                    'Estudo de caso: {theme} com decisao estrategica',
                ],
                'formats' => ['artigo', 'carrossel', 'post', 'video curto'],
            ],
            default => [
                'titles' => [
                    'Plano pratico: {theme} para {objective}',
                    'Guia aplicado: {theme} com foco em resultado',
                    'Roteiro semanal: {theme} para {audience}',
                    'Boas praticas: {theme} em campanha ativa',
                ],
                'formats' => ['post', 'carrossel', 'video curto', 'story'],
            ],
        };
    }

    private function frequencyIntervalDays(string $frequency): int
    {
        return match ($frequency) {
            'diario' => 1,
            'quinzenal' => 14,
            'mensal' => 30,
            default => 7,
        };
    }

    private function normalizeFrequency(string $frequency): string
    {
        $frequency = strtolower(trim($frequency));
        return in_array($frequency, ['diario', 'semanal', 'quinzenal', 'mensal'], true)
            ? $frequency
            : 'semanal';
    }

    private function defaultChannelsForManager(string $managerId): array
    {
        return match ($managerId) {
            'strategist_growth' => ['instagram', 'tiktok', 'linkedin'],
            'strategist_authority' => ['linkedin', 'blog', 'youtube'],
            default => ['instagram', 'facebook', 'linkedin'],
        };
    }

    private function buildPlanName(string $theme, string $startDate, string $endDate): string
    {
        return 'Plano IA - ' . ucfirst($theme) . ' (' . $startDate . ' a ' . $endDate . ')';
    }

    private function buildCampaignName(string $managerId, string $focus, string $objective): string
    {
        $prefix = match ($managerId) {
            'strategist_growth' => 'Growth Sprint',
            'strategist_authority' => 'Authority Track',
            default => 'Balanced Drive',
        };

        return $prefix . ': ' . ucfirst($focus) . ' / ' . ucfirst($objective);
    }

    private function campaignStatusByPeriod(string $startDate, string $endDate): string
    {
        $today = $this->clockFormat('Y-m-d');

        if ($endDate < $today) {
            return 'completed';
        }

        if ($startDate <= $today && $today <= $endDate) {
            return 'active';
        }

        return 'planned';
    }

    private function renderTemplate(string $template, string $theme, string $objective, string $audience): string
    {
        $rendered = strtr($template, [
            '{theme}' => $theme,
            '{objective}' => $objective,
            '{audience}' => $audience,
        ]);

        return $this->cleanText($rendered, 180);
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches) !== 1) {
            return null;
        }

        $year = (int) ($matches[1] ?? 0);
        $month = (int) ($matches[2] ?? 0);
        $day = (int) ($matches[3] ?? 0);

        if ($year < 1970 || $year > 2100 || !checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private function dateAfterDays(string $date, int $days): ?string
    {
        $normalized = $this->normalizeDate($date);
        if ($normalized === null) {
            return null;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $normalized));
        $timestamp = mktime(0, 0, 0, $month, $day + $days, $year);
        if (!is_int($timestamp)) {
            return null;
        }

        return $this->clockFormatAt($timestamp, 'Y-m-d');
    }

    private function channelSlug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace('_', '-', $value);
        $value = preg_replace('/[^a-z0-9-]+/', '-', $value) ?: '';
        $value = trim($value, '-');

        return $value;
    }

    private function cleanText(string $value, int $maxLength): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
        if ($value === '') {
            return '';
        }

        return mb_substr($value, 0, max(1, $maxLength));
    }

    private function normalizeManagerId(string $managerId): string
    {
        $managerId = strtolower(trim($managerId));
        return isset($this->availableManagers()[$managerId]) ? $managerId : '';
    }

    private function managerById(string $managerId): array
    {
        $normalized = $this->normalizeManagerId($managerId);
        if ($normalized === '') {
            $normalized = self::DEFAULT_MANAGER;
        }

        return $this->availableManagers()[$normalized];
    }

    private function getSettingValue(string $key, string $default = ''): string
    {
        $db = $this->db();
        if (!$db) {
            return $default;
        }

        $row = $db->fetch('SELECT value_text FROM settings WHERE key_name = :key LIMIT 1', [
            'key' => $key,
        ]);

        return isset($row['value_text']) ? (string) $row['value_text'] : $default;
    }

    private function setSettingValue(string $key, string $value): void
    {
        $db = $this->db();
        if (!$db) {
            return;
        }

        $row = $db->fetch('SELECT id FROM settings WHERE key_name = :key LIMIT 1', [
            'key' => $key,
        ]);

        if ($row) {
            $db->update('settings', [
                'value_text' => $value,
                'autoload' => 1,
                'status' => 1,
                'updated_at' => $this->clockDateTimeNow(),
            ], 'id = :id', ['id' => (int) ($row['id'] ?? 0)]);
            return;
        }

        $db->insert('settings', [
            'key_name' => $key,
            'value_text' => $value,
            'autoload' => 1,
            'status' => 1,
            'created_at' => $this->clockDateTimeNow(),
            'updated_at' => $this->clockDateTimeNow(),
        ]);
    }

    private function deleteSettingValue(string $key): void
    {
        $db = $this->db();
        if (!$db) {
            return;
        }

        $db->delete('settings', 'key_name = :key', ['key' => $key]);
    }

    private function db(): ?Database
    {
        $db = $this->registry->get('db');
        if ($db instanceof Database && $db->connected()) {
            return $db;
        }

        return null;
    }
}

