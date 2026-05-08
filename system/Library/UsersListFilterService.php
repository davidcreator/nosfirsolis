<?php

namespace System\Library;

class UsersListFilterService
{
    public function normalize(array $source): array
    {
        $read = static function (array $data, string $key, mixed $default = ''): mixed {
            return array_key_exists($key, $data) ? $data[$key] : $default;
        };

        $q = trim((string) $read($source, 'f_q', ''));
        if (function_exists('mb_substr')) {
            $q = mb_substr($q, 0, 120);
        } else {
            $q = substr($q, 0, 120);
        }

        $scope = strtolower(trim((string) $read($source, 'f_scope', 'all')));
        if (!in_array($scope, ['all', 'manageable', 'restricted'], true)) {
            $scope = 'all';
        }

        $userStatus = strtolower(trim((string) $read($source, 'f_user_status', 'all')));
        if (!in_array($userStatus, ['all', 'active', 'inactive'], true)) {
            $userStatus = 'all';
        }

        $subscriptionStatus = strtolower(trim((string) $read($source, 'f_subscription_status', 'all')));
        if (!in_array($subscriptionStatus, ['all', 'trial', 'active', 'past_due', 'suspended', 'canceled'], true)) {
            $subscriptionStatus = 'all';
        }

        $overrideMode = strtolower(trim((string) $read($source, 'f_override_mode', 'all')));
        if (!in_array($overrideMode, ['all', 'custom', 'no_custom'], true)) {
            $overrideMode = 'all';
        }

        $groupId = max(0, (int) $read($source, 'f_group_id', 0));
        $planId = max(0, (int) $read($source, 'f_plan_id', 0));

        return [
            'q' => $q,
            'group_id' => $groupId,
            'plan_id' => $planId,
            'scope' => $scope,
            'user_status' => $userStatus,
            'subscription_status' => $subscriptionStatus,
            'override_mode' => $overrideMode,
        ];
    }

    public function apply(array $users, array $filters): array
    {
        $normalize = static function (string $value): string {
            $value = trim($value);
            return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        };

        $searchNeedle = $normalize((string) ($filters['q'] ?? ''));
        $groupId = (int) ($filters['group_id'] ?? 0);
        $planId = (int) ($filters['plan_id'] ?? 0);
        $scope = (string) ($filters['scope'] ?? 'all');
        $userStatus = (string) ($filters['user_status'] ?? 'all');
        $subscriptionStatus = (string) ($filters['subscription_status'] ?? 'all');
        $overrideMode = (string) ($filters['override_mode'] ?? 'all');

        return array_values(array_filter($users, static function (array $user) use (
            $normalize,
            $searchNeedle,
            $groupId,
            $planId,
            $scope,
            $userStatus,
            $subscriptionStatus,
            $overrideMode
        ): bool {
            if ($searchNeedle !== '') {
                $haystack = $normalize(
                    (string) ($user['name'] ?? '') . ' '
                    . (string) ($user['email'] ?? '') . ' '
                    . (string) ($user['group_name'] ?? '') . ' '
                    . (string) ($user['plan_name'] ?? '')
                );
                if (!str_contains($haystack, $searchNeedle)) {
                    return false;
                }
            }

            if ($groupId > 0 && (int) ($user['user_group_id'] ?? 0) !== $groupId) {
                return false;
            }

            if ($planId > 0 && (int) ($user['plan_id'] ?? 0) !== $planId) {
                return false;
            }

            if ($scope === 'manageable' && empty($user['can_manage_subscription'])) {
                return false;
            }
            if ($scope === 'restricted' && !empty($user['can_manage_subscription'])) {
                return false;
            }

            if ($userStatus !== 'all') {
                $currentStatus = (int) ($user['status'] ?? 0) === 1 ? 'active' : 'inactive';
                if ($currentStatus !== $userStatus) {
                    return false;
                }
            }

            if ($subscriptionStatus !== 'all') {
                $currentSubscriptionStatus = strtolower(trim((string) ($user['plan_status'] ?? '')));
                if ($currentSubscriptionStatus !== $subscriptionStatus) {
                    return false;
                }
            }

            $hasCustomOverrides = !empty($user['has_custom_overrides']);
            if ($overrideMode === 'custom' && !$hasCustomOverrides) {
                return false;
            }
            if ($overrideMode === 'no_custom' && $hasCustomOverrides) {
                return false;
            }

            return true;
        }));
    }

    public function buildQuery(array $filters, bool $skipDefaultFilters = false): string
    {
        $params = [];
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $params['f_q'] = $q;
        }

        $groupId = (int) ($filters['group_id'] ?? 0);
        if ($groupId > 0) {
            $params['f_group_id'] = $groupId;
        }

        $planId = (int) ($filters['plan_id'] ?? 0);
        if ($planId > 0) {
            $params['f_plan_id'] = $planId;
        }

        $scope = (string) ($filters['scope'] ?? 'all');
        if ($scope !== 'all') {
            $params['f_scope'] = $scope;
        }

        $userStatus = (string) ($filters['user_status'] ?? 'all');
        if ($userStatus !== 'all') {
            $params['f_user_status'] = $userStatus;
        }

        $subscriptionStatus = (string) ($filters['subscription_status'] ?? 'all');
        if ($subscriptionStatus !== 'all') {
            $params['f_subscription_status'] = $subscriptionStatus;
        }

        $overrideMode = (string) ($filters['override_mode'] ?? 'all');
        if ($overrideMode !== 'all') {
            $params['f_override_mode'] = $overrideMode;
        }

        if ($skipDefaultFilters) {
            $params['skip_default_filters'] = 1;
        }

        return $params === [] ? '' : http_build_query($params);
    }

    public function hasInput(array $source): bool
    {
        foreach (['f_q', 'f_group_id', 'f_plan_id', 'f_scope', 'f_user_status', 'f_subscription_status', 'f_override_mode'] as $key) {
            if (array_key_exists($key, $source)) {
                return true;
            }
        }

        return false;
    }
}
