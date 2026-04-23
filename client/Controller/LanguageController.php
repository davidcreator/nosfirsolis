<?php

namespace Client\Controller;

class LanguageController extends BaseController
{
    public function save(): void
    {
        $this->boot();
        $this->ensurePostWithCsrf();

        $languageCode = (string) $this->request->post('language_code', '');
        $redirectRoute = $this->sanitizeRedirectRoute((string) $this->request->post('redirect_route', 'dashboard/index'));

        if (!$this->auth->updateLanguagePreference($languageCode)) {
            flash('error', $this->t('common.flash_language_invalid', 'Invalid selected language.'));
            $this->redirectToRoute($redirectRoute);
        }

        flash('success', $this->t('common.flash_language_updated', 'Language updated successfully.'));
        $this->redirectToRoute($redirectRoute);
    }

    private function sanitizeRedirectRoute(string $route): string
    {
        $route = trim($route);
        $route = ltrim($route, '/');
        if ($route === '') {
            return 'dashboard/index';
        }

        if (preg_match('/^[a-zA-Z0-9_\/-]+$/', $route) !== 1) {
            return 'dashboard/index';
        }

        return $route;
    }
}
