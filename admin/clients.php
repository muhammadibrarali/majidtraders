<?php
/** Clients manager — /admin/clients */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/admin_crud.php';

$types = [
    'federal_government'    => 'Federal Government',
    'provincial_government' => 'Provincial Government',
    'semi_government'       => 'Semi-Government',
    'private'               => 'Private',
    'international'         => 'International',
    'other'                 => 'Other',
];

crud_run([
    'key'      => 'clients',
    'table'    => 'clients',
    'singular' => 'client',
    'plural'   => 'clients',
    'label'    => 'Clients',
    'title'    => 'name',
    'image'    => 'logo_id',
    'summary'  => 'description',
    'search'   => ['name', 'description'],
    'view_url' => '/clients#{slug}',
    'defaults' => ['show_in_marquee' => 1],
    'filters'  => [['name' => 'type', 'column' => 't.department_type', 'label' => 'All types', 'options' => $types]],
    'list_select' => '(SELECT COUNT(*) FROM projects p WHERE p.client_id = t.id) AS project_count',
    'list_meta'   => static fn (array $r): string => ($types[$r['department_type']] ?? 'Other') . ' · '
        . $r['project_count'] . ' project' . ((int) $r['project_count'] === 1 ? '' : 's'),
    'list_badges' => static fn (array $r): array => $r['show_in_marquee'] ? [['In logo marquee', 'featured']] : [],
    'delete_warning' => static function (array $row): ?string {
        $stmt = db()->prepare('SELECT COUNT(*) FROM projects WHERE client_id = ?');
        $stmt->execute([$row['id']]);
        $n = (int) $stmt->fetchColumn();
        return $n > 0 ? $n . ' project' . ($n === 1 ? ' is' : 's are') . ' linked to this client. The projects will be kept, but will no longer show this client.' : null;
    },
    'fields' => [
        ['name' => 'name', 'type' => 'text', 'label' => 'Client name', 'required' => true, 'max' => 190,
         'help' => 'Full official name, e.g. “Communication & Works Department, KP”.'],
        ['name' => 'slug', 'type' => 'slug', 'label' => 'Web address', 'from' => 'name', 'prefix' => 'majidtraders.org/clients#'],
        ['name' => 'department_type', 'type' => 'select', 'label' => 'Type', 'required' => true, 'empty' => '— Choose type —', 'options' => $types],
        ['name' => 'logo_id', 'type' => 'media', 'label' => 'Logo', 'accept' => 'image',
         'help' => 'PNG or WebP with a transparent background looks best in the logo marquee.'],
        ['name' => 'website_url', 'type' => 'url', 'label' => 'Website (optional)', 'placeholder' => 'https://'],
        ['name' => 'description', 'type' => 'textarea', 'label' => 'Short description (optional)', 'max' => 500, 'rows' => 3, 'counter' => true],
        ['name' => 'show_in_marquee', 'type' => 'switch', 'label' => 'Show in logo marquee', 'side' => true, 'default' => true,
         'help' => 'The moving row of client logos on the home page.'],
    ],
]);
