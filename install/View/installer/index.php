<section class="card">
    <h1><?= e($title ?? $t('install.title', 'Instalador')) ?></h1>
    <p class="subtitle"><?= e($t('install.subtitle', 'Configuracao inicial da plataforma estrategica de planejamento de conteudo.')) ?></p>

    <?php if ($message_success): ?>
        <div class="alert success"><?= e($message_success) ?></div>
    <?php endif; ?>

    <?php if ($message_error): ?>
        <div class="alert error"><?= e($message_error) ?></div>
    <?php endif; ?>

    <h2><?= e($t('install.step_environment', '1. Verificacao de ambiente')) ?></h2>
    <table class="table-checks">
        <thead>
            <tr>
                <th><?= e($t('install.table_item', 'Item')) ?></th>
                <th><?= e($t('install.table_current', 'Atual')) ?></th>
                <th><?= e($t('install.table_required', 'Requerido')) ?></th>
                <th><?= e($t('install.table_status', 'Status')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($report as $name => $item): ?>
                <?php $itemLabel = $t('install.report_' . $name, $name); ?>
                <tr>
                    <td><?= e($itemLabel) ?></td>
                    <td><?= e((string) $item['current']) ?></td>
                    <td><?= e((string) $item['required']) ?></td>
                    <td><?= $item['ok'] ? '<span class="ok">' . e($t('install.status_ok', 'OK')) . '</span>' : '<span class="fail">' . e($t('install.status_fail', 'Falha')) . '</span>' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2><?= e($t('install.step_configure', '2. Configurar e instalar')) ?></h2>
    <form method="post" action="<?= e(route_url('index/install')) ?>" class="grid-form">
        <?= csrf_field() ?>
        <?php if (!empty($reinstall_key)): ?>
            <input type="hidden" name="reinstall_key" value="<?= e($reinstall_key) ?>">
        <?php endif; ?>

        <label>
            <?= e($t('install.field_db_host', 'Host do banco')) ?>
            <input type="text" name="db_host" value="<?= e($values['db_host'] ?? '') ?>" required>
        </label>

        <label>
            <?= e($t('install.field_db_port', 'Porta')) ?>
            <input type="number" name="db_port" value="<?= e($values['db_port'] ?? '3306') ?>" required>
        </label>

        <label>
            <?= e($t('install.field_db_name', 'Nome do banco')) ?>
            <input type="text" name="db_name" value="<?= e($values['db_name'] ?? '') ?>" required>
        </label>

        <label>
            <?= e($t('install.field_db_user', 'Usuario do banco')) ?>
            <input type="text" name="db_user" value="<?= e($values['db_user'] ?? '') ?>" required>
        </label>

        <label>
            <?= e($t('install.field_db_pass', 'Senha do banco')) ?>
            <input type="password" name="db_pass" value="<?= e($values['db_pass'] ?? '') ?>">
        </label>

        <label>
            <?= e($t('install.field_admin_name', 'Nome do administrador')) ?>
            <input type="text" name="admin_name" value="<?= e($values['admin_name'] ?? '') ?>" required>
        </label>

        <label>
            <?= e($t('install.field_admin_email', 'Email do administrador')) ?>
            <input type="email" name="admin_email" value="<?= e($values['admin_email'] ?? '') ?>" required>
        </label>

        <label>
            <?= e($t('install.field_admin_password', 'Senha do administrador')) ?>
            <input type="password" name="admin_password" required minlength="8">
        </label>

        <label>
            <?= e($t('install.field_timezone', 'Fuso horario')) ?>
            <input type="text" name="timezone" value="<?= e($values['timezone'] ?? 'America/Sao_Paulo') ?>">
        </label>

        <label>
            <?= e($t('install.field_language', 'Idioma')) ?>
            <?php $selectedLanguage = strtolower((string) ($values['language_code'] ?? 'en-us')); ?>
            <select name="language_code">
                <option value="en-us" <?= $selectedLanguage === 'en-us' ? 'selected' : '' ?>><?= e($t('install.language_en_us', 'English (United States) - en-us')) ?></option>
                <option value="pt-br" <?= $selectedLanguage === 'pt-br' ? 'selected' : '' ?>><?= e($t('install.language_pt_br', 'Portugues (Brasil) - pt-br')) ?></option>
            </select>
        </label>

        <button type="submit" <?= !$all_ok ? 'disabled' : '' ?>><?= e($t('install.button_install_now', 'Instalar agora')) ?></button>
    </form>

    <?php if (!$all_ok): ?>
        <p class="hint"><?= e($t('install.hint_fix_failed_items', 'Corrija os itens com falha antes de instalar.')) ?></p>
    <?php endif; ?>
</section>
