<?php
/**
 * Admin logout script
 * Destroys admin session and redirects to homepage
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/Auth.php';

// Logout admin
Auth::logout();

// Redirect to homepage
redirect(app_url('index.php'));
