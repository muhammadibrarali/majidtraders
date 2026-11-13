<?php
/** Blog posts — /admin/blog */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/admin_crud.php';

/** Authors: admin accounts ("a:ID") and team members ("t:ID"). */
$authors = static function (): array {
    $list = [];
    foreach (db()->query('SELECT id, name FROM admin_users WHERE is_active = 1 ORDER BY name') as $a) {
        $list['a:' . $a['id']] = $a['name'] . ' (admin)';
    }
    foreach (db()->query("SELECT id, name, position FROM team_members ORDER BY sort_order, name") as $t) {
        $list['t:' . $t['id']] = $t['name'] . ($t['position'] ? ' — ' . $t['position'] : '');
    }
    return $list;
};

crud_run([
    'key'      => 'blog',
    'table'    => 'blog_posts',
    'singular' => 'post',
    'plural'   => 'posts',
    'label'    => 'Blog',
    'title'    => 'title',
    'image'    => 'cover_image_id',
    'summary'  => 'excerpt',
    'search'   => ['title', 'excerpt', 'tags'],
    'view_url' => '/blog/{slug}',
    'seo'      => true,
    'sortable' => false,
    'order_by' => 'COALESCE(t.published_at, t.created_at) DESC, t.id DESC',
    'page_actions' => '<a class="btn btn--ghost" href="/admin/blog-categories">' . icon_svg('sliders', 18) . '<span>Manage categories</span></a>',
    'filters'  => [
        ['name' => 'category', 'column' => 't.category_id', 'label' => 'All categories',
         'options' => static fn () => options_from('SELECT id AS k, name AS v FROM blog_categories ORDER BY sort_order, name')],
        ['name' => 'featured', 'column' => 't.is_featured', 'label' => 'Featured & normal', 'options' => ['1' => 'Featured only', '0' => 'Not featured']],
    ],
    'list_select' => '(SELECT name FROM blog_categories bc WHERE bc.id = t.category_id) AS category_name,
                      COALESCE((SELECT name FROM team_members tm WHERE tm.id = t.author_team_id),
                               (SELECT name FROM admin_users au WHERE au.id = t.author_id)) AS author_name',
    'list_meta' => static fn (array $r): string => implode(' · ', array_filter([
        $r['published_at'] ? date('j M Y, g:i a', strtotime((string) $r['published_at'])) : 'No date yet',
        (string) $r['category_name'], (string) $r['author_name'],
        $r['reading_minutes'] ? $r['reading_minutes'] . ' min read' : '',
    ])),
    'list_badges' => static fn (array $r): array => array_values(array_filter([
        $r['status'] === 'published' && $r['published_at'] && strtotime((string) $r['published_at']) > time() ? ['Scheduled', 'scheduled'] : null,
        $r['is_featured'] ? ['Featured', 'featured'] : null,
    ])),
    'is_live' => static fn (array $r): bool => $r['status'] === 'published'
        && (!$r['published_at'] || strtotime((string) $r['published_at']) <= time()),
    'validate' => static function (array $data, array $extra, array &$errors): void {
        if ($data['status'] === 'published' && empty($data['body'])) {
            $errors['body'] = 'Please write the post content before publishing (or save it as a Draft).';
        }
    },
    'before_save' => static function (array $data, ?array $existing): array {
        // Reading time: about 200 words per minute.
        $words = str_word_count(strip_tags(str_replace('<', ' <', (string) $data['body'])));
        $data['reading_minutes'] = $words > 0 ? max(1, (int) ceil($words / 200)) : null;
        // Publishing without a date = publish now.
        if ($data['status'] === 'published' && empty($data['published_at'])) {
            $data['published_at'] = date('Y-m-d H:i:s');
        }
        // Tags: "Supply, tenders , supply" -> "Supply, tenders"
        $tags = [];
        foreach (explode(',', (string) $data['tags']) as $t) {
            $t = mb_substr(trim(preg_replace('/\s+/', ' ', $t) ?? ''), 0, 40);
            if ($t !== '' && !in_array(mb_strtolower($t), array_map('mb_strtolower', $tags), true)) {
                $tags[] = $t;
            }
        }
        $data['tags'] = $tags ? mb_substr(implode(', ', array_slice($tags, 0, 10)), 0, 255) : null;
        return $data;
    },
    'fields' => [
        ['name' => 'title', 'type' => 'text', 'label' => 'Post title', 'required' => true, 'max' => 200],
        ['name' => 'slug', 'type' => 'slug', 'label' => 'Web address', 'from' => 'title', 'prefix' => 'majidtraders.org/blog/',
         'help' => 'Made automatically from the title. Changing it after publishing breaks old links.'],
        ['name' => 'excerpt', 'type' => 'textarea', 'label' => 'Excerpt', 'max' => 500, 'rows' => 3, 'counter' => true, 'recommend' => [80, 220],
         'help' => 'Short summary shown on the blog list and when sharing.'],
        ['name' => 'body', 'type' => 'richtext', 'label' => 'Content',
         'help' => 'Use “Image” in the toolbar to add photos from the Media Library. Reading time is calculated automatically.'],
        ['name' => 'cover_image_id', 'type' => 'media', 'label' => 'Cover image', 'accept' => 'image',
         'help' => 'Shown at the top of the post and on the blog list. Landscape, at least 1200 px wide.'],
        ['name' => 'category_id', 'type' => 'select', 'label' => 'Category', 'empty' => '— No category —',
         'options' => static fn () => options_from('SELECT id AS k, name AS v FROM blog_categories ORDER BY sort_order, name')],
        ['name' => 'tags', 'type' => 'text', 'label' => 'Tags', 'max' => 255, 'placeholder' => 'tenders, Mardan, supply',
         'help' => 'Separate tags with commas (up to 10).'],
        // Side column
        ['name' => 'author', 'type' => 'custom', 'side' => true,
         'render' => static function (array $v, ?array $row, array $errors) use ($authors): string {
             $current = $v['author'] ?? (!empty($v['author_team_id']) ? 't:' . $v['author_team_id']
                 : (!empty($v['author_id']) ? 'a:' . $v['author_id'] : 'a:' . (current_admin()['id'] ?? 0)));
             return field_select('author', 'Author', (string) $current, ['' => '— No author —'] + $authors(), [], $errors);
         },
         'collect' => static function (array &$data, array &$extra, array &$errors) use ($authors): void {
             $v = post_str('author');
             $extra['author'] = $v;
             $data['author_id'] = null;
             $data['author_team_id'] = null;
             if ($v !== '' && array_key_exists($v, $authors())) {
                 [$kind, $id] = explode(':', $v);
                 $data[$kind === 't' ? 'author_team_id' : 'author_id'] = (int) $id;
             }
         }],
        ['name' => 'published_at', 'type' => 'datetime', 'label' => 'Publish date', 'side' => true,
         'help' => 'Leave empty to publish immediately. Pick a future date and choose “Published” to schedule the post.'],
        ['name' => 'is_featured', 'type' => 'switch', 'label' => 'Featured post', 'side' => true,
         'help' => 'Highlighted at the top of the blog.'],
    ],
]);
