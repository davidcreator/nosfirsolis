<?php

namespace Admin\Controller;

class DashboardController extends BaseController
{
    public function index(): void
    {
        $this->boot('admin.dashboard');

        $summary = [
            'users' => $this->loader->model('users')->count(),
            'holidays' => $this->loader->model('holidays')->count(),
            'commemoratives' => $this->loader->model('commemorative_dates')->count(),
            'suggestions' => $this->loader->model('content_suggestions')->count(),
            'campaigns' => $this->loader->model('campaigns')->count(),
            'platforms' => $this->loader->model('content_platforms')->count(),
        ];

        $recentSuggestions = $this->loader->model('content_suggestions')->allDetailed();
        $appName = (string) $this->config->get('app.name', 'Solis');

        $this->render('dashboard/index', [
            'title' => $this->t('dashboard.title_index', '{app} | Admin', ['app' => $appName]),
            'summary' => $summary,
            'recent_suggestions' => array_slice($recentSuggestions, 0, 8),
        ]);
    }
}
