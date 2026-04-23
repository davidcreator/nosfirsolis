<?php

namespace Admin\Controller;

class SuggestionsController extends BaseController
{
    public function index(): void
    {
        $this->boot('admin.suggestions');

        $this->render('suggestions/index', [
            'title' => $this->t('suggestions.title_index', 'Sugestoes Estrategicas'),
            'items' => $this->loader->model('content_suggestions')->allDetailed(),
        ]);
    }

    public function create(): void
    {
        $this->boot('admin.suggestions');

        $this->render('suggestions/form', [
            'title' => $this->t('suggestions.title_create', 'Nova Sugestao'),
            'action' => 'suggestions/store',
            'item' => null,
            'selected_channels' => [],
            'categories' => $this->loader->model('content_categories')->options(),
            'pillars' => $this->loader->model('content_pillars')->options(),
            'objectives' => $this->loader->model('content_objectives')->options(),
            'campaigns' => $this->loader->model('campaigns')->options(),
            'platforms' => $this->loader->model('content_platforms')->options(),
        ]);
    }

    public function store(): void
    {
        $this->boot('admin.suggestions');
        $this->requirePostAndCsrf();

        $model = $this->loader->model('content_suggestions');
        $date = (string) $this->request->post('suggestion_date');
        $id = $model->create([
            'title' => trim((string) $this->request->post('title')),
            'description' => trim((string) $this->request->post('description')),
            'suggestion_date' => $date,
            'month_day' => date('m-d', strtotime($date)),
            'is_recurring' => (int) $this->request->post('is_recurring', 1),
            'recurrence_type' => (string) $this->request->post('recurrence_type', 'yearly'),
            'content_category_id' => $this->request->post('content_category_id') !== '' ? (int) $this->request->post('content_category_id') : null,
            'content_pillar_id' => $this->request->post('content_pillar_id') !== '' ? (int) $this->request->post('content_pillar_id') : null,
            'content_objective_id' => $this->request->post('content_objective_id') !== '' ? (int) $this->request->post('content_objective_id') : null,
            'campaign_id' => $this->request->post('campaign_id') !== '' ? (int) $this->request->post('campaign_id') : null,
            'format_type' => trim((string) $this->request->post('format_type')),
            'context_type' => (string) $this->request->post('context_type', 'editorial'),
            'channel_priority' => trim((string) $this->request->post('channel_priority')),
            'status' => (int) $this->request->post('status', 1),
        ]);

        $channels = $this->request->post('channels', []);
        if (is_array($channels)) {
            $model->setChannels($id, array_map('intval', $channels));
        }

        flash('success', $this->t('suggestions.flash_created', 'Sugestao cadastrada.'));
        $this->redirectToRoute('suggestions/index');
    }

    public function edit(int $id): void
    {
        $this->boot('admin.suggestions');
        $model = $this->loader->model('content_suggestions');
        $item = $model->find($id);

        if (!$item) {
            flash('error', $this->t('suggestions.flash_not_found', 'Sugestao nao encontrada.'));
            $this->redirectToRoute('suggestions/index');
        }

        $this->render('suggestions/form', [
            'title' => $this->t('suggestions.title_edit', 'Editar Sugestao'),
            'action' => 'suggestions/update/' . $id,
            'item' => $item,
            'selected_channels' => $model->getChannels($id),
            'categories' => $this->loader->model('content_categories')->options(),
            'pillars' => $this->loader->model('content_pillars')->options(),
            'objectives' => $this->loader->model('content_objectives')->options(),
            'campaigns' => $this->loader->model('campaigns')->options(),
            'platforms' => $this->loader->model('content_platforms')->options(),
        ]);
    }

    public function update(int $id): void
    {
        $this->boot('admin.suggestions');
        $this->requirePostAndCsrf();

        $model = $this->loader->model('content_suggestions');
        $date = (string) $this->request->post('suggestion_date');
        $model->updateById($id, [
            'title' => trim((string) $this->request->post('title')),
            'description' => trim((string) $this->request->post('description')),
            'suggestion_date' => $date,
            'month_day' => date('m-d', strtotime($date)),
            'is_recurring' => (int) $this->request->post('is_recurring', 1),
            'recurrence_type' => (string) $this->request->post('recurrence_type', 'yearly'),
            'content_category_id' => $this->request->post('content_category_id') !== '' ? (int) $this->request->post('content_category_id') : null,
            'content_pillar_id' => $this->request->post('content_pillar_id') !== '' ? (int) $this->request->post('content_pillar_id') : null,
            'content_objective_id' => $this->request->post('content_objective_id') !== '' ? (int) $this->request->post('content_objective_id') : null,
            'campaign_id' => $this->request->post('campaign_id') !== '' ? (int) $this->request->post('campaign_id') : null,
            'format_type' => trim((string) $this->request->post('format_type')),
            'context_type' => (string) $this->request->post('context_type', 'editorial'),
            'channel_priority' => trim((string) $this->request->post('channel_priority')),
            'status' => (int) $this->request->post('status', 1),
        ]);

        $channels = $this->request->post('channels', []);
        $model->setChannels($id, is_array($channels) ? array_map('intval', $channels) : []);

        flash('success', $this->t('suggestions.flash_updated', 'Sugestao atualizada.'));
        $this->redirectToRoute('suggestions/index');
    }

    public function delete(int $id): void
    {
        $this->boot('admin.suggestions');
        $this->requirePostAndCsrf();
        $this->loader->model('content_suggestions')->deleteById($id);

        flash('success', $this->t('suggestions.flash_deleted', 'Sugestao removida.'));
        $this->redirectToRoute('suggestions/index');
    }
}
