<?php
/**
 * Full database design for majidtraders.org.
 *
 * Returns a list of  table name => CREATE TABLE statement.
 * All statements use IF NOT EXISTS, so running them twice is harmless.
 * Order matters: tables that others point to (admin_users, media) come first.
 *
 * Common columns:
 *   status      'draft' or 'published' — only published items appear on the website
 *   sort_order  smaller number shows first
 *   created_at / updated_at  filled automatically by MySQL
 *   slug        the clean-URL part, e.g. school-renovation-mardan (always unique)
 */
declare(strict_types=1);

defined('MT_TABLE_OPTIONS') || define('MT_TABLE_OPTIONS', 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
defined('MT_TIMESTAMPS') || define('MT_TIMESTAMPS', "
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
defined('MT_STATUS') || define('MT_STATUS', "status ENUM('draft','published') NOT NULL DEFAULT 'draft'");
defined('MT_SEO') || define('MT_SEO', "
    meta_title VARCHAR(160) NULL,
    meta_description VARCHAR(300) NULL");

return [

// ---------------------------------------------------------------- admin & security
'admin_users' => "CREATE TABLE IF NOT EXISTS admin_users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','editor') NOT NULL DEFAULT 'editor',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    failed_logins SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    last_login_at DATETIME NULL,
    last_login_ip VARCHAR(45) NULL," . MT_TIMESTAMPS . ",
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_email (email)
) " . MT_TABLE_OPTIONS,

'login_attempts' => "CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(190) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_login_email_time (email, attempted_at),
    KEY idx_login_ip_time (ip_address, attempted_at)
) " . MT_TABLE_OPTIONS,

// ---------------------------------------------------------------- uploaded files
// Files live in private/uploads/ under a random name; this table remembers them.
'media' => "CREATE TABLE IF NOT EXISTS media (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    file_name VARCHAR(100) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    title VARCHAR(190) NULL,
    mime_type VARCHAR(100) NOT NULL,
    kind ENUM('image','document') NOT NULL DEFAULT 'image',
    size_bytes INT UNSIGNED NOT NULL DEFAULT 0,
    width SMALLINT UNSIGNED NULL,
    height SMALLINT UNSIGNED NULL,
    alt_text VARCHAR(255) NULL,
    uploaded_by INT UNSIGNED NULL," . MT_TIMESTAMPS . ",
    PRIMARY KEY (id),
    UNIQUE KEY uq_media_file (file_name),
    KEY idx_media_kind (kind),
    CONSTRAINT fk_media_user FOREIGN KEY (uploaded_by) REFERENCES admin_users (id) ON DELETE SET NULL
) " . MT_TABLE_OPTIONS,

// ---------------------------------------------------------------- site settings
// Simple key/value list: company info, phone numbers, social links, etc.
'settings' => "CREATE TABLE IF NOT EXISTS settings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT NULL,
    setting_group VARCHAR(50) NOT NULL DEFAULT 'general',
    label VARCHAR(150) NULL," . MT_TIMESTAMPS . ",
    PRIMARY KEY (id),
    UNIQUE KEY uq_setting_key (setting_key),
    KEY idx_setting_group (setting_group)
) " . MT_TABLE_OPTIONS,

// Animated counter numbers on the home page (e.g. "250+ Projects completed")
'counters' => "CREATE TABLE IF NOT EXISTS counters (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    label VARCHAR(100) NOT NULL,
    value INT UNSIGNED NOT NULL DEFAULT 0,
    prefix VARCHAR(10) NULL,
    suffix VARCHAR(10) NULL,
    " . MT_STATUS . ",
    sort_order INT NOT NULL DEFAULT 0," . MT_TIMESTAMPS . ",
    PRIMARY KEY (id),
    KEY idx_counters_list (status, sort_order)
) " . MT_TABLE_OPTIONS,

// ---------------------------------------------------------------- services
'services' => "CREATE TABLE IF NOT EXISTS services (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(150) NOT NULL,
    slug VARCHAR(170) NOT NULL,
    icon VARCHAR(60) NULL,
    excerpt VARCHAR(500) NULL,
    body MEDIUMTEXT NULL,
    image_id INT UNSIGNED NULL," . MT_SEO . ",
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    " . MT_STATUS . ",
    sort_order INT NOT NULL DEFAULT 0," . MT_TIMESTAMPS . ",
    PRIMARY KEY (id),
    UNIQUE KEY uq_services_slug (slug),
    KEY idx_services_list (status, sort_order),
    CONSTRAINT fk_services_image FOREIGN KEY (image_id) REFERENCES media (id) ON DELETE SET NULL
) " . MT_TABLE_OPTIONS,

