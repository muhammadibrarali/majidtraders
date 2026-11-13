<?php
/** Services manager — /admin/services (uses the reusable admin manager). */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/admin_crud.php';

crud_run([
    'key'      => 'services',
    'table'    => 'services',
    'singular' => 'service',
    'plural'   => 'services',
    'label'    => 'Services',
    'title'    => 'title',
    'image'    => 'image_id',
    'summary'  => 'excerpt',
    'search'   => ['title', 'excerpt'],
    'view_url' => '/services/{slug}',
    'seo'      => true,
    'fields'   => [
        ['name' => 'title', 'type' => 'text', 'label' => 'Service name', 'required' => true, 'max' => 150,
         'help' => 'For example “General Order Supply”. Shown as the heading on the website.'],
        ['name' => 'slug', 'type' => 'slug', 'label' => 'Web address', 'from' => 'title', 'prefix' => 'majidtraders.org/services/',
         'help' => 'Made automatically from the name. Only lower-case letters, numbers and dashes. Changing it later breaks old links.'],
        ['name' => 'excerpt', 'type' => 'textarea', 'label' => 'Short summary', 'max' => 300, 'rows' => 3, 'counter' => true, 'recommend' => [80, 200],
         'help' => 'One or two sentences shown on the service cards on the home page and services page.'],
        ['name' => 'body', 'type' => 'richtext', 'label' => 'Full description',
         'help' => 'Shown on the service’s own page. Use headings and bullet points to make it easy to read.'],
        ['name' => 'icon', 'type' => 'icon', 'label' => 'Icon', 'help' => 'Small symbol shown on the service card.'],
        ['name' => 'image_id', 'type' => 'media', 'label' => 'Cover image', 'accept' => 'image',
         'help' => 'Large photo at the top of the service page and on cards. Landscape photos (wider than tall) work best.'],
        ['name' => 'gallery', 'type' => 'gallery', 'label' => 'Gallery images (optional)', 'link_table' => 'service_images', 'link_fk' => 'service_id', 'captions' => true,
         'help' => 'Extra photos shown on the service page. Use the arrows to change their order.'],
    ],
]);
