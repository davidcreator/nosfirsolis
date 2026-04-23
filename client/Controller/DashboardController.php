<?php

namespace Client\Controller;

class DashboardController extends BaseController
{
    public function index(): void
    {
        $this->boot('client.dashboard');

        $user = $this->auth->user();
        $userId = (int) ($user['id'] ?? 0);

        $overview = $this->loader->model('dashboard')->overview($userId);
        $appName = (string) $this->config->get('app.name', 'Solis');

        $this->render('dashboard/index', [
            'title' => $appName,
            'overview' => $overview,
        ]);
    }
}
