<?php
/** Testimonials manager — /admin/testimonials */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/admin_crud.php';

crud_run([
    'key'      => 'testimonials',
    'table'    => 'testimonials',
    'singular' => 'testimonial',
    'plural'   => 'testimonials',
    'label'    => 'Testimonials',
    'title'    => 'author_name',
    'image'    => 'photo_id',
    'summary'  => 'quote',
    'search'   => ['author_name', 'organization', 'quote'],
    'list_meta' => static fn (array $r): string => implode(', ', array_filter([(string) $r['author_position'], (string) $r['organization']])),
    'fields' => [
        ['name' => 'quote', 'type' => 'textarea', 'label' => 'Quote', 'required' => true, 'max' => 1000, 'rows' => 5, 'counter' => true, 'recommend' => [80, 400],
         'help' => 'What the client said about Majid Traders. Short quotes (2–4 sentences) work best.'],
        ['name' => 'author_name', 'type' => 'text', 'label' => 'Person’s name', 'required' => true, 'max' => 120],
        ['name' => 'author_position', 'type' => 'text', 'label' => 'Position', 'max' => 120, 'placeholder' => 'Executive Engineer'],
        ['name' => 'organization', 'type' => 'text', 'label' => 'Organisation', 'max' => 190, 'placeholder' => 'C&W Department, Mardan'],
        ['name' => 'photo_id', 'type' => 'media', 'label' => 'Photo or logo (optional)', 'accept' => 'image',
         'help' => 'A photo of the person or the organisation’s logo.'],
    ],
]);
