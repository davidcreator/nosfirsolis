<?php

namespace System\Library;

trait SubscriptionServiceBillingPromotionsAndAnnouncementsTrait
{
    public function listPromotions(int $limit = 200): array
    {
        if (!$this->db()?->connected()) {
            return [];
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return [];
        }
        $limit = max(1, min(500, $limit));

        return $this->db()->fetchAll(
            'SELECT bp.*, sp.name AS plan_name
             FROM billing_promotions bp
             LEFT JOIN subscription_plans sp ON sp.id = bp.plan_id
             ORDER BY bp.id DESC
             LIMIT ' . $limit
        );
    }

    public function savePromotion(array $payload): array
    {
        if (!$this->db()?->connected()) {
            return ['success' => false, 'message' => 'Nao foi possivel salvar promocao agora.'];
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return ['success' => false, 'message' => 'Schema de promocoes indisponivel para salvar.'];
        }
        $id = max(0, (int) ($payload['id'] ?? 0));
        $name = trim((string) ($payload['name'] ?? ''));
        if (mb_strlen($name) < 3) {
            return ['success' => false, 'message' => 'Nome da promocao deve ter ao menos 3 caracteres.'];
        }

        $planId = max(0, (int) ($payload['plan_id'] ?? 0));
        if ($planId <= 0) {
            $planId = null;
        }

        $discountType = strtolower(trim((string) ($payload['discount_type'] ?? 'percent')));
        if (!in_array($discountType, ['percent', 'amount'], true)) {
            $discountType = 'percent';
        }

        $discountValue = max(1, (int) ($payload['discount_value'] ?? 0));
        if ($discountType === 'percent') {
            $discountValue = min(100, $discountValue);
        }

        $code = strtoupper(trim((string) ($payload['code'] ?? '')));
        $code = preg_replace('/[^A-Z0-9_-]/', '', $code) ?: '';
        if ($code === '') {
            $code = null;
        }

        $startsAt = $this->normalizeDatetime($payload['starts_at'] ?? null);
        $endsAt = $this->normalizeDatetime($payload['ends_at'] ?? null);
        if ($startsAt !== null && $endsAt !== null && strcmp($endsAt, $startsAt) < 0) {
            return ['success' => false, 'message' => 'Periodo de promocao invalido: fim menor que inicio.'];
        }

        $description = mb_substr(trim((string) ($payload['description'] ?? '')), 0, 255);
        $status = $this->truthy($payload['status'] ?? 0) ? 1 : 0;
        $isPublic = $this->truthy($payload['is_public'] ?? 0) ? 1 : 0;

        if ($code !== null) {
            $existingCode = $this->db()->fetch(
                'SELECT id
                 FROM billing_promotions
                 WHERE code = :code
                   AND id <> :id
                 LIMIT 1',
                ['code' => $code, 'id' => $id]
            );
            if ($existingCode) {
                return ['success' => false, 'message' => 'Codigo de promocao ja esta em uso.'];
            }
        }

        $timestamp = $this->clockDateTimeNow();
        $data = [
            'name' => mb_substr($name, 0, 140),
            'code' => $code,
            'description' => $description !== '' ? $description : null,
            'plan_id' => $planId,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'is_public' => $isPublic,
            'status' => $status,
            'updated_at' => $timestamp,
        ];

        if ($id > 0) {
            $this->db()->update('billing_promotions', $data, 'id = :id', ['id' => $id]);
            return ['success' => true, 'message' => 'Promocao atualizada com sucesso.'];
        }

        $data['created_at'] = $timestamp;
        $this->db()->insert('billing_promotions', $data);
        return ['success' => true, 'message' => 'Promocao cadastrada com sucesso.'];
    }

    public function deletePromotion(int $promotionId): bool
    {
        if ($promotionId <= 0 || !$this->db()?->connected()) {
            return false;
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return false;
        }
        return $this->db()->delete('billing_promotions', 'id = :id', ['id' => $promotionId]) > 0;
    }

    public function listAnnouncements(int $limit = 200): array
    {
        if (!$this->db()?->connected()) {
            return [];
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return [];
        }
        $limit = max(1, min(500, $limit));

        return $this->db()->fetchAll(
            'SELECT *
             FROM billing_announcements
             ORDER BY id DESC
             LIMIT ' . $limit
        );
    }

    public function activeAnnouncements(int $limit = 10): array
    {
        if (!$this->db()?->connected()) {
            return [];
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return [];
        }
        $limit = max(1, min(100, $limit));
        $now = $this->clockDateTimeNow();

        return $this->db()->fetchAll(
            'SELECT *
             FROM billing_announcements
             WHERE status = 1
               AND (starts_at IS NULL OR starts_at <= :now_start)
               AND (ends_at IS NULL OR ends_at >= :now_end)
             ORDER BY starts_at DESC, id DESC
             LIMIT ' . $limit,
            [
                'now_start' => $now,
                'now_end' => $now,
            ]
        );
    }

    public function saveAnnouncement(array $payload): array
    {
        if (!$this->db()?->connected()) {
            return ['success' => false, 'message' => 'Nao foi possivel salvar comunicado agora.'];
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return ['success' => false, 'message' => 'Schema de comunicados indisponivel para salvar.'];
        }
        $id = max(0, (int) ($payload['id'] ?? 0));
        $title = trim((string) ($payload['title'] ?? ''));
        $message = trim((string) ($payload['message'] ?? ''));

        if (mb_strlen($title) < 3) {
            return ['success' => false, 'message' => 'Titulo do comunicado deve ter ao menos 3 caracteres.'];
        }
        if (mb_strlen($message) < 8) {
            return ['success' => false, 'message' => 'Mensagem do comunicado deve ter ao menos 8 caracteres.'];
        }

        $type = strtolower(trim((string) ($payload['announcement_type'] ?? 'informativo')));
        if (!in_array($type, ['discount', 'reajuste', 'informativo'], true)) {
            $type = 'informativo';
        }

        $startsAt = $this->normalizeDatetime($payload['starts_at'] ?? null);
        $endsAt = $this->normalizeDatetime($payload['ends_at'] ?? null);
        if ($startsAt !== null && $endsAt !== null && strcmp($endsAt, $startsAt) < 0) {
            return ['success' => false, 'message' => 'Periodo do comunicado invalido.'];
        }

        $timestamp = $this->clockDateTimeNow();
        $data = [
            'title' => mb_substr($title, 0, 180),
            'message' => mb_substr($message, 0, 2000),
            'announcement_type' => $type,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => $this->truthy($payload['status'] ?? 0) ? 1 : 0,
            'updated_at' => $timestamp,
        ];

        if ($id > 0) {
            $this->db()->update('billing_announcements', $data, 'id = :id', ['id' => $id]);
            return ['success' => true, 'message' => 'Comunicado atualizado com sucesso.'];
        }

        $data['created_at'] = $timestamp;
        $this->db()->insert('billing_announcements', $data);
        return ['success' => true, 'message' => 'Comunicado criado com sucesso.'];
    }

    public function deleteAnnouncement(int $announcementId): bool
    {
        if ($announcementId <= 0 || !$this->db()?->connected()) {
            return false;
        }

        $this->ensureTables();
        if (!$this->schemaAvailable) {
            return false;
        }
        return $this->db()->delete('billing_announcements', 'id = :id', ['id' => $announcementId]) > 0;
    }
}
