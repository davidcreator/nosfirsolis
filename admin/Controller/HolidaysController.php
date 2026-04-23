<?php

namespace Admin\Controller;

class HolidaysController extends BaseController
{
    public function index(): void
    {
        $this->boot('admin.holidays');
        $model = $this->loader->model('holidays');

        $this->render('holidays/index', [
            'title' => $this->t('holidays.title_index', 'Gestao de Feriados'),
            'holidays' => $model->all([], 'holiday_date ASC'),
        ]);
    }

    public function create(): void
    {
        $this->boot('admin.holidays');

        $this->render('holidays/form', [
            'title' => $this->t('holidays.title_create', 'Novo Feriado'),
            'action' => 'holidays/store',
            'holiday' => null,
            'regions' => $this->loader->model('holiday_regions')->options(),
        ]);
    }

    public function store(): void
    {
        $this->boot('admin.holidays');
        $this->requirePostAndCsrf();

        $date = (string) $this->request->post('holiday_date');
        $this->loader->model('holidays')->create([
            'name' => trim((string) $this->request->post('name')),
            'holiday_date' => $date,
            'month_day' => date('m-d', strtotime($date)),
            'is_fixed' => (int) $this->request->post('is_fixed', 1),
            'is_movable' => (int) $this->request->post('is_movable', 0),
            'movable_rule' => trim((string) $this->request->post('movable_rule')) ?: null,
            'holiday_type' => (string) $this->request->post('holiday_type', 'national'),
            'holiday_region_id' => $this->request->post('holiday_region_id') !== '' ? (int) $this->request->post('holiday_region_id') : null,
            'country_code' => strtoupper(trim((string) $this->request->post('country_code')) ?: 'BR'),
            'state_code' => strtoupper(trim((string) $this->request->post('state_code'))),
            'description' => trim((string) $this->request->post('description')),
            'status' => (int) $this->request->post('status', 1),
        ]);

        flash('success', $this->t('holidays.flash_created', 'Feriado cadastrado com sucesso.'));
        $this->redirectToRoute('holidays/index');
    }

    public function edit(int $id): void
    {
        $this->boot('admin.holidays');
        $model = $this->loader->model('holidays');

        $holiday = $model->find($id);
        if (!$holiday) {
            flash('error', $this->t('holidays.flash_not_found', 'Feriado nao encontrado.'));
            $this->redirectToRoute('holidays/index');
        }

        $this->render('holidays/form', [
            'title' => $this->t('holidays.title_edit', 'Editar Feriado'),
            'action' => 'holidays/update/' . $id,
            'holiday' => $holiday,
            'regions' => $this->loader->model('holiday_regions')->options(),
        ]);
    }

    public function update(int $id): void
    {
        $this->boot('admin.holidays');
        $this->requirePostAndCsrf();

        $date = (string) $this->request->post('holiday_date');
        $this->loader->model('holidays')->updateById($id, [
            'name' => trim((string) $this->request->post('name')),
            'holiday_date' => $date,
            'month_day' => date('m-d', strtotime($date)),
            'is_fixed' => (int) $this->request->post('is_fixed', 1),
            'is_movable' => (int) $this->request->post('is_movable', 0),
            'movable_rule' => trim((string) $this->request->post('movable_rule')) ?: null,
            'holiday_type' => (string) $this->request->post('holiday_type', 'national'),
            'holiday_region_id' => $this->request->post('holiday_region_id') !== '' ? (int) $this->request->post('holiday_region_id') : null,
            'country_code' => strtoupper(trim((string) $this->request->post('country_code')) ?: 'BR'),
            'state_code' => strtoupper(trim((string) $this->request->post('state_code'))),
            'description' => trim((string) $this->request->post('description')),
            'status' => (int) $this->request->post('status', 1),
        ]);

        flash('success', $this->t('holidays.flash_updated', 'Feriado atualizado.'));
        $this->redirectToRoute('holidays/index');
    }

    public function delete(int $id): void
    {
        $this->boot('admin.holidays');
        $this->requirePostAndCsrf();
        $this->loader->model('holidays')->deleteById($id);

        flash('success', $this->t('holidays.flash_deleted', 'Feriado removido.'));
        $this->redirectToRoute('holidays/index');
    }
}
