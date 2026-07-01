<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/layout.php';

require_admin();

function admin_column_names(array $columnsMeta, array $rows): array
{
    if ($columnsMeta) {
        return array_map(static fn(array $column): string => (string)$column['COLUMN_NAME'], $columnsMeta);
    }

    return $rows ? array_keys($rows[0]) : [];
}

function admin_enum_options(string $columnType): array
{
    if (!str_starts_with($columnType, 'enum(')) {
        return [];
    }

    preg_match_all("/'((?:''|[^'])*)'/", $columnType, $matches);

    return array_map(static fn(string $value): string => str_replace("''", "'", $value), $matches[1] ?? []);
}

function admin_input_type(array $column): string
{
    $type = strtolower((string)$column['DATA_TYPE']);

    return match ($type) {
        'int', 'bigint', 'smallint', 'mediumint', 'tinyint', 'decimal', 'float', 'double' => 'number',
        'date' => 'date',
        'datetime', 'timestamp' => 'datetime-local',
        default => 'text',
    };
}

function admin_field_value(array $column, ?array $row): string
{
    $name = (string)$column['COLUMN_NAME'];

    if (!$row || !array_key_exists($name, $row) || $row[$name] === null) {
        return '';
    }

    $value = (string)$row[$name];
    $type = strtolower((string)$column['DATA_TYPE']);

    if (in_array($type, ['datetime', 'timestamp'], true)) {
        return str_replace(' ', 'T', substr($value, 0, 16));
    }

    return $value;
}

function admin_render_form(string $table, array $columnsMeta, ?array $row, string $mode, ?string $primaryKey): void
{
    $isEdit = $mode === 'edit';
    $action = $isEdit ? 'update' : 'create';
    $title = $isEdit ? 'Изменение записи' : 'Добавление записи';
    ?>
    <div class="admin-form-card">
        <h2><?= e($title) ?></h2>
        <form class="admin-form" method="post" action="/admin/table.php?table=<?= urlencode($table) ?>">
            <input type="hidden" name="admin_action" value="<?= e($action) ?>">
            <?php if ($isEdit && $primaryKey && $row): ?>
                <input type="hidden" name="id" value="<?= e($row[$primaryKey] ?? '') ?>">
            <?php endif; ?>

            <div class="admin-form-grid">
                <?php foreach ($columnsMeta as $column): ?>
                    <?php
                    $name = (string)$column['COLUMN_NAME'];
                    $type = strtolower((string)$column['DATA_TYPE']);
                    $columnType = strtolower((string)$column['COLUMN_TYPE']);
                    $extra = strtolower((string)($column['EXTRA'] ?? ''));
                    $isPrimary = ($column['COLUMN_KEY'] ?? '') === 'PRI';
                    $isAuto = str_contains($extra, 'auto_increment');
                    $isReadonly = $isPrimary || $isAuto;
                    $isTextarea = $type === 'text' || str_contains($name, 'description') || str_contains($name, 'address') || str_contains($name, 'text');
                    $enumOptions = admin_enum_options($columnType);
                    $required = !$isReadonly && ($column['IS_NULLABLE'] ?? 'NO') === 'NO' && $column['COLUMN_DEFAULT'] === null && $name !== 'password_hash';
                    $value = $name === 'password_hash' && $isEdit ? '' : admin_field_value($column, $row);
                    ?>
                    <label class="<?= $isTextarea ? 'wide' : '' ?>">
                        <span><?= e($name) ?><?= $isReadonly ? ' (PK)' : '' ?></span>
                        <?php if ($enumOptions): ?>
                            <select class="input" name="<?= e($name) ?>" <?= $isReadonly ? 'disabled' : '' ?> <?= $required ? 'required' : '' ?>>
                                <?php foreach ($enumOptions as $option): ?>
                                    <option value="<?= e($option) ?>" <?= $value === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($isTextarea): ?>
                            <textarea class="textarea" name="<?= e($name) ?>" rows="4" <?= $isReadonly ? 'readonly' : '' ?> <?= $required ? 'required' : '' ?>><?= e($value) ?></textarea>
                        <?php else: ?>
                            <input
                                class="input"
                                type="<?= e(admin_input_type($column)) ?>"
                                name="<?= e($name) ?>"
                                value="<?= e($value) ?>"
                                <?= admin_input_type($column) === 'number' ? 'step="any"' : '' ?>
                                <?= $isReadonly ? 'readonly' : '' ?>
                                <?= $required ? 'required' : '' ?>
                                <?= $name === 'password_hash' && $isEdit ? 'placeholder="Оставьте пустым, чтобы не менять пароль"' : '' ?>
                            >
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="admin-actions">
                <button class="btn" type="submit"><?= $isEdit ? 'Сохранить изменения' : 'Добавить запись' ?></button>
                <a class="chip" href="/admin/table.php?table=<?= urlencode($table) ?>">Отмена</a>
            </div>
        </form>
    </div>
    <?php
}

