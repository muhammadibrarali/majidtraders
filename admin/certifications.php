<?php
/** Certifications & registrations — /admin/certifications */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/admin_crud.php';

crud_run([
    'key'      => 'certifications',
    'table'    => 'certifications',
    'singular' => 'certificate',
    'plural'   => 'certificates',
    'label'    => 'Certifications & Registrations',
    'title'    => 'title',
    'image'    => 'document_id',
    'search'   => ['title', 'issuing_authority', 'registration_number', 'grade'],
    'view_url' => '/certifications#{slug}',
    'defaults' => ['show_number' => 1],
    'list_meta' => static fn (array $r): string => implode(' · ', array_filter([
        (string) $r['issuing_authority'],
        $r['registration_number'] ? 'No. ' . $r['registration_number'] : '',
        $r['grade'] ? 'Grade ' . $r['grade'] : '',
        $r['expiry_date'] ? 'Expires ' . date('j M Y', strtotime((string) $r['expiry_date'])) : '',
    ])),
    'list_badges' => static fn (array $r): array => cert_expiry_badge($r['expiry_date']),
    'validate' => static function (array $data, array $extra, array &$errors): void {
        if (!empty($data['issue_date']) && !empty($data['expiry_date']) && !isset($errors['issue_date']) && !isset($errors['expiry_date'])
            && $data['expiry_date'] < $data['issue_date']) {
            $errors['expiry_date'] = 'The expiry date is before the issue date. Please check both dates.';
        }
    },
    'fields' => [
        ['name' => 'title', 'type' => 'text', 'label' => 'Name', 'required' => true, 'max' => 190,
         'help' => 'For example “PEC Registration”, “FBR NTN”, “KPPRA Registration”.'],
        ['name' => 'slug', 'type' => 'slug', 'label' => 'Web address', 'from' => 'title', 'prefix' => 'majidtraders.org/certifications#'],
        ['name' => 'issuing_authority', 'type' => 'text', 'label' => 'Issuing body', 'max' => 190, 'placeholder' => 'Pakistan Engineering Council'],
        ['name' => 'registration_number', 'type' => 'text', 'label' => 'Registration number', 'max' => 100],
        ['name' => 'grade', 'type' => 'text', 'label' => 'Category / grade', 'max' => 60, 'placeholder' => 'C-3',
         'help' => 'Optional, e.g. PEC category “C-3”.'],
        ['name' => 'issue_date', 'type' => 'date', 'label' => 'Issue date'],
        ['name' => 'expiry_date', 'type' => 'date', 'label' => 'Expiry date',
         'help' => 'Leave empty if it never expires. The dashboard warns you 60 days before expiry.'],
        ['name' => 'document_id', 'type' => 'media', 'label' => 'Certificate document', 'accept' => 'any',
         'help' => 'A PDF or a clear photo/scan of the certificate.'],
        ['name' => 'show_number', 'type' => 'switch', 'label' => 'Show registration number publicly', 'side' => true, 'default' => true,
         'help' => 'Turn off to hide the number on the website (it stays visible here).'],
    ],
]);

/** Badge for the list: Expired / Expires in N days / Valid. */
function cert_expiry_badge(?string $expiry): array
{
    if (!$expiry) {
        return [];
    }
    $days = (int) floor((strtotime($expiry) - strtotime('today')) / 86400);
    if ($days < 0) {
        return [['Expired', 'danger']];
    }
    if ($days <= 60) {
        return [[$days === 0 ? 'Expires today' : 'Expires in ' . $days . ' day' . ($days === 1 ? '' : 's'), 'warn']];
    }
    return [['Valid', 'published']];
}
