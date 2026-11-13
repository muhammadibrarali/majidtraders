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

const SCHEMA_VERSION = 6;

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

    // ------------------------------------------------------------------ v4
    // "Website is live" switch (OFF = coming-soon page for visitors, preview
    // for logged-in admins) and editable texts for every home page section.
    4 => static function (PDO $pdo): void {
        $process = json_encode([
            ['title' => 'Enquiry & tender study', 'text' => 'We study your tender documents, BOQ or requirement list and ask the right questions early.'],
            ['title' => 'Site survey & costing', 'text' => 'Our team visits the site or checks the specifications and prepares an honest, detailed cost.'],
            ['title' => 'Proposal & agreement', 'text' => 'You receive a clear quotation with timelines. Once approved, we plan materials, people and logistics.'],
            ['title' => 'Execution & supply', 'text' => 'Work and deliveries start on schedule, with regular progress updates and quality checks.'],
            ['title' => 'Handover & support', 'text' => 'We hand over complete work with documentation and stay available for after-sales support.'],
        ], JSON_UNESCAPED_UNICODE);
        $keys = [
            ['site_live', '0', 'status', 'Website is live'],
            ['home_meta_title', 'Majid Traders – Contracting, Supply and Import & Export in Mardan, KP', 'seo', 'Home page title'],
            ['home_clients_title', 'Trusted by government departments and private clients', 'home', 'Client logos heading'],
            ['home_about_eyebrow', 'About us', 'home', 'About: small label'],
            ['home_about_heading', 'Building, supplying and trading across Khyber Pakhtunkhwa', 'home', 'About: heading'],
            ['home_about_image_id', '', 'home', 'About: image'],
            ['home_services_eyebrow', 'What we do', 'home', 'Services: small label'],
            ['home_services_title', 'Services for government and private clients', 'home', 'Services: heading'],
            ['home_projects_eyebrow', 'Featured work', 'home', 'Projects: small label'],
            ['home_projects_title', 'Projects we are proud of', 'home', 'Projects: heading'],
            ['home_process_eyebrow', 'How we work', 'home', 'Process: small label'],
            ['home_process_title', 'From tender to handover', 'home', 'Process: heading'],
            ['home_process', $process, 'home', 'Process steps'],
            ['home_certs_eyebrow', 'Registered & certified', 'home', 'Certifications: small label'],
            ['home_certs_title', 'Certifications & registrations', 'home', 'Certifications: heading'],
            ['home_testimonials_eyebrow', 'Client feedback', 'home', 'Testimonials: small label'],
            ['home_testimonials_title', 'What our clients say', 'home', 'Testimonials: heading'],
            ['home_blog_eyebrow', 'News & insights', 'home', 'Blog: small label'],
            ['home_blog_title', 'Latest from the blog', 'home', 'Blog: heading'],
            ['home_cta_heading', "Have a tender or project in mind? Let's talk.", 'home', 'Call to action: heading'],
            ['home_cta_text', 'Share your requirements and our team will get back to you with a clear, honest quotation.', 'home', 'Call to action: text'],
        ];
        $insert = $pdo->prepare('INSERT IGNORE INTO settings (setting_key, setting_value, setting_group, label) VALUES (?, ?, ?, ?)');
        foreach ($keys as $row) {
            $insert->execute($row);
        }
    },

    // ------------------------------------------------------------------ v5
    // Texts and header images for the inner pages (About, Services,
    // Projects, Clients). Starter text only — the owner edits it in
    // Site Settings › About page / Page headers.
    5 => static function (PDO $pdo): void {
        $values = json_encode([
            ['title' => 'Honesty', 'text' => 'Clear quotations, honest billing and no hidden costs — ever.'],
            ['title' => 'Quality', 'text' => 'Materials and workmanship that meet the specification, checked at every stage.'],
            ['title' => 'On-time delivery', 'text' => 'We plan carefully so supplies and projects are delivered when promised.'],
            ['title' => 'Long-term relationships', 'text' => 'Most of our work comes from clients who return to us again and again.'],
        ], JSON_UNESCAPED_UNICODE);
        $keys = [
            // About page
            ['about_page_title', 'About Majid Traders', 'about', 'Page title'],
            ['about_page_lead', "A Mardan-based company in import & export, government & private contracting and general order supply.", 'about', 'Short intro under the title'],
            ['about_page_image_id', '', 'about', 'Header image'],
            ['about_meta_description', 'Learn about Majid Traders, Mardan: our story, mission, values and the people behind our contracting, supply and import & export work in Khyber Pakhtunkhwa.', 'about', 'Google description'],
            ['about_story_heading', 'Our story', 'about', 'Story heading'],
            ['about_story_body', '<p><strong>Placeholder text — please replace.</strong> Tell the story of Majid Traders: when and how the company started, the first projects, how the business grew, and what makes your team different.</p>', 'about', 'Story text'],
            ['about_mission', 'To deliver reliable contracting, supply and trading services that our clients can trust — on time, to specification and at a fair price.', 'about', 'Mission'],
            ['about_vision', 'To be the most trusted contracting and supply partner in Khyber Pakhtunkhwa for government departments and private businesses.', 'about', 'Vision'],
            ['about_values', $values, 'about', 'Core values'],
            ['about_owner_heading', 'A message from our founder', 'about', 'Owner message heading'],
            ['about_owner_title', 'Founder & Proprietor', 'about', "Owner's title"],
            ['about_owner_message', "Placeholder text — please replace with a short personal message from the owner to clients and partners.\n\nThank you for your trust in Majid Traders.", 'about', 'Owner message'],
            ['about_owner_photo_id', '', 'about', 'Owner photo'],
            // Services / Projects / Clients page headers
            ['services_page_title', 'Our services', 'pages', 'Services page title'],
            ['services_page_lead', 'Import & export, government & private contracting and general order supply — handled end to end by one reliable team.', 'pages', 'Services page intro'],
            ['services_page_image_id', '', 'pages', 'Services header image'],
            ['services_meta_description', 'Services of Majid Traders, Mardan: import & export, government and private contracting, and general order supply across Khyber Pakhtunkhwa.', 'pages', 'Services Google description'],
            ['projects_page_title', 'Our projects', 'pages', 'Projects page title'],
            ['projects_page_lead', 'A selection of completed and ongoing work for government departments and private clients.', 'pages', 'Projects page intro'],
            ['projects_page_image_id', '', 'pages', 'Projects header image'],
            ['projects_meta_description', 'Completed and ongoing projects by Majid Traders: construction, renovation, supply and procurement for government and private clients in Khyber Pakhtunkhwa.', 'pages', 'Projects Google description'],
            ['clients_page_title', 'Our clients', 'pages', 'Clients page title'],
            ['clients_page_lead', 'Government departments and private organisations that trust Majid Traders with their projects and supplies.', 'pages', 'Clients page intro'],
            ['clients_page_image_id', '', 'pages', 'Clients header image'],
            ['clients_meta_description', 'Clients of Majid Traders, Mardan: federal and provincial government departments, semi-government bodies and private organisations.', 'pages', 'Clients Google description'],
        ];
        $insert = $pdo->prepare('INSERT IGNORE INTO settings (setting_key, setting_value, setting_group, label) VALUES (?, ?, ?, ?)');
        foreach ($keys as $row) {
            $insert->execute($row);
        }
    },

    // ------------------------------------------------------------------ v6
    // Page headers for Team, Blog and Certifications + the Privacy Policy
    // (Site Settings › Page headers / Legal). Starter text only.
    6 => static function (PDO $pdo): void {
        $privacy = <<<'HTML'
<p>This Privacy Policy explains how <strong>Majid Traders</strong> (“we”, “us”, “our”), Mardan, Khyber Pakhtunkhwa, Pakistan, collects, uses and protects personal information when you visit majidtraders.org or contact us.</p>
<h2>Information we collect</h2>
<ul>
<li><strong>Information you give us:</strong> when you use our contact or quotation form, send us an email or message us on WhatsApp, we receive the details you provide — for example your name, phone number, email address, company or department, and the content of your message or tender requirements.</li>
<li><strong>Technical information:</strong> like most websites, our hosting provider records basic technical data such as your IP address, browser type and the pages you visit, for security and to keep the website working.</li>
</ul>
<h2>Cookies</h2>
<p>Our public website does not use advertising or tracking cookies. A small technical cookie is used only in our private administration area by our own staff.</p>
<h2>How we use your information</h2>
<ul>
<li>To reply to your enquiry and prepare quotations or proposals.</li>
<li>To deliver the goods, services or projects you have requested.</li>
<li>To keep records required for accounting, tax and government procurement purposes.</li>
<li>To protect our website against misuse and fraud.</li>
</ul>
<p>We do not sell or rent your personal information, and we do not send marketing messages without your permission.</p>
<h2>Sharing your information</h2>
<p>We only share personal information when it is needed to provide our services (for example with transport or logistics partners), with our IT and hosting providers who process data on our behalf, or when required by law or by a competent authority in Pakistan.</p>
<h2>How long we keep information</h2>
<p>We keep enquiries and business records only for as long as needed for the purposes above and to meet our legal, tax and contractual obligations. After that, information is deleted or anonymised.</p>
<h2>How we protect your information</h2>
<p>Our website uses an encrypted (HTTPS) connection, and access to stored messages is limited to authorised staff with secure passwords. No system is completely secure, but we take reasonable steps to protect your information.</p>
<h2>Your choices and rights</h2>
<p>You can ask us to see, correct or delete the personal information we hold about you, or ask us to stop contacting you, by using the contact details below. We will respond within a reasonable time.</p>
<h2>Applicable law</h2>
<p>We handle personal information in line with the applicable laws of Pakistan, including the Prevention of Electronic Crimes Act, 2016.</p>
<h2>Links to other websites</h2>
<p>Our website may link to other websites (for example WhatsApp, Facebook or LinkedIn). We are not responsible for the privacy practices of those websites.</p>
<h2>Changes to this policy</h2>
<p>We may update this Privacy Policy from time to time. The “last updated” date at the top of this page shows when it was last changed.</p>
HTML;
        $keys = [
            ['team_page_title', 'Our team', 'pages', 'Team page title'],
            ['team_page_lead', 'The engineers, buyers and field staff who deliver every project and order on time.', 'pages', 'Team page intro'],
            ['team_page_image_id', '', 'pages', 'Team header image'],
            ['team_meta_description', 'Meet the team of Majid Traders, Mardan: management, engineering, procurement, accounts and field staff.', 'pages', 'Team Google description'],
            ['blog_page_title', 'News & insights', 'pages', 'Blog page title'],
            ['blog_page_lead', 'Company news, completed projects and practical tips on tenders, supply and contracting in Khyber Pakhtunkhwa.', 'pages', 'Blog page intro'],
            ['blog_page_image_id', '', 'pages', 'Blog header image'],
            ['blog_meta_description', 'News and insights from Majid Traders, Mardan: projects, tenders, supply and contracting in Khyber Pakhtunkhwa.', 'pages', 'Blog Google description'],
            ['certifications_page_title', 'Certifications & registrations', 'pages', 'Certifications page title'],
            ['certifications_page_lead', 'Our official registrations and certificates — valid and up to date.', 'pages', 'Certifications page intro'],
            ['certifications_page_image_id', '', 'pages', 'Certifications header image'],
            ['certifications_meta_description', 'Certifications and registrations of Majid Traders, Mardan: PEC, FBR, KPPRA and other official registrations.', 'pages', 'Certifications Google description'],
            ['privacy_page_title', 'Privacy Policy', 'legal', 'Privacy page title'],
            ['privacy_updated', date('Y-m-d'), 'legal', 'Privacy policy last updated'],
            ['privacy_body', $privacy, 'legal', 'Privacy policy text'],
            ['privacy_meta_description', 'How Majid Traders collects, uses and protects personal information on majidtraders.org and through our contact form.', 'legal', 'Privacy Google description'],
        ];
        $insert = $pdo->prepare('INSERT IGNORE INTO settings (setting_key, setting_value, setting_group, label) VALUES (?, ?, ?, ?)');
        foreach ($keys as $row) {
            $insert->execute($row);
        }
    },

    ];
}