$table = (string)($_GET['table'] ?? '');
$allowed = spice_admin_is_allowed_object($table);
$isBaseTable = spice_admin_is_base_table($table);
$columnsMeta = $allowed ? spice_admin_table_columns($table) : [];
$primaryKey = $allowed ? spice_admin_primary_key($table) : null;
$error = null;
$notice = null;

if ($allowed && $isBaseTable && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminAction = (string)($_POST['admin_action'] ?? '');

    try {
        if ($adminAction === 'create') {
            $data = spice_admin_normalize_form_data($table, $_POST, true);
            spice_admin_insert_row($table, $data);
            header('Location: /admin/table.php?table=' . urlencode($table) . '&saved=created');
            exit;
        }

        if ($adminAction === 'update' && $primaryKey) {
            $data = spice_admin_normalize_form_data($table, $_POST, false);
            spice_admin_update_row($table, $primaryKey, (string)($_POST['id'] ?? ''), $data);
            header('Location: /admin/table.php?table=' . urlencode($table) . '&saved=updated');
            exit;
        }

        if ($adminAction === 'delete' && $primaryKey) {
            spice_admin_delete_row($table, $primaryKey, (string)($_POST['id'] ?? ''));
            header('Location: /admin/table.php?table=' . urlencode($table) . '&saved=deleted');
            exit;
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$saved = (string)($_GET['saved'] ?? '');
if ($saved === 'created') {
    $notice = 'Запись добавлена.';
} elseif ($saved === 'updated') {
    $notice = 'Запись изменена.';
} elseif ($saved === 'deleted') {
    $notice = 'Запись удалена.';
}

$rows = $allowed ? spice_admin_table_rows($table) : [];
$columns = admin_column_names($columnsMeta, $rows);
$action = (string)($_GET['action'] ?? '');
$editRow = null;

if ($allowed && $isBaseTable && $action === 'edit' && $primaryKey) {
    $editRow = spice_admin_row($table, $primaryKey, (string)($_GET['id'] ?? ''));
    if (!$editRow) {
        $error = 'Запись для изменения не найдена.';
        $action = '';
    }
}

page_start($allowed ? 'Таблица ' . $table : 'Таблица не найдена', 'admin');
?>

<section>
    <a class="chip" href="/admin/">Назад к админке</a>

    <?php if (!$allowed): ?>
        <h1 class="page-title">Таблица не найдена</h1>
        <p class="lead">Запрошенная таблица не входит в список разрешённых для просмотра.</p>
    <?php else: ?>
        <div class="admin-title-row">
            <div>
                <h1 class="page-title"><?= e($table) ?></h1>
                <p class="lead">
                    <?= $isBaseTable
                        ? 'Первые 100 строк таблицы. Для обычных таблиц доступны добавление, изменение и удаление данных.'
                        : 'Представление открыто только для просмотра, данные формируются запросом к базе.' ?>
                </p>
            </div>
            <?php if ($isBaseTable): ?>
                <a class="btn" href="/admin/table.php?table=<?= urlencode($table) ?>&action=new">Добавить запись</a>
            <?php endif; ?>
        </div>

        <?php if ($notice): ?>
            <div class="message success"><?= e($notice) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error">Ошибка: <?= e($error) ?></div>
        <?php endif; ?>

        <?php if (!$isBaseTable): ?>
            <div class="message">VIEW: просмотр без редактирования.</div>
        <?php endif; ?>

        <?php if ($isBaseTable && $action === 'new'): ?>
            <?php admin_render_form($table, $columnsMeta, null, 'new', $primaryKey); ?>
        <?php elseif ($isBaseTable && $action === 'edit' && $editRow): ?>
            <?php admin_render_form($table, $columnsMeta, $editRow, 'edit', $primaryKey); ?>
        <?php endif; ?>

        <?php if (!$columns): ?>
            <div class="empty-state">Нет данных для отображения или MySQL сейчас недоступна.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <?php if ($isBaseTable && $primaryKey): ?>
                                <th>Действия</th>
                            <?php endif; ?>
                            <?php foreach ($columns as $column): ?>
                                <th><?= e($column) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <?php if ($isBaseTable && $primaryKey): ?>
                                    <td class="row-actions">
                                        <a class="chip chip-small" href="/admin/table.php?table=<?= urlencode($table) ?>&action=edit&id=<?= urlencode((string)$row[$primaryKey]) ?>">Изменить</a>
                                        <form class="inline-form" method="post" action="/admin/table.php?table=<?= urlencode($table) ?>" onsubmit="return confirm('Удалить запись и связанные с ней данные?');">
                                            <input type="hidden" name="admin_action" value="delete">
                                            <input type="hidden" name="id" value="<?= e($row[$primaryKey]) ?>">
                                            <button class="link-danger" type="submit">Удалить</button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                                <?php foreach ($columns as $column): ?>
                                    <td><?= e($row[$column] ?? '') ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php page_end(); ?>
