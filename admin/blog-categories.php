<?php
/** Blog categories — /admin/blog-categories */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/admin_crud.php';

crud_run([
    'key'      => 'blog-categories',
    'menu_key' => 'blog',
    'table'    => 'blog_categories',
    'singular' => 'category',
    'plural'   => 'blog categories',
    'label'    => 'Blog Categories',
    'title'    => 'name',
    'summary'  => 'description',
    'search'   => ['name', 'description'],
    'view_url' => '/blog/category/{slug}',
    'page_actions' => '<a class="btn btn--text" href="/admin/blog">← Blog posts</a>',
    'list_select'  => '(SELECT COUNT(*) FROM blog_posts b WHERE b.category_id = t.id) AS post_count',
    'list_meta'    => static fn (array $r): string => $r['post_count'] . ' post' . ((int) $r['post_count'] === 1 ? '' : 's'),
    'delete_check' => static function (array $row): ?string {
        $stmt = db()->prepare('SELECT COUNT(*) FROM blog_posts WHERE category_id = ?');
        $stmt->execute([$row['id']]);
        $n = (int) $stmt->fetchColumn();
        return $n > 0 ? 'You can’t delete “' . $row['name'] . '” because ' . $n . ' post' . ($n === 1 ? ' uses' : 's use')
            . ' it. Move ' . ($n === 1 ? 'that post' : 'those posts') . ' to another category first.' : null;
    },
    'fields' => [
        ['name' => 'name', 'type' => 'text', 'label' => 'Category name', 'required' => true, 'max' => 100, 'placeholder' => 'Company News'],
        ['name' => 'slug', 'type' => 'slug', 'label' => 'Web address', 'from' => 'name', 'prefix' => 'majidtraders.org/blog/category/'],
        ['name' => 'description', 'type' => 'textarea', 'label' => 'Short description', 'max' => 500, 'rows' => 3, 'counter' => true],
    ],
]);
