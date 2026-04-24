<?php

namespace Admin\Controller;

class UsersController extends BaseController
{
    public function index(): void
    {
        $this->boot('admin.users');

        $groupsModel = $this->loader->model('user_groups');
        $groupsModel->ensureHierarchySchema();

        $currentUserId = (int) ($this->auth->user()['id'] ?? 0);
        $currentHierarchyLevel = $groupsModel->hierarchyLevelByUser($currentUserId);

        $groups = $groupsModel->optionsForHierarchy($currentHierarchyLevel);
        $allGroups = $groupsModel->allWithHierarchy();
        $manageableGroups = array_values(array_filter(
            $allGroups,
            static fn (array $group): bool => (int) ($group['hierarchy_level'] ?? 50) >= $currentHierarchyLevel
        ));

        $this->render('users/index', [
            'title' => $this->t('users.title_index', 'Usuários e Hierarquia'),
            'users' => $this->loader->model('users')->allWithGroup(),
            'groups' => $groups,
            'hierarchy_groups' => $manageableGroups,
            'current_hierarchy_level' => $currentHierarchyLevel,
        ]);
    }

    public function store(): void
    {
        $this->boot('admin.users');
        $this->requirePostAndCsrf();

        $groupsModel = $this->loader->model('user_groups');
        $groupsModel->ensureHierarchySchema();

        $currentUserId = (int) ($this->auth->user()['id'] ?? 0);
        $currentHierarchyLevel = $groupsModel->hierarchyLevelByUser($currentUserId);

        $groupId = (int) $this->request->post('user_group_id');
        $targetGroup = $groupsModel->find($groupId);
        if (!$targetGroup) {
            flash('error', $this->t('users.flash_invalid_group', 'Grupo de usuário inválido.'));
            $this->redirectToRoute('users/index');
        }

        $targetHierarchyLevel = max(1, min(999, (int) ($targetGroup['hierarchy_level'] ?? 50)));
        if ($targetHierarchyLevel < $currentHierarchyLevel) {
            flash('error', $this->t('users.flash_group_above_hierarchy', 'Você não pode criar usuário em um grupo acima do seu nível hierárquico.'));
            $this->redirectToRoute('users/index');
        }

        $name = trim((string) $this->request->post('name'));
        $email = strtolower(trim((string) $this->request->post('email')));
        $password = (string) $this->request->post('password');

        if ($name === '' || $email === '' || $password === '') {
            flash('error', $this->t('users.flash_required_fields', 'Nome, email e senha são obrigatórios.'));
            $this->redirectToRoute('users/index');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', $this->t('users.flash_invalid_email', 'Informe um email válido.'));
            $this->redirectToRoute('users/index');
        }

        if (strlen($password) < 8) {
            flash('error', $this->t('users.flash_password_min_length', 'A senha deve ter no mínimo 8 caracteres.'));
            $this->redirectToRoute('users/index');
        }

        $existing = $this->db->fetch('SELECT id FROM users WHERE email = :email LIMIT 1', ['email' => $email]);
        if ($existing) {
            flash('error', $this->t('users.flash_email_exists', 'Já existe um usuário com este email.'));
            $this->redirectToRoute('users/index');
        }

        $this->loader->model('users')->create([
            'user_group_id' => $groupId,
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'status' => (int) $this->request->post('status', 1),
        ]);

        flash('success', $this->t('users.flash_created', 'Usuário criado.'));
        $this->redirectToRoute('users/index');
    }

    public function saveHierarchy(): void
    {
        $this->boot('admin.users');
        $this->requirePostAndCsrf();

        $groupsModel = $this->loader->model('user_groups');
        $groupsModel->ensureHierarchySchema();

        $currentUserId = (int) ($this->auth->user()['id'] ?? 0);
        $currentHierarchyLevel = $groupsModel->hierarchyLevelByUser($currentUserId);

        $payload = (array) $this->request->post('hierarchy_level', []);
        if (empty($payload)) {
            flash('error', $this->t('users.flash_no_hierarchy_payload', 'Nenhum nível hierárquico foi enviado para atualização.'));
            $this->redirectToRoute('users/index');
        }

        $updated = 0;
        $blocked = 0;

        foreach ($payload as $groupIdRaw => $levelRaw) {
            $groupIdString = (string) $groupIdRaw;
            if (!ctype_digit($groupIdString)) {
                continue;
            }

            $groupId = (int) $groupIdString;
            if ($groupId <= 0) {
                continue;
            }

            $group = $groupsModel->find($groupId);
            if (!$group) {
                continue;
            }

            $currentGroupLevel = max(1, min(999, (int) ($group['hierarchy_level'] ?? 50)));
            if ($currentGroupLevel < $currentHierarchyLevel) {
                $blocked++;
                continue;
            }

            $levelString = trim((string) $levelRaw);
            if ($levelString === '' || !ctype_digit($levelString)) {
                continue;
            }

            $newLevel = max(1, min(999, (int) $levelString));
            if ($newLevel < $currentHierarchyLevel) {
                $blocked++;
                continue;
            }

            if ($newLevel === $currentGroupLevel) {
                continue;
            }

            if ($groupsModel->updateHierarchyLevel($groupId, $newLevel) > 0) {
                $updated++;
            }
        }

        if ($updated > 0) {
            $message = $this->t(
                'users.flash_hierarchy_updated',
                'Níveis hierárquicos atualizados: {count}.',
                ['count' => $updated]
            );
            if ($blocked > 0) {
                $message .= ' ' . $this->t(
                    'users.flash_hierarchy_blocked',
                    'Itens bloqueados por permissão: {count}.',
                    ['count' => $blocked]
                );
            }
            flash('success', $message);
            $this->redirectToRoute('users/index');
        }

        if ($blocked > 0) {
            flash('error', $this->t('users.flash_hierarchy_update_denied', 'Não foi possível atualizar. Alguns níveis estão acima da sua permissão.'));
            $this->redirectToRoute('users/index');
        }

        flash('success', $this->t('users.flash_hierarchy_no_changes', 'Nenhuma alteração de hierarquia foi necessária.'));
        $this->redirectToRoute('users/index');
    }
}
