<?php
/** Project categories — /admin/project-categories */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/admin_crud.php';

$usage = static function (array $row): int {
    $stmt = db()->prepare('SELECT COUNT(*) FROM projects WHERE category_id = ?');
    $stmt->execute([$row['id']]);
    return (int) $stmt->fetchColumn();
};

crud_run([
    'key'      => 'project-categories',
    'menu_key' => 'projects',
    'table'    => 'project_categories',
    'singular' => 'category',
    'plural'   => 'project categories',
    'label'    => 'Project Categories',
    'title'    => 'name',
    'summary'  => 'description',
    'search'   => ['name', 'description'],
    'view_url' => '/projects/category/{slug}',
    'page_actions' => '<a class="btn btn--text" href="/admin/projects">← Projects</a>',
    'list_select'  => '(SELECT COUNT(*) FROM projects p WHERE p.category_id = t.id) AS project_count',
    'list_meta'    => static fn (array $r): string => $r['project_count'] . ' project' . ((int) $r['project_count'] === 1 ? '' : 's'),
    'delete_check' => static function (array $row) use ($usage): ?string {
        $n = $usage($row);
        return $n > 0 ? 'You can’t delete “' . $row['name'] . '” because ' . $n . ' project' . ($n === 1 ? ' uses' : 's use')
            . ' it. Move ' . ($n === 1 ? 'that project' : 'those projects') . ' to another category first.' : null;
    },
    'fields' => [
        ['name' => 'name', 'type' => 'text', 'label' => 'Category name', 'required' => true, 'max' => 100,
         'help' => 'For example “Construction & Renovation”.'],
        ['name' => 'slug', 'type' => 'slug', 'label' => 'Web address', 'from' => 'name', 'prefix' => 'majidtraders.org/projects/category/'],
        ['name' => 'description', 'type' => 'textarea', 'label' => 'Short description', 'max' => 500, 'rows' => 3, 'counter' => true,
         'help' => 'Optional. Shown at the top of this category’s page.'],
    ],
]);
