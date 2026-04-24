<?php

namespace Admin\Controller;

class LanguageController extends BaseController
{
    public function save(): void
    {
        $this->boot();
        $this->requirePostAndCsrf();

        $languageCode = (string) $this->request->post('language_code', '');
        $redirectRoute = $this->sanitizeRedirectRoute((string) $this->request->post('redirect_route', 'dashboard/index'));

        if (!$this->auth->updateLanguagePreference($languageCode)) {
            flash('error', $this->t('common.flash_language_invalid', 'Idioma inválido selecionado.'));
            $this->redirectToRoute($redirectRoute);
        }

        flash('success', $this->t('common.flash_language_updated', 'Idioma atualizado com sucesso.'));
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
