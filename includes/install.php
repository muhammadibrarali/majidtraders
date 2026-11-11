<?php
/**
 * Installation helpers used by /admin/setup.php.
 */
declare(strict_types=1);

/** Does a table exist in the current database? */
function table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

/**
 * Setup counts as completed once ANY admin account exists,
 * or once the "setup_completed_at" flag has been saved.
 */
function setup_is_completed(PDO $pdo): bool
{
    if (table_exists($pdo, 'admin_users')
        && (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn() > 0) {
        return true;
    }
    if (table_exists($pdo, 'settings')) {
        $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        $stmt->execute(['setup_completed_at']);
        return (string) $stmt->fetchColumn() !== '';
    }
    return false;
}

/** Create every table that does not exist yet. Returns the list of table names. */
function install_schema(PDO $pdo): array
{
    $tables = require ROOT_PATH . '/includes/schema.php';
    foreach ($tables as $sql) {
        $pdo->exec($sql);
    }
    return array_keys($tables);
}

/** Fill in default settings and starter counters (never overwrites existing values). */
function seed_defaults(PDO $pdo): void
{
    $settings = [
        // key, value, group, label
        ['company_name',     'Majid Traders', 'company', 'Company name'],
        ['tagline',          "Live the Trader's Life", 'company', 'Tagline'],
        ['company_intro',    'Import & export, government & private contracting, and general order supply from Mardan, Khyber Pakhtunkhwa.', 'company', 'Short introduction'],
        ['founded_year',     '', 'company', 'Year founded'],
        ['ntn_number',       '', 'company', 'NTN number'],
        ['strn_number',      '', 'company', 'STRN / sales tax number'],
        ['address',          'Mardan, Khyber Pakhtunkhwa, Pakistan', 'contact', 'Office address'],
        ['phone',            '', 'contact', 'Phone number'],
        ['mobile',           '', 'contact', 'Mobile number'],
        ['whatsapp',         '', 'contact', 'WhatsApp number (international format, e.g. 923001234567)'],
        ['email',            'info@majidtraders.org', 'contact', 'Public email address'],
        ['office_hours',     'Mon–Sat, 9:00 am – 6:00 pm', 'contact', 'Office hours'],
        ['map_embed_url',    '', 'contact', 'Google Maps embed link'],
        ['facebook_url',     '', 'social', 'Facebook page'],
        ['linkedin_url',     '', 'social', 'LinkedIn page'],
        ['instagram_url',    '', 'social', 'Instagram'],
        ['youtube_url',      '', 'social', 'YouTube'],
        ['x_url',            '', 'social', 'X (Twitter)'],
        ['portal_url',       'https://portal.majidtraders.org', 'general', 'Staff portal link'],
        ['schema_version',   '1', 'system', 'Database version'],
        ['setup_completed_at', '', 'system', 'Setup completed at'],
    ];
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO settings (setting_key, setting_value, setting_group, label) VALUES (?, ?, ?, ?)'
    );
    foreach ($settings as $row) {
        $stmt->execute($row);
    }

    if ((int) $pdo->query('SELECT COUNT(*) FROM counters')->fetchColumn() === 0) {
        $stmt = $pdo->prepare(
            "INSERT INTO counters (label, value, suffix, status, sort_order) VALUES (?, ?, ?, 'draft', ?)"
        );
        $starter = [
            ['Years of experience', 0, '+', 1],
            ['Projects completed',  0, '+', 2],
            ['Government & private clients', 0, '+', 3],
            ['Districts served',    0, '',  4],
        ];
        foreach ($starter as $row) {
            $stmt->execute($row);
        }
    }

    $defaultCategories = [
        ['Construction & Renovation', 'construction-renovation', 1],
        ['Supply & Procurement',      'supply-procurement',      2],
        ['Import & Export',           'import-export',           3],
    ];
    $stmt = $pdo->prepare(
        "INSERT IGNORE INTO project_categories (name, slug, status, sort_order) VALUES (?, ?, 'published', ?)"
    );
    foreach ($defaultCategories as $row) {
        $stmt->execute($row);
    }
}
