<?php

namespace Admin\Controller;

class CommemorativesController extends BaseController
{
    public function index(): void
    {
        $this->boot('admin.commemoratives');

        $this->render('commemoratives/index', [
            'title' => $this->t('commemoratives.title_index', 'Datas Comemorativas'),
            'items' => $this->loader->model('commemorative_dates')->all([], 'event_date ASC'),
        ]);
    }

    public function create(): void
    {
        $this->boot('admin.commemoratives');

        $this->render('commemoratives/form', [
            'title' => $this->t('commemoratives.title_create', 'Nova Data Comemorativa'),
            'action' => 'commemoratives/store',
            'item' => null,
        ]);
    }

    public function store(): void
    {
        $this->boot('admin.commemoratives');
        $this->requirePostAndCsrf();

        $date = (string) $this->request->post('event_date');
        $this->loader->model('commemorative_dates')->create([
            'name' => trim((string) $this->request->post('name')),
            'event_date' => $date,
            'month_day' => date('m-d', strtotime($date)),
            'recurrence_type' => (string) $this->request->post('recurrence_type', 'yearly'),
            'context_type' => (string) $this->request->post('context_type', 'editorial'),
            'country_code' => strtoupper(trim((string) $this->request->post('country_code'))),
            'description' => trim((string) $this->request->post('description')),
            'status' => (int) $this->request->post('status', 1),
        ]);

        flash('success', $this->t('commemoratives.flash_created', 'Data comemorativa cadastrada.'));
        $this->redirectToRoute('commemoratives/index');
    }

    public function edit(int $id): void
    {
        $this->boot('admin.commemoratives');
        $item = $this->loader->model('commemorative_dates')->find($id);

        if (!$item) {
            flash('error', $this->t('commemoratives.flash_not_found', 'Registro nao encontrado.'));
            $this->redirectToRoute('commemoratives/index');
        }

        $this->render('commemoratives/form', [
            'title' => $this->t('commemoratives.title_edit', 'Editar Data Comemorativa'),
            'action' => 'commemoratives/update/' . $id,
            'item' => $item,
        ]);
    }

    public function update(int $id): void
    {
        $this->boot('admin.commemoratives');
        $this->requirePostAndCsrf();

        $date = (string) $this->request->post('event_date');
        $this->loader->model('commemorative_dates')->updateById($id, [
            'name' => trim((string) $this->request->post('name')),
            'event_date' => $date,
            'month_day' => date('m-d', strtotime($date)),
            'recurrence_type' => (string) $this->request->post('recurrence_type', 'yearly'),
            'context_type' => (string) $this->request->post('context_type', 'editorial'),
            'country_code' => strtoupper(trim((string) $this->request->post('country_code'))),
            'description' => trim((string) $this->request->post('description')),
            'status' => (int) $this->request->post('status', 1),
        ]);

        flash('success', $this->t('commemoratives.flash_updated', 'Data comemorativa atualizada.'));
        $this->redirectToRoute('commemoratives/index');
    }

    public function delete(int $id): void
    {
        $this->boot('admin.commemoratives');
        $this->requirePostAndCsrf();
        $this->loader->model('commemorative_dates')->deleteById($id);

        flash('success', $this->t('commemoratives.flash_deleted', 'Data comemorativa removida.'));
        $this->redirectToRoute('commemoratives/index');
    }
}
