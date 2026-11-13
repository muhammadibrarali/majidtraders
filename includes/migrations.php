<?php
/**
 * Small, automatic database upgrades.
 *
 * Runs when an admin opens any admin page (see require_admin()). Each step
 * runs once; the current step number is stored in settings.schema_version.
 * Every step is written so that running it twice is harmless.
 *
 * To change the database later: update includes/schema.php (for fresh
 * installs) AND add the next numbered step here.
 */
declare(strict_types=1);

const SCHEMA_VERSION = 3;

function column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function constraint_exists(PDO $pdo, string $table, string $name): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?');
    $stmt->execute([$table, $name]);
    return (int) $stmt->fetchColumn() > 0;
}

function index_exists(PDO $pdo, string $table, string $name): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
    $stmt->execute([$table, $name]);
    return (int) $stmt->fetchColumn() > 0;
}

function run_migrations(): void
{
    $pdo = db();
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'schema_version'");
    $stmt->execute();
    $current = (int) $stmt->fetchColumn();
    if ($current >= SCHEMA_VERSION) {
        return;
    }

    $pdo->query("SELECT GET_LOCK('mt_migrations', 30)");
    try {
        $stmt->execute();
        $current = (int) $stmt->fetchColumn();
        foreach (migration_steps() as $version => $step) {
            if ($version > $current) {
                $step($pdo);
                $pdo->prepare("INSERT INTO settings (setting_key, setting_value, setting_group) VALUES ('schema_version', ?, 'system')
                               ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
                    ->execute([(string) $version]);
            }
        }
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('mt_migrations')");
    }
}

function migration_steps(): array
{
    return [

    // ------------------------------------------------------------------ v2
    // Media titles, service gallery, all Site Settings keys, company details,
    // the three starter services.
    2 => static function (PDO $pdo): void {
        if (!column_exists($pdo, 'media', 'title')) {
            $pdo->exec('ALTER TABLE media ADD COLUMN title VARCHAR(190) NULL AFTER original_name');
        }
        $tables = require ROOT_PATH . '/includes/schema.php';
        $pdo->exec($tables['service_images']);

        // [key, default value, group, label]
        $keys = [
            ['company_name', 'Majid Traders', 'company', 'Company name'],
            ['tagline', "Live the Trader's Life", 'company', 'Tagline'],
            ['company_intro', '', 'company', 'Short introduction'],
            ['owner_name', 'Majid Khan', 'company', 'Owner name'],
            ['address', 'Main Malakand Road, Near Mardan Fuel CNG, Pohan Colony, Mardan, Khyber Pakhtunkhwa, Pakistan', 'company', 'Full address'],
            ['phone', '0300 5741542', 'company', 'Phone number'],
            ['whatsapp', '', 'company', 'WhatsApp number'],
            ['email', 'majidtraders1991@outlook.com', 'company', 'Email address'],
            ['office_hours', 'Mon–Sat, 9:00 am – 6:00 pm', 'company', 'Office hours'],
            ['map_embed_url', '', 'company', 'Google Maps embed link'],
            ['logo_id', '', 'company', 'Logo'],
            ['favicon_id', '', 'company', 'Favicon'],
            ['company_profile_pdf_id', '', 'company', 'Company profile PDF'],
            ['facebook_url', '', 'social', 'Facebook'],
            ['instagram_url', '', 'social', 'Instagram'],
            ['linkedin_url', '', 'social', 'LinkedIn'],
            ['youtube_url', '', 'social', 'YouTube'],
            ['tiktok_url', '', 'social', 'TikTok'],
            ['x_url', '', 'social', 'X (Twitter)'],
            ['hero_headline', "Live the Trader's Life", 'hero', 'Main headline'],
            ['hero_subheadline', 'Import & export, government & private contracting and general order supply — from Mardan to all of Pakistan.', 'hero', 'Sub-headline'],
            ['hero_button1_text', 'Our Services', 'hero', 'Button 1 text'],
            ['hero_button1_link', '/services', 'hero', 'Button 1 link'],
            ['hero_button2_text', 'Contact Us', 'hero', 'Button 2 text'],
            ['hero_button2_link', '/contact', 'hero', 'Button 2 link'],
            ['hero_image_id', '', 'hero', 'Hero background image'],
            ['default_meta_description', 'Majid Traders, Mardan (Khyber Pakhtunkhwa): import & export, government & private contracting, and general order supply.', 'seo', 'Default meta description'],
            ['default_og_image_id', '', 'seo', 'Default share image'],
        ];
        $insert = $pdo->prepare('INSERT IGNORE INTO settings (setting_key, setting_value, setting_group, label) VALUES (?, ?, ?, ?)');
        foreach ($keys as $row) {
            $insert->execute($row);
        }

        // Fill in the known company details where the old placeholder is still there.
        $prefill = $pdo->prepare("UPDATE settings SET setting_value = ?, setting_group = ?
                                  WHERE setting_key = ? AND (setting_value IS NULL OR setting_value = '' OR setting_value = ?)");
        $prefill->execute(['Majid Khan', 'company', 'owner_name', '']);
        $prefill->execute(['Main Malakand Road, Near Mardan Fuel CNG, Pohan Colony, Mardan, Khyber Pakhtunkhwa, Pakistan', 'company', 'address', 'Mardan, Khyber Pakhtunkhwa, Pakistan']);
        $prefill->execute(['0300 5741542', 'company', 'phone', '']);
        $prefill->execute(['majidtraders1991@outlook.com', 'company', 'email', 'info@majidtraders.org']);
        $prefill->execute(['Import & export, government & private contracting, and general order supply from Mardan, Khyber Pakhtunkhwa.', 'company', 'company_intro', '']);
        $pdo->exec("UPDATE settings SET setting_group = 'company' WHERE setting_key IN ('whatsapp','office_hours','map_embed_url','company_name','tagline','company_intro')");

        // Starter services (drafts) — only if none exist yet.
        if ((int) $pdo->query('SELECT COUNT(*) FROM services')->fetchColumn() === 0) {
            $svc = $pdo->prepare("INSERT INTO services (title, slug, icon, excerpt, body, status, sort_order) VALUES (?, ?, ?, ?, ?, 'draft', ?)");
            $svc->execute(['Import & Export', 'import-export', 'globe',
                'Reliable import and export of goods, handled end to end — sourcing, documentation, customs and delivery.',
                '<p><strong>Placeholder text — please replace.</strong> Describe what you import and export, the countries you work with, how you handle customs and logistics, and why clients trust Majid Traders.</p>', 10]);
            $svc->execute(['Government & Private Contracting', 'government-private-contracting', 'landmark',
                'Construction, renovation and maintenance contracts for government departments and private clients.',
                '<p><strong>Placeholder text — please replace.</strong> Describe the kinds of contracts you take on (construction, renovation, maintenance), the departments you work with, and your registrations.</p>', 20]);
            $svc->execute(['General Order Supply', 'general-order-supply', 'package',
                'On-time supply of office, school, hospital and industrial goods for government and private orders.',
                '<p><strong>Placeholder text — please replace.</strong> Describe the goods you supply, typical customers, delivery areas and how orders are handled.</p>', 30]);
        }
    },

    // ------------------------------------------------------------------ v3
    // Projects (status, related services), clients (marquee), team
    // (department, no personal phone numbers), blog (tags, reading time,
    // team authors), certifications (grade, show number, expiry index).
    3 => static function (PDO $pdo): void {
        $add = static function (string $table, string $column, string $definition) use ($pdo): void {
            if (!column_exists($pdo, $table, $column)) {
                $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
            }
        };
        $add('projects', 'project_status', "ENUM('completed','ongoing') NOT NULL DEFAULT 'completed' AFTER `year`");
        $tables = require ROOT_PATH . '/includes/schema.php';
        $pdo->exec($tables['project_services']);

        $add('clients', 'show_in_marquee', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER is_featured');

        $add('team_members', 'department', 'VARCHAR(60) NULL AFTER `position`');
        if (column_exists($pdo, 'team_members', 'phone')) {
            // Personal phone numbers must not be stored (owner's rule).
            $pdo->exec('ALTER TABLE team_members DROP COLUMN phone');
        }

        $add('blog_posts', 'author_team_id', 'INT UNSIGNED NULL AFTER author_id');
        $add('blog_posts', 'tags', 'VARCHAR(255) NULL AFTER body');
        $add('blog_posts', 'reading_minutes', 'SMALLINT UNSIGNED NULL AFTER tags');
        if (!constraint_exists($pdo, 'blog_posts', 'fk_blog_author_team')) {
            $pdo->exec('ALTER TABLE blog_posts ADD CONSTRAINT fk_blog_author_team FOREIGN KEY (author_team_id) REFERENCES team_members (id) ON DELETE SET NULL');
        }

        $add('certifications', 'grade', 'VARCHAR(60) NULL AFTER registration_number');
        $add('certifications', 'show_number', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER grade');
        if (!index_exists($pdo, 'certifications', 'idx_cert_expiry')) {
            $pdo->exec('ALTER TABLE certifications ADD KEY idx_cert_expiry (expiry_date)');
        }
    },

    ];
}
