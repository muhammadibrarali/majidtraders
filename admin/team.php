<?php
/** Team manager — /admin/team */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/admin_crud.php';

$departments = array_combine($d = ['Management', 'Engineering', 'Procurement', 'Accounts', 'Field Staff', 'Administration', 'Sales & Marketing', 'Logistics'], $d);

crud_run([
    'key'      => 'team',
    'table'    => 'team_members',
    'singular' => 'team member',
    'plural'   => 'team members',
    'label'    => 'Team',
    'title'    => 'name',
    'image'    => 'photo_id',
    'summary'  => 'bio',
    'search'   => ['name', 'position', 'department'],
    'view_url' => '/team#{slug}',
    'status_labels' => ['draft' => 'Hidden', 'published' => 'Shown on website'],
    'status_help'   => 'Choose “Shown on website” to show this person on the Team page.',
    'filters'  => [['name' => 'department', 'column' => 't.department', 'label' => 'All departments', 'options' => $departments]],
    'list_meta' => static fn (array $r): string => implode(' · ', array_filter([(string) $r['position'], (string) $r['department']])),
    'validate'  => static function (array $data, array $extra, array &$errors): void {
        if (!empty($data['linkedin_url']) && !preg_match('#^https://([a-z]{2,3}\.)?linkedin\.com/#i', $data['linkedin_url'])) {
            $errors['linkedin_url'] = 'Please paste a LinkedIn profile link (it starts with https://www.linkedin.com/in/…).';
        }
    },
    'fields' => [
        ['name' => 'name', 'type' => 'text', 'label' => 'Full name', 'required' => true, 'max' => 120],
        ['name' => 'slug', 'type' => 'slug', 'label' => 'Web address', 'from' => 'name', 'prefix' => 'majidtraders.org/team#'],
        ['name' => 'position', 'type' => 'text', 'label' => 'Position', 'max' => 120, 'placeholder' => 'Project Engineer'],
        ['name' => 'department', 'type' => 'select', 'label' => 'Department', 'empty' => '— Choose department —', 'options' => $departments],
        ['name' => 'bio', 'type' => 'textarea', 'label' => 'Short bio', 'max' => 600, 'rows' => 4, 'counter' => true,
         'help' => 'Two or three sentences about experience and role. Please do not enter personal phone numbers or home addresses — they are never stored on this website.'],
        ['name' => 'photo_id', 'type' => 'media', 'label' => 'Photo', 'accept' => 'image',
         'help' => 'A professional head-and-shoulders photo. Square or portrait photos work best.'],
        ['name' => 'linkedin_url', 'type' => 'url', 'label' => 'LinkedIn profile (optional)', 'placeholder' => 'https://www.linkedin.com/in/…'],
    ],
]);
