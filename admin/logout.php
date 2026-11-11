<?php
/** Logout — /admin/logout (POST with CSRF only). */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/auth.php';
admin_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check()) {
    // Someone opened the link directly or the form is stale: don't log out, go back.
    redirect('/admin/');
}

logout_admin();
session_start();
flash('success', 'You have been logged out.');
redirect('/admin/login');