// Gallery: optional extra photos per service
'service_images' => "CREATE TABLE IF NOT EXISTS service_images (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    service_id INT UNSIGNED NOT NULL,
    media_id INT UNSIGNED NOT NULL,
    caption VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0," . MT_TIMESTAMPS . ",
    PRIMARY KEY (id),
    UNIQUE KEY uq_service_media (service_id, media_id),
    KEY idx_service_images_order (service_id, sort_order),
    CONSTRAINT fk_simg_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE CASCADE,
    CONSTRAINT fk_simg_media FOREIGN KEY (media_id) REFERENCES media (id) ON DELETE CASCADE
) " . MT_TABLE_OPTIONS,

// ---------------------------------------------------------------- clients
'clients' => "CREATE TABLE IF NOT EXISTS clients (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(190) NOT NULL,
    slug VARCHAR(200) NOT NULL,
    department_type ENUM('federal_government','provincial_government','local_government','semi_government','armed_forces','private','ngo','international','other') NOT NULL DEFAULT 'other',
    logo_id INT UNSIGNED NULL,
    website_url VARCHAR(255) NULL,
    description TEXT NULL,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    show_in_marquee TINYINT(1) NOT NULL DEFAULT 1,
    " . MT_STATUS . ",
    sort_order INT NOT NULL DEFAULT 0," . MT_TIMESTAMPS . ",
    PRIMARY KEY (id),
    UNIQUE KEY uq_clients_slug (slug),
    KEY idx_clients_list (status, sort_order),
    KEY idx_clients_type (department_type),
    CONSTRAINT fk_clients_logo FOREIGN KEY (logo_id) REFERENCES media (id) ON DELETE SET NULL
) " . MT_TABLE_OPTIONS,

// ---------------------------------------------------------------- projects
'project_categories' => "CREATE TABLE IF NOT EXISTS project_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    description VARCHAR(500) NULL,
    " . MT_STATUS . ",
    sort_order INT NOT NULL DEFAULT 0," . MT_TIMESTAMPS . ",
    PRIMARY KEY (id),
    UNIQUE KEY uq_project_cat_slug (slug)
) " . MT_TABLE_OPTIONS,

'projects' => "CREATE TABLE IF NOT EXISTS projects (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(190) NOT NULL,
    slug VARCHAR(200) NOT NULL,
    category_id INT UNSIGNED NULL,
    service_id INT UNSIGNED NULL,
    client_id INT UNSIGNED NULL,
    client_department VARCHAR(190) NULL,
    location VARCHAR(150) NULL,
    year SMALLINT UNSIGNED NULL,
    project_status ENUM('completed','ongoing') NOT NULL DEFAULT 'completed',
    excerpt VARCHAR(500) NULL,
    body MEDIUMTEXT NULL,
    cover_image_id INT UNSIGNED NULL," . MT_SEO . ",
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    " . MT_STATUS . ",
    sort_order INT NOT NULL DEFAULT 0," . MT_TIMESTAMPS . ",
    PRIMARY KEY (id),
    UNIQUE KEY uq_projects_slug (slug),
    KEY idx_projects_list (status, is_featured, sort_order),
    KEY idx_projects_year (year),
    CONSTRAINT fk_projects_category FOREIGN KEY (category_id) REFERENCES project_categories (id) ON DELETE SET NULL,
    CONSTRAINT fk_projects_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE SET NULL,
    CONSTRAINT fk_projects_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE SET NULL,
    CONSTRAINT fk_projects_cover FOREIGN KEY (cover_image_id) REFERENCES media (id) ON DELETE SET NULL
) " . MT_TABLE_OPTIONS,

// Gallery: many photos per project
'project_images' => "CREATE TABLE IF NOT EXISTS project_images (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id INT UNSIGNED NOT NULL,
    media_id INT UNSIGNED NOT NULL,
    caption VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0," . MT_TIMESTAMPS . ",
    PRIMARY KEY (id),
    UNIQUE KEY uq_project_media (project_id, media_id),
    KEY idx_project_images_order (project_id, sort_order),
    CONSTRAINT fk_pimg_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
    CONSTRAINT fk_pimg_media FOREIGN KEY (media_id) REFERENCES media (id) ON DELETE CASCADE
) " . MT_TABLE_OPTIONS,

// Related services of a project (many-to-many)
'project_services' => "CREATE TABLE IF NOT EXISTS project_services (
    project_id INT UNSIGNED NOT NULL,
    service_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (project_id, service_id),
    KEY idx_ps_service (service_id),
    CONSTRAINT fk_ps_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
    CONSTRAINT fk_ps_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE CASCADE
) " . MT_TABLE_OPTIONS,

// ---------------------------------------------------------------- team
'team_members' => "CREATE TABLE IF NOT EXISTS team_members (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL,
    position VARCHAR(120) NULL,
    department VARCHAR(60) NULL,
    bio TEXT NULL,
    photo_id INT UNSIGNED NULL,
    email VARCHAR(190) NULL,
    linkedin_url VARCHAR(255) NULL,
    " . MT_STATUS . ",
    sort_order INT NOT NULL DEFAULT 0," . MT_TIMESTAMPS . ",
    PRIMARY KEY (id),
    UNIQUE KEY uq_team_slug (slug),
    KEY idx_team_list (status, sort_order),
    CONSTRAINT fk_team_photo FOREIGN KEY (photo_id) REFERENCES media (id) ON DELETE SET NULL
) " . MT_TABLE_OPTIONS,

