<?php
/**
 * EXAMPLE configuration — placeholders only. NEVER put real values in this file.
 *
 * How to use:
 *   1. Copy this file into the folder  private  that sits NEXT TO public_html
 *      (not inside it) and name the copy  config.php
 *   2. In the copy, replace every value marked CHANGE_ME with the real value.
 *
 * The real private/config.php is never uploaded to GitHub.
 */

return [

    // ----- Website -------------------------------------------------------
    'site' => [
        'url'      => 'https://majidtraders.org',  // Main address of the website, no slash at the end.
        'name'     => 'Majid Traders',             // Company name used in emails and page titles.
        'email'    => 'info@majidtraders.org',     // Main public email address.
        'timezone' => 'Asia/Karachi',              // Time zone for all dates (Pakistan time).
    ],

    // ----- Database (from Hostinger: Databases > Management) ---------------
    'db' => [
        'host'     => 'localhost',                 // Leave as localhost on Hostinger.
        'port'     => 3306,                        // Leave as 3306.
        'name'     => 'u000000000_CHANGE_ME',      // Full database name, e.g. u123456789_majid
        'user'     => 'u000000000_CHANGE_ME',      // Full database username, e.g. u123456789_admin
        'password' => 'CHANGE_ME',                 // The database user's password.
    ],

    // ----- Security ------------------------------------------------------
    'security' => [
        // A long random secret (64 characters). Generate a new one, never reuse.
        // It is also asked once on /admin/setup to prove you own this website.
        'app_key' => 'CHANGE_ME_TO_64_RANDOM_CHARACTERS',
    ],

    // ----- Email sending (SMTP) — used later for the contact form -----------
    'smtp' => [
        'host'       => 'smtp.hostinger.com',          // Hostinger's mail server.
        'port'       => 465,                           // 465 = SSL (recommended on Hostinger).
        'encryption' => 'ssl',                         // 'ssl' for port 465, 'tls' for port 587.
        'username'   => 'no-reply@majidtraders.org',   // Email account that sends the emails.
        'password'   => 'CHANGE_ME',                   // Password of that email account.
        'from_email' => 'no-reply@majidtraders.org',   // "From" address visitors see.
        'from_name'  => 'Majid Traders Website',       // "From" name visitors see.
        'to_email'   => 'info@majidtraders.org',       // Where contact-form messages are delivered.
    ],

    // ----- Uploads ---------------------------------------------------------
    'uploads' => [
        // Folder for uploaded images/PDFs. Leave as is: it points to private/uploads.
        'path'      => __DIR__ . '/uploads',
        'max_bytes' => 8 * 1024 * 1024,                // Largest allowed upload: 8 MB.
    ],
];
