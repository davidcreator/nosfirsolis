<?php

namespace Admin\Controller;

class CampaignsController extends BaseController
{
    public function index(): void
    {
        $this->boot('admin.campaigns');

        $this->render('campaigns/index', [
            'title' => $this->t('campaigns.title_index', 'Campanhas'),
            'items' => $this->loader->model('campaigns')->all([], 'start_date ASC'),
        ]);
    }

    public function create(): void
    {
        $this->boot('admin.campaigns');

        $this->render('campaigns/form', [
            'title' => $this->t('campaigns.title_create', 'Nova Campanha'),
            'action' => 'campaigns/store',
            'item' => null,
        ]);
    }

    public function store(): void
    {
        $this->boot('admin.campaigns');
        $this->requirePostAndCsrf();

        $this->loader->model('campaigns')->create([
            'name' => trim((string) $this->request->post('name')),
            'description' => trim((string) $this->request->post('description')),
            'objective' => trim((string) $this->request->post('objective')),
            'start_date' => $this->request->post('start_date') ?: null,
            'end_date' => $this->request->post('end_date') ?: null,
            'status' => (string) $this->request->post('status', 'planned'),
        ]);

        flash('success', $this->t('campaigns.flash_created', 'Campanha cadastrada.'));
        $this->redirectToRoute('campaigns/index');
    }

    public function edit(int $id): void
    {
        $this->boot('admin.campaigns');
        $item = $this->loader->model('campaigns')->find($id);

        if (!$item) {
            flash('error', $this->t('campaigns.flash_not_found', 'Campanha não encontrada.'));
            $this->redirectToRoute('campaigns/index');
        }

        $this->render('campaigns/form', [
            'title' => $this->t('campaigns.title_edit', 'Editar Campanha'),
            'action' => 'campaigns/update/' . $id,
            'item' => $item,
        ]);
    }

    public function update(int $id): void
    {
        $this->boot('admin.campaigns');
        $this->requirePostAndCsrf();

        $this->loader->model('campaigns')->updateById($id, [
            'name' => trim((string) $this->request->post('name')),
            'description' => trim((string) $this->request->post('description')),
            'objective' => trim((string) $this->request->post('objective')),
            'start_date' => $this->request->post('start_date') ?: null,
            'end_date' => $this->request->post('end_date') ?: null,
            'status' => (string) $this->request->post('status', 'planned'),
        ]);

        flash('success', $this->t('campaigns.flash_updated', 'Campanha atualizada.'));
        $this->redirectToRoute('campaigns/index');
    }

    public function delete(int $id): void
    {
        $this->boot('admin.campaigns');
        $this->requirePostAndCsrf();
        $this->loader->model('campaigns')->deleteById($id);

        flash('success', $this->t('campaigns.flash_deleted', 'Campanha removida.'));
        $this->redirectToRoute('campaigns/index');
    }
}
