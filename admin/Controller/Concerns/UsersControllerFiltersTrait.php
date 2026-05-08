<?php

namespace Admin\Controller\Concerns;

trait UsersControllerFiltersTrait
{
    public function saveDefaultFilters(): void
    {
        $this->boot('admin.users');
        $this->requirePostAndCsrf();

        $currentUserId = (int) ($this->auth->user()['id'] ?? 0);
        if ($currentUserId <= 0) {
            flash('error', $this->t('users.flash_default_filters_save_error', 'Nao foi possivel salvar o filtro padrao.'));
            $this->redirectToRoute('users/index');
        }

        $filters = $this->usersListFilter()->normalize((array) $this->request->post);
        $filtersQuery = $this->usersListFilter()->buildQuery($filters);

        try {
            $this->loader
                ->model('settings')
                ->setValue($this->usersDefaultFiltersSettingKey($currentUserId), $filtersQuery);
        } catch (\Throwable) {
            flash('error', $this->t('users.flash_default_filters_save_error', 'Nao foi possivel salvar o filtro padrao.'));
            $this->redirectToUsersIndex($this->returnUsersListFiltersQueryFromPost());
        }

        if ($filtersQuery === '') {
            flash('success', $this->t('users.flash_default_filters_cleared', 'Filtro padrao removido.'));
            $this->redirectToUsersIndex('');
        }

        flash('success', $this->t('users.flash_default_filters_saved', 'Filtro padrao salvo com sucesso.'));
        $this->redirectToUsersIndex($filtersQuery);
    }

    public function clearDefaultFilters(): void
    {
        $this->boot('admin.users');
        $this->requirePostAndCsrf();

        $currentUserId = (int) ($this->auth->user()['id'] ?? 0);
        if ($currentUserId <= 0) {
            flash('error', $this->t('users.flash_default_filters_remove_error', 'Nao foi possivel remover o filtro padrao.'));
            $this->redirectToRoute('users/index');
        }

        try {
            $this->loader
                ->model('settings')
                ->setValue($this->usersDefaultFiltersSettingKey($currentUserId), '');
        } catch (\Throwable) {
            flash('error', $this->t('users.flash_default_filters_remove_error', 'Nao foi possivel remover o filtro padrao.'));
            $this->redirectToUsersIndex($this->returnUsersListFiltersQueryFromPost());
        }

        flash('success', $this->t('users.flash_default_filters_cleared', 'Filtro padrao removido.'));
        $this->redirectToUsersIndex($this->returnUsersListFiltersQueryFromPost());
    }

    protected function returnUsersListFiltersQueryFromPost(): string
    {
        $rawQuery = trim((string) $this->request->post('_return_qs', ''));
        if ($rawQuery === '') {
            return '';
        }

        parse_str($rawQuery, $parsed);
        if (!is_array($parsed)) {
            return '';
        }

        $skipDefaultFilters = (int) ($parsed['skip_default_filters'] ?? 0) === 1;
        $filters = $this->usersListFilter()->normalize($parsed);
        return $this->usersListFilter()->buildQuery($filters, $skipDefaultFilters);
    }

    protected function redirectToUsersIndex(string $filtersQuery = ''): never
    {
        $url = route_url('users/index');
        $filtersQuery = ltrim(trim($filtersQuery), '?');
        if ($filtersQuery !== '') {
            $url .= '?' . $filtersQuery;
        }

        $this->response->redirect($url);
    }

    protected function loadSavedUsersListFilters(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        try {
            $rawQuery = trim((string) $this->loader
                ->model('settings')
                ->getValue($this->usersDefaultFiltersSettingKey($userId), ''));
        } catch (\Throwable) {
            return [];
        }
        if ($rawQuery === '') {
            return [];
        }

        parse_str($rawQuery, $parsed);
        if (!is_array($parsed)) {
            return [];
        }

        return $this->usersListFilter()->normalize($parsed);
    }

    protected function hasSavedUsersListFilters(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        try {
            $rawQuery = trim((string) $this->loader
                ->model('settings')
                ->getValue($this->usersDefaultFiltersSettingKey($userId), ''));
        } catch (\Throwable) {
            return false;
        }

        return $rawQuery !== '';
    }

    protected function usersDefaultFiltersSettingKey(int $userId): string
    {
        return 'users.default_filters.' . max(1, $userId);
    }

}
