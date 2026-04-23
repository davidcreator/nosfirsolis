<?php

namespace System\Engine;

class Router
{
    public function __construct(private readonly Registry $registry, private readonly string $area)
    {
    }

    public function dispatch(): void
    {
        $config = $this->registry->get('config');
        $request = $this->registry->get('request');
        $response = $this->registry->get('response');
        $auth = $this->registry->get('auth');
        $language = $this->registry->get('language');

        $route = $request->route();
        $defaultController = $config->get('app.areas.' . $this->area . '.default_controller', 'dashboard');
        $defaultAction = $config->get('app.areas.' . $this->area . '.default_action', 'index');

        if ($route === '') {
            $route = $defaultController . '/' . $defaultAction;
        }

        $normalizedRoute = trim($route, '/');
        $parts = $normalizedRoute === '' ? [] : explode('/', $normalizedRoute);
        $controllerSlug = $parts[0] ?? $defaultController;
        $action = $parts[1] ?? $defaultAction;
        $params = array_slice($parts, 2);

        $publicRoutes = $config->get('routes.public_routes', []);
        $candidateRoute = strtolower($controllerSlug . '/' . $action);

        if ($this->area !== 'install' && !in_array($candidateRoute, $publicRoutes, true) && !$auth->check()) {
            $response->redirect(route_url($config->get('routes.login_redirect', 'auth/login')));
        }

        $controllerClass = ucfirst($this->area) . '\\Controller\\' . $this->studly($controllerSlug) . 'Controller';
        $actionMethod = $this->camel($action);

        if (!class_exists($controllerClass)) {
            $response->setStatusCode(404);
            $response->setOutput($this->translate(
                $language,
                'common.router_controller_not_found',
                'Controller nao encontrada: {controller}',
                ['controller' => $controllerClass]
            ));
            return;
        }

        $controller = new $controllerClass($this->registry);

        if (!method_exists($controller, $actionMethod) || str_starts_with($actionMethod, '__')) {
            $response->setStatusCode(404);
            $response->setOutput($this->translate(
                $language,
                'common.router_action_not_found',
                'Acao nao encontrada: {action}',
                ['action' => $actionMethod]
            ));
            return;
        }

        try {
            $refMethod = new \ReflectionMethod($controller, $actionMethod);
            if (!$refMethod->isPublic() || $refMethod->isStatic()) {
                $response->setStatusCode(404);
                $response->setOutput($this->translate(
                    $language,
                    'common.router_action_not_found',
                    'Acao nao encontrada: {action}',
                    ['action' => $actionMethod]
                ));
                return;
            }
        } catch (\ReflectionException $exception) {
            $response->setStatusCode(404);
            $response->setOutput($this->translate(
                $language,
                'common.router_action_not_found',
                'Acao nao encontrada: {action}',
                ['action' => $actionMethod]
            ));
            return;
        }

        $result = call_user_func_array([$controller, $actionMethod], $params);

        if (is_string($result) && $response->getOutput() === '') {
            $response->setOutput($result);
        }
    }

    private function studly(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', strtolower($value));

        return str_replace(' ', '', ucwords($value));
    }

    private function camel(string $value): string
    {
        $studly = $this->studly($value);

        return lcfirst($studly);
    }

    private function translate(mixed $language, string $key, string $default, array $replacements = []): string
    {
        $text = is_object($language) && method_exists($language, 'get')
            ? (string) $language->get($key, $default)
            : $default;

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