// ---------------------------------------------------------------- blog
'blog_categories' => "CREATE TABLE IF NOT EXISTS blog_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    description VARCHAR(500) NULL,
    " . MT_STATUS . ",
    sort_order INT NOT NULL DEFAULT 0," . MT_TIMESTAMPS . ",
    PRIMARY KEY (id),
    UNIQUE KEY uq_blog_cat_slug (slug)
) " . MT_TABLE_OPTIONS,

'blog_posts' => "CREATE TABLE IF NOT EXISTS blog_posts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(220) NOT NULL,
    category_id INT UNSIGNED NULL,
    author_id INT UNSIGNED NULL,
    author_team_id INT UNSIGNED NULL,
    excerpt VARCHAR(500) NULL,
    body MEDIUMTEXT NULL,
    tags VARCHAR(255) NULL,
    reading_minutes SMALLINT UNSIGNED NULL,
    cover_image_id INT UNSIGNED NULL," . MT_SEO . ",
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    " . MT_STATUS . ",
    published_at DATETIME NULL," . MT_TIMESTAMPS . ",
    PRIMARY KEY (id),
    UNIQUE KEY uq_blog_slug (slug),
    KEY idx_blog_list (status, published_at),
    CONSTRAINT fk_blog_category FOREIGN KEY (category_id) REFERENCES blog_categories (id) ON DELETE SET NULL,
    CONSTRAINT fk_blog_author FOREIGN KEY (author_id) REFERENCES admin_users (id) ON DELETE SET NULL,
    CONSTRAINT fk_blog_author_team FOREIGN KEY (author_team_id) REFERENCES team_members (id) ON DELETE SET NULL,
    CONSTRAINT fk_blog_cover FOREIGN KEY (cover_image_id) REFERENCES media (id) ON DELETE SET NULL
) " . MT_TABLE_OPTIONS,

// ---------------------------------------------------------------- certifications
'certifications' => "CREATE TABLE IF NOT EXISTS certifications (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(190) NOT NULL,
    slug VARCHAR(200) NOT NULL,
    issuing_authority VARCHAR(190) NULL,
    registration_number VARCHAR(100) NULL,
    grade VARCHAR(60) NULL,
    show_number TINYINT(1) NOT NULL DEFAULT 1,
    issue_date DATE NULL,
    expiry_date DATE NULL,
    description TEXT NULL,
    image_id INT UNSIGNED NULL,
    document_id INT UNSIGNED NULL,
    " . MT_STATUS . ",
    sort_order INT NOT NULL DEFAULT 0," . MT_TIMESTAMPS . ",
    PRIMARY KEY (id),
    UNIQUE KEY uq_cert_slug (slug),
    KEY idx_cert_list (status, sort_order),
    KEY idx_cert_expiry (expiry_date),
    CONSTRAINT fk_cert_image FOREIGN KEY (image_id) REFERENCES media (id) ON DELETE SET NULL,
    CONSTRAINT fk_cert_document FOREIGN KEY (document_id) REFERENCES media (id) ON DELETE SET NULL
) " . MT_TABLE_OPTIONS,

// ---------------------------------------------------------------- testimonials
'testimonials' => "CREATE TABLE IF NOT EXISTS testimonials (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    author_name VARCHAR(120) NOT NULL,
    author_position VARCHAR(120) NULL,
    organization VARCHAR(190) NULL,
    client_id INT UNSIGNED NULL,
    quote TEXT NOT NULL,
    rating TINYINT UNSIGNED NULL,
    photo_id INT UNSIGNED NULL,
    " . MT_STATUS . ",
    sort_order INT NOT NULL DEFAULT 0," . MT_TIMESTAMPS . ",
    PRIMARY KEY (id),
    KEY idx_testimonials_list (status, sort_order),
    CONSTRAINT fk_testimonial_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE SET NULL,
    CONSTRAINT fk_testimonial_photo FOREIGN KEY (photo_id) REFERENCES media (id) ON DELETE SET NULL
) " . MT_TABLE_OPTIONS,

// ---------------------------------------------------------------- contact form
'contact_messages' => "CREATE TABLE IF NOT EXISTS contact_messages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(40) NULL,
    company VARCHAR(190) NULL,
    subject VARCHAR(200) NULL,
    message TEXT NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    status ENUM('new','read','replied','archived','spam') NOT NULL DEFAULT 'new'," . MT_TIMESTAMPS . ",
    PRIMARY KEY (id),
    KEY idx_messages_status (status, created_at)
) " . MT_TABLE_OPTIONS,

];
