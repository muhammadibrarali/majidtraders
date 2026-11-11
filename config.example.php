<?php
/**
 * EXAMPLE configuration — placeholders only. Do NOT put real values here.
 *
 * How to use:
 *   1. In Hostinger File Manager, create the folder  private  NEXT TO public_html
 *      (not inside it), so the path is  ../private/  from the website root.
 *   2. Copy this file there and name it  config.php
 *   3. Replace every placeholder with the real value.
 *
 * The real ../private/config.php is never committed to GitHub.
 */

return [
    'site' => [
        'url'   => 'https://majidtraders.org',
        'name'  => 'Majid Traders',
        'email' => 'info@majidtraders.org',
        'debug' => false, // keep false on the live site
    ],

    'db' => [
        'host'     => 'localhost',
        'name'     => 'u000000000_majidtraders',
        'user'     => 'u000000000_mtuser',
        'password' => 'CHANGE_ME',
        'charset'  => 'utf8mb4',
    ],

    'smtp' => [
        'host'       => 'smtp.hostinger.com',
        'port'       => 465,
        'encryption' => 'ssl',
        'username'   => 'no-reply@majidtraders.org',
        'password'   => 'CHANGE_ME',
        'from_email' => 'no-reply@majidtraders.org',
        'from_name'  => 'Majid Traders Website',
        'to_email'   => 'info@majidtraders.org', // where contact-form messages go
    ],

    'security' => [
        // Long random string (64+ characters). Used for signing tokens.
        'app_key' => 'CHANGE_ME_TO_A_LONG_RANDOM_STRING',
    ],

    'paths' => [
        // Absolute path to the private uploads folder (outside public_html).
        'uploads' => dirname(__DIR__) . '/private/uploads',
    ],
];
