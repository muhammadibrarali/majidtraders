<?php
/**
 * Site settings (key/value table "settings").
 *   setting('phone')            -> '0300 5741542'
 *   setting_media_id('logo_id') -> 12 or null
 *   save_settings(['phone' => '...'])
 */
declare(strict_types=1);

function settings_all(bool $refresh = false): array
{
    static $cache = null;
    if ($cache === null || $refresh) {
        $cache = [];
        foreach (db()->query('SELECT setting_key, setting_value FROM settings') as $row) {
            $cache[$row['setting_key']] = (string) $row['setting_value'];
        }
    }
    return $cache;
}

function setting(string $key, string $default = ''): string
{
    $all = settings_all();
    return isset($all[$key]) && $all[$key] !== '' ? $all[$key] : $default;
}

function setting_media_id(string $key): ?int
{
    $v = (int) setting($key, '0');
    return $v > 0 ? $v : null;
}

/** Save several settings at once (creates missing keys). */
function save_settings(array $values, string $group = 'general'): void
{
    $stmt = db()->prepare(
        'INSERT INTO settings (setting_key, setting_value, setting_group) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    foreach ($values as $key => $value) {
        $stmt->execute([$key, $value === null ? '' : (string) $value, $group]);
    }
    settings_all(true);
}

/** Setting keys that hold a media ID (used for "is this file in use?" checks). */
function media_setting_keys(): array
{
    return [
        'logo_id'               => 'Site Settings › Logo',
        'favicon_id'            => 'Site Settings › Favicon',
        'company_profile_pdf_id' => 'Site Settings › Company profile PDF',
        'hero_image_id'         => 'Site Settings › Home page hero image',
        'default_og_image_id'   => 'Site Settings › Default share image',
        'home_about_image_id'   => 'Site Settings › Home page › About image',
        'about_page_image_id'   => 'Site Settings › About page › Header image',
        'about_owner_photo_id'  => 'Site Settings › About page › Owner photo',
        'services_page_image_id' => 'Site Settings › Page headers › Services image',
        'projects_page_image_id' => 'Site Settings › Page headers › Projects image',
        'clients_page_image_id' => 'Site Settings › Page headers › Clients image',
        'team_page_image_id'    => 'Site Settings › Page headers › Team image',
        'blog_page_image_id'    => 'Site Settings › Page headers › Blog image',
        'certifications_page_image_id' => 'Site Settings › Page headers › Certifications image',
    ];
}
