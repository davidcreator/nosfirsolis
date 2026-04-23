<?php

namespace System\Engine;

abstract class Controller
{
    public function __construct(protected Registry $registry)
    {
    }

    public function __get(string $key): mixed
    {
        return $this->registry->get($key);
    }

    protected function render(string $template, array $data = [], ?string $layout = 'layout/main'): void
    {
        $data['app_name'] = $this->config->get('app.name', 'Solis');
        $data['current_route'] = $this->request->route();
        $data['current_user'] = $this->auth ? $this->auth->user() : null;
        $data['message_success'] = flash('success');
        $data['message_error'] = flash('error');
        $data['language_code'] = method_exists($this->language, 'code') ? $this->language->code() : 'en-us';
        $data['t'] = fn (string $key, string $default = '', array $replacements = []): string => $this->t($key, $default, $replacements);

        $output = $this->view->render($template, $data, $layout);
        $this->response->setOutput($output);
    }

    protected function redirectToRoute(string $route): never
    {
        $this->response->redirect(route_url($route));
    }

    protected function requireAuth(string $permission = ''): void
    {
        if (!$this->auth || !$this->auth->check()) {
            $loginRoute = $this->config->get('routes.login_redirect', 'auth/login');
            $this->response->redirect(route_url($loginRoute));
        }

        if ($permission !== '' && !$this->auth->hasPermission($permission)) {
            $this->response->setStatusCode(403);
            $this->render('partials/forbidden', [
                'title' => $this->t('common.access_denied_title', 'Access denied'),
                'message' => $this->t('common.access_denied_message', 'Your user does not have permission to access this resource.'),
            ]);
            $this->response->send();
            exit;
        }
    }

    protected function t(string $key, string $default = '', array $replacements = []): string
    {
        $text = $this->language ? $this->language->get($key, $default) : $default;
        if ($replacements === []) {
            return $text;
        }

        $tokens = [];
        foreach ($replacements as $name => $value) {
            $tokens['{' . $name . '}'] = (string) $value;
        }

        return strtr($text, $tokens);
    }
}
