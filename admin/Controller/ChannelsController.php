<?php

namespace Admin\Controller;

class ChannelsController extends BaseController
{
    public function index(): void
    {
        $this->boot('admin.channels');

        $this->render('channels/index', [
            'title' => $this->t('channels.title_index', 'Canais e Plataformas'),
            'items' => $this->loader->model('content_platforms')->all([], 'platform_type ASC, name ASC'),
        ]);
    }

    public function create(): void
    {
        $this->boot('admin.channels');

        $this->render('channels/form', [
            'title' => $this->t('channels.title_create', 'Novo Canal'),
            'action' => 'channels/store',
            'item' => null,
        ]);
    }

    public function store(): void
    {
        $this->boot('admin.channels');
        $this->requirePostAndCsrf();

        $name = trim((string) $this->request->post('name'));

        $this->loader->model('content_platforms')->create([
            'name' => $name,
            'slug' => $this->slugify($name),
            'platform_type' => (string) $this->request->post('platform_type', 'social'),
            'source' => trim((string) $this->request->post('source', 'manual')),
            'status' => (int) $this->request->post('status', 1),
        ]);

        flash('success', $this->t('channels.flash_created', 'Canal cadastrado com sucesso.'));
        $this->redirectToRoute('channels/index');
    }

    public function edit(int $id): void
    {
        $this->boot('admin.channels');
        $item = $this->loader->model('content_platforms')->find($id);

        if (!$item) {
            flash('error', $this->t('channels.flash_not_found', 'Canal não encontrado.'));
            $this->redirectToRoute('channels/index');
        }

        $this->render('channels/form', [
            'title' => $this->t('channels.title_edit', 'Editar Canal'),
            'action' => 'channels/update/' . $id,
            'item' => $item,
        ]);
    }

    public function update(int $id): void
    {
        $this->boot('admin.channels');
        $this->requirePostAndCsrf();

        $name = trim((string) $this->request->post('name'));

        $this->loader->model('content_platforms')->updateById($id, [
            'name' => $name,
            'slug' => $this->slugify($name),
            'platform_type' => (string) $this->request->post('platform_type', 'social'),
            'source' => trim((string) $this->request->post('source', 'manual')),
            'status' => (int) $this->request->post('status', 1),
        ]);

        flash('success', $this->t('channels.flash_updated', 'Canal atualizado.'));
        $this->redirectToRoute('channels/index');
    }

    public function delete(int $id): void
    {
        $this->boot('admin.channels');
        $this->requirePostAndCsrf();
        $this->loader->model('content_platforms')->deleteById($id);

        flash('success', $this->t('channels.flash_deleted', 'Canal removido.'));
        $this->redirectToRoute('channels/index');
    }
}
